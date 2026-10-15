<?php

namespace App\Services\Achievement;

use App\Models\AwardRecipient;
use App\Models\AwardResult;
use App\Models\Competition;
use App\Models\CompetitionResult;
use App\Models\User;
use App\Notifications\BrudelNotice;
use App\Services\DomainService;
use App\Services\PrivateFiles;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Achievement module. One award_result per award (a team award counts once) with one recipient row per student.
 * BRUDEL awards come from published results; external awards need evidence and verification.
 */
class AchievementService extends DomainService
{
    public function __construct(private PrivateFiles $files) {}

    public static function levelKey(string $label): int
    {
        $key = array_search($label, config('brudel.levels'), true);

        return $key === false ? 1 : (int) $key;
    }

    public function fromResult(User $actor, Competition $competition, int $registrationId, string $award): AwardResult
    {
        abort_unless($actor->managesCompetition($competition), 403);
        $this->ensure($competition->resultsPublished(), 'Penghargaan hanya dapat ditetapkan setelah hasil disahkan.');
        $result = CompetitionResult::where('competition_id', $competition->id)->where('registration_id', $registrationId)->with('registration.participants')->first();
        $this->ensure($result !== null, 'Peserta tidak ada pada hasil resmi.');
        $registration = $result->registration;
        abort_if($registration->participants->contains('user_id', $actor->id), 403, 'Tidak dapat menetapkan penghargaan untuk diri atau tim sendiri.');
        $this->ensure(! AwardResult::where('registration_id', $registrationId)->where('award', $award)->exists(), 'Penghargaan ini sudah ditetapkan.');

        return DB::transaction(function () use ($actor, $competition, $registration, $award) {
            $competition->loadMissing('event');
            $team = $competition->format === 'team';
            $awardResult = AwardResult::create([
                'source' => 'brudel',
                'competition_id' => $competition->id,
                'registration_id' => $registration->id,
                'club_id' => $competition->event->club_id,
                'title' => $competition->name.' · '.$competition->event->name,
                'organizer' => $competition->event->organizer,
                'field' => $competition->field,
                'level' => self::levelKey($competition->level),
                'award' => $award,
                'awarded_on' => $competition->results_published_at->timezone(config('brudel.timezone'))->toDateString(),
                'is_team' => $team,
                'team_name' => $registration->team_name,
                'status' => 'verified',
                'submitted_by' => $actor->id,
                'verified_by' => $actor->id,
                'verified_at' => now(),
            ]);
            foreach ($registration->participants as $participant) {
                AwardRecipient::create([
                    'award_result_id' => $awardResult->id,
                    'user_id' => $participant->user_id,
                    'team_role' => ! $team ? 'individu' : ($participant->user_id === $registration->user_id ? 'ketua' : 'anggota'),
                ]);
            }
            $this->audit($actor, 'award.created', $awardResult, ['award' => $award]);
            Notification::send(User::whereIn('id', $registration->participants->pluck('user_id'))->get(),
                new BrudelNotice('Selamat! '.$award, $competition->name.' tercatat sebagai prestasi Anda.', '/prestasi'));

            return $awardResult;
        });
    }

