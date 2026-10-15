<?php

namespace App\Services\Certificate;

use App\Models\AwardRecipient;
use App\Models\Certificate;
use App\Models\CertificateVersion;
use App\Models\Competition;
use App\Models\Participant;
use App\Models\User;
use App\Notifications\BrudelNotice;
use App\Services\DomainService;
use App\Services\PrivateFiles;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Certificate module. Managers upload the official PDF per recipient (team members each get their own).
 * Replacement and revocation keep the reason and every version. Public verification shows minimum data.
 */
class CertificateService extends DomainService
{
    public function __construct(private PrivateFiles $files) {}

    /**
     * @param  array{number: string, user_id: int, competition_id: int, award_type: string, issued_on: string}  $data
     */
    public function issue(User $actor, array $data, UploadedFile $file): Certificate
    {
        $competition = Competition::with('event')->findOrFail($data['competition_id']);
        abort_unless($actor->managesCompetition($competition), 403);
        $participant = Participant::where('competition_id', $competition->id)->where('user_id', $data['user_id'])
            ->where('status', 'confirmed')->whereHas('registration', fn ($q) => $q->where('status', 'accepted'))->with('registration')->first();
        $this->ensure($participant !== null, 'Penerima harus peserta terkonfirmasi pada pendaftaran yang diterima.');
        if (in_array($competition->assessment_type, ['rubric', 'bracket'], true)) {
            $this->ensure($competition->resultsPublished(), 'Sertifikat diterbitkan setelah hasil lomba disahkan.');
        } else {
            $this->ensure($competition->starts_at->isPast(), 'Sertifikat diterbitkan setelah lomba dilaksanakan.');
        }
        $stored = $this->files->storePdf($file, 'certificates/'.$competition->id);

        try {
            return DB::transaction(function () use ($actor, $data, $competition, $participant, $stored) {
                $recipient = AwardRecipient::where('user_id', $data['user_id'])
                    ->whereHas('award', fn ($q) => $q->where('competition_id', $competition->id)->where('status', 'verified'))->first();
                $certificate = Certificate::create([
                    'number' => trim($data['number']),
                    'user_id' => $data['user_id'],
                    'competition_id' => $competition->id,
                    'registration_id' => $participant->registration_id,
                    'award_recipient_id' => $recipient?->id,
                    'title' => $competition->name.' · '.$competition->event->name,
                    'award_type' => $data['award_type'],
                    'issued_on' => $data['issued_on'],
                    'verification_token' => Str::random(48),
                    'issued_by' => $actor->id,
                ]);
                CertificateVersion::create(['certificate_id' => $certificate->id, 'version' => 1, 'path' => $stored[0], 'disk' => $stored[1], 'uploaded_by' => $actor->id, 'created_at' => now()]);
                $this->history($certificate, null, 'valid', $actor);
                $this->audit($actor, 'certificate.issued', $certificate, ['number' => $certificate->number, 'user_id' => $certificate->user_id]);
                $certificate->user->notify(new BrudelNotice('Sertifikat baru', 'Sertifikat '.$certificate->award_type.' '.$competition->name.' tersedia di Sertifikat Saya.', '/sertifikat'));

                return $certificate;
            });
        } catch (\Throwable $e) {
            $this->files->delete($stored[0], $stored[1]);
            throw $e;
        }
    }

    public function replace(User $actor, Certificate $certificate, UploadedFile $file, string $reason): void
    {
        abort_unless($actor->managesCompetition($certificate->competition), 403);
        $this->ensure(mb_strlen(trim($reason)) >= 5, 'Alasan penggantian minimal 5 karakter.');
        $stored = $this->files->storePdf($file, 'certificates/'.$certificate->competition_id);

        try {
            DB::transaction(function () use ($actor, $certificate, $reason, $stored) {
                $certificate = Certificate::lockForUpdate()->findOrFail($certificate->id);
                $this->ensure($certificate->status === 'valid', 'Sertifikat yang dicabut tidak dapat diganti.');
                $version = $certificate->current_version + 1;
                CertificateVersion::create(['certificate_id' => $certificate->id, 'version' => $version, 'path' => $stored[0], 'disk' => $stored[1], 'reason' => $reason, 'uploaded_by' => $actor->id, 'created_at' => now()]);
                $certificate->update(['current_version' => $version]);
                $this->history($certificate, 'valid', 'valid', $actor, 'Versi '.$version.': '.$reason);
                $this->audit($actor, 'certificate.replaced', $certificate, ['version' => $version, 'reason' => $reason]);
                $certificate->user->notify(new BrudelNotice('Sertifikat diperbarui', 'Sertifikat '.$certificate->number.' diganti ke versi '.$version.'.', '/sertifikat'));
            });
        } catch (\Throwable $e) {
            $this->files->delete($stored[0], $stored[1]);
            throw $e;
        }
    }

    public function revoke(User $actor, Certificate $certificate, string $reason): void
    {
        abort_unless($actor->managesCompetition($certificate->competition), 403);
        $this->ensure(mb_strlen(trim($reason)) >= 5, 'Alasan pencabutan minimal 5 karakter.');
        DB::transaction(function () use ($actor, $certificate, $reason) {
            $certificate = Certificate::lockForUpdate()->findOrFail($certificate->id);
            $this->ensure($certificate->status === 'valid', 'Sertifikat sudah dicabut.');
            $certificate->update(['status' => 'revoked', 'revoked_reason' => $reason, 'revoked_at' => now(), 'revoked_by' => $actor->id]);
            $this->history($certificate, 'valid', 'revoked', $actor, $reason);
            $this->audit($actor, 'certificate.revoked', $certificate, ['reason' => $reason]);
            $certificate->user->notify(new BrudelNotice('Sertifikat dicabut', 'Sertifikat '.$certificate->number.' dicabut: '.$reason, '/sertifikat'));
        });
    }

    public function canDownload(User $user, Certificate $certificate): bool
    {
        if ($user->managesCompetition($certificate->competition)) {
            return true;
        }
        if ($certificate->status !== 'valid') {
            return false;
        }

        $winnerOfOwnDepartment = $certificate->award_recipient_id !== null && $user->assigned('jurusan')
            && $user->reviewsDepartment($certificate->user->departmentId());

        return $certificate->user_id === $user->id || $winnerOfOwnDepartment;
    }
}