    /**
     * @param  array{title: string, organizer: string, field: string, level: int, award: string, awarded_on: string, team_name?: ?string, members?: ?string, documentation_url?: ?string}  $data
     */
    public function submitExternal(User $actor, array $data, UploadedFile $evidence): AwardResult
    {
        $this->ensure($actor->isStudent(), 'Hanya mahasiswa aktif yang dapat mengajukan prestasi.');
        $this->ensure(isset(config('brudel.levels')[(int) $data['level']]), 'Tingkat prestasi tidak valid.');
        $nims = collect(preg_split('/[\s,;]+/', (string) ($data['members'] ?? ''), -1, PREG_SPLIT_NO_EMPTY))->unique()->reject(fn ($nim) => $nim === $actor->nim);
        $members = User::whereIn('nim', $nims)->where('active', true)->get();
        $this->ensure($members->count() === $nims->count(), 'NIM anggota tim tidak ditemukan: '.$nims->diff($members->pluck('nim'))->implode(', ').'.');
        $stored = $this->files->storePdf($evidence, 'achievements/'.$actor->id);

        try {
            return DB::transaction(function () use ($actor, $data, $members, $stored) {
                $team = $members->isNotEmpty();
                $award = AwardResult::create([
                    'source' => 'external',
                    'title' => $data['title'],
                    'organizer' => $data['organizer'],
                    'field' => $data['field'],
                    'level' => (int) $data['level'],
                    'award' => $data['award'],
                    'awarded_on' => $data['awarded_on'],
                    'is_team' => $team,
                    'team_name' => $team ? ($data['team_name'] ?? null) : null,
                    'documentation_url' => $data['documentation_url'] ?? null,
                    'evidence_path' => $stored[0],
                    'evidence_disk' => $stored[1],
                    'status' => 'pending',
                    'submitted_by' => $actor->id,
                ]);
                AwardRecipient::create(['award_result_id' => $award->id, 'user_id' => $actor->id, 'team_role' => $team ? 'ketua' : 'individu']);
                foreach ($members as $member) {
                    AwardRecipient::create(['award_result_id' => $award->id, 'user_id' => $member->id, 'team_role' => 'anggota']);
                }
                $this->history($award, null, 'pending', $actor);
                $this->audit($actor, 'award.submitted', $award);

                return $award;
            });
        } catch (\Throwable $e) {
            $this->files->delete($stored[0], $stored[1]);
            throw $e;
        }
    }

    public function verify(User $actor, AwardResult $award, string $decision, ?string $reason): void
    {
        abort_unless($actor->isStudentAffairs(), 403);
        abort_if($award->submitted_by === $actor->id || $award->recipients()->where('user_id', $actor->id)->exists(), 403, 'Tidak dapat memverifikasi prestasi sendiri.');
        $this->ensure(in_array($decision, ['verified', 'rejected'], true), 'Keputusan tidak valid.');
        $this->requireReason($decision, $reason, ['verified']);

        DB::transaction(function () use ($actor, $award, $decision, $reason) {
            $award = AwardResult::lockForUpdate()->findOrFail($award->id);
            $this->ensure($award->status === 'pending', 'Prestasi sudah diverifikasi.');
            $award->update(['status' => $decision, 'reason' => $reason, 'verified_by' => $actor->id, 'verified_at' => now()]);
            $this->history($award, 'pending', $decision, $actor, $reason);
            $this->audit($actor, 'award.'.$decision, $award, ['reason' => $reason]);
            Notification::send(User::whereIn('id', $award->recipients()->pluck('user_id'))->get(), new BrudelNotice(
                'Verifikasi prestasi '.$award->title,
                $decision === 'verified' ? 'Prestasi terverifikasi dan masuk laporan resmi.' : 'Prestasi ditolak: '.$reason,
                '/prestasi',
            ));
        });
    }

    public function setConsent(User $actor, bool $consent): void
    {
        DB::transaction(function () use ($actor, $consent) {
            $slug = $actor->public_slug ?? Str::slug(Str::limit($actor->name, 40, '')).'-'.Str::lower(Str::random(6));
            $actor->update(['publication_consent' => $consent, 'public_slug' => $slug]);
            $this->audit($actor, 'profile.consent', $actor, ['consent' => $consent]);
        });
    }

    public function canViewEvidence(User $user, AwardResult $award): bool
    {
        if ($user->isStudentAffairs() || $award->submitted_by === $user->id || $award->recipients()->where('user_id', $user->id)->exists()) {
            return true;
        }

        return $award->status === 'verified' && $user->assigned('jurusan') && $award->recipients()->whereHas('user.program',
            fn ($q) => $q->whereIn('department_id', $user->scopeIds('jurusan', 'department_id')))->exists();
    }
}
