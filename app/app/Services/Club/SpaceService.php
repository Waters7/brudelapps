<?php

namespace App\Services\Club;

use App\Models\ActivityAttendance;
use App\Models\Club;
use App\Models\ClubMessage;
use App\Models\ClubPost;
use App\Models\ClubQuest;
use App\Models\Membership;
use App\Models\QuestSubmission;
use App\Models\User;
use App\Notifications\BrudelNotice;
use App\Services\DomainService;
use App\Services\Wita;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * The member space of each organization: announcements, live chat, quests, and a points leaderboard.
 * Active members take part; officers, coaches and kemahasiswaan run it. Points come from approved quests
 * and from attending the organization's activities.
 */
class SpaceService extends DomainService
{
    public const ATTENDANCE_POINTS = 10;

    /** Level titles by minimum points. */
    public const LEVELS = [0 => 'Pendatang Baru', 50 => 'Penggerak', 150 => 'Andalan', 300 => 'Bintang', 600 => 'Legenda'];

    public function isMember(User $user, Club $club): bool
    {
        return Membership::where('club_id', $club->id)->where('user_id', $user->id)->where('status', 'active')->exists();
    }

    public function canEnter(User $user, Club $club): bool
    {
        return $this->canLead($user, $club) || $this->isMember($user, $club);
    }

    public function canLead(User $user, Club $club): bool
    {
        return $user->coachesClub($club->id);
    }

    public function post(User $actor, Club $club, string $title, string $body, bool $pinned): ClubPost
    {
        abort_unless($this->canLead($actor, $club), 403);
        $this->ensure(mb_strlen(trim($title)) >= 3 && mb_strlen(trim($body)) >= 5, 'Judul minimal 3 dan isi minimal 5 karakter.');

        return DB::transaction(function () use ($actor, $club, $title, $body, $pinned) {
            $post = ClubPost::create(['club_id' => $club->id, 'user_id' => $actor->id, 'title' => trim($title), 'body' => trim($body), 'pinned' => $pinned]);
            $this->audit($actor, 'space.announced', $post);
            Notification::send($this->members($club, $actor), new BrudelNotice('Pengumuman '.$club->name, $post->title, '/ruang/'.$club->id));

            return $post;
        });
    }

    public function togglePin(User $actor, ClubPost $post): void
    {
        abort_unless($this->canLead($actor, $post->club), 403);
        $post->update(['pinned' => ! $post->pinned]);
    }

    public function deletePost(User $actor, ClubPost $post): void
    {
        abort_unless($this->canLead($actor, $post->club), 403);
        $this->audit($actor, 'space.announcement_deleted', $post, ['title' => $post->title]);
        $post->delete();
    }

    public function sendMessage(User $user, Club $club, string $body): ClubMessage
    {
        abort_unless($this->canEnter($user, $club), 403);
        $body = trim($body);
        $this->ensure($body !== '' && mb_strlen($body) <= 1000, 'Pesan 1–1000 karakter.');

        return ClubMessage::create(['club_id' => $club->id, 'user_id' => $user->id, 'body' => $body]);
    }

    public function deleteMessage(User $user, ClubMessage $message): void
    {
        abort_unless($message->user_id === $user->id || $this->canLead($user, $message->club), 403);
        if ($message->user_id !== $user->id) {
            $this->audit($user, 'space.message_removed', $message, ['author' => $message->user_id]);
        }
        $message->delete();
    }

    /**
     * @param  array{title: string, description: string, points: int, due_at?: ?string}  $data
     */
    public function createQuest(User $actor, Club $club, array $data): ClubQuest
    {
        abort_unless($this->canLead($actor, $club), 403);
        $due = filled($data['due_at'] ?? null) ? Wita::toUtc($data['due_at']) : null;
        $this->ensure($due === null || $due->isFuture(), 'Tenggat quest harus di masa depan.');
        $this->ensure($data['points'] >= 5 && $data['points'] <= 500, 'Poin quest 5–500.');

        return DB::transaction(function () use ($actor, $club, $data, $due) {
            $quest = ClubQuest::create([
                'club_id' => $club->id, 'created_by' => $actor->id, 'title' => $data['title'], 'description' => $data['description'],
                'points' => $data['points'], 'due_at' => $due, 'status' => 'open',
            ]);
            $this->audit($actor, 'space.quest_created', $quest, ['points' => $quest->points]);
            Notification::send($this->members($club, $actor), new BrudelNotice('Quest baru '.$club->name, $quest->title.' · '.$quest->points.' poin', '/ruang/'.$club->id.'?tab=quest'));

            return $quest;
        });
    }

    public function closeQuest(User $actor, ClubQuest $quest): void
    {
        abort_unless($this->canLead($actor, $quest->club), 403);
        $quest->update(['status' => 'closed']);
        $this->audit($actor, 'space.quest_closed', $quest);
    }

    /**
     * A member reports a quest as done; a rejected report may be sent again.
     */
    public function submit(User $student, ClubQuest $quest, string $note): QuestSubmission
    {
        $this->ensure($this->isMember($student, $quest->club), 'Quest hanya untuk anggota aktif '.$quest->club->name.'.');
        $this->ensure($quest->isOpen(), 'Quest sudah ditutup.');
        $this->ensure(mb_strlen(trim($note)) >= 10, 'Ceritakan bukti penyelesaian minimal 10 karakter.');

        return DB::transaction(function () use ($student, $quest, $note) {
            $submission = QuestSubmission::lockForUpdate()->firstOrNew(['club_quest_id' => $quest->id, 'user_id' => $student->id]);
            $this->ensure(! $submission->exists || $submission->status === 'rejected', 'Quest ini sudah kamu kirim.');
            $submission->fill(['note' => trim($note), 'status' => 'submitted', 'points_awarded' => 0, 'reviewed_by' => null, 'reviewed_at' => null, 'review_note' => null])->save();

            return $submission;
        });
    }

    public function review(User $actor, QuestSubmission $submission, string $decision, ?string $note): void
    {
        $quest = $submission->quest;
        abort_unless($this->canLead($actor, $quest->club), 403);
        abort_if($submission->user_id === $actor->id, 403, 'Tidak dapat menilai quest sendiri.');
        $this->ensure(in_array($decision, ['approved', 'rejected'], true), 'Keputusan tidak valid.');
        $this->ensure($decision === 'approved' || mb_strlen(trim((string) $note)) >= 5, 'Alasan penolakan minimal 5 karakter.');

        DB::transaction(function () use ($actor, $submission, $quest, $decision, $note) {
            $submission = QuestSubmission::lockForUpdate()->findOrFail($submission->id);
            $this->ensure($submission->status === 'submitted', 'Laporan quest ini sudah ditinjau.');
            $submission->update(['status' => $decision, 'points_awarded' => $decision === 'approved' ? $quest->points : 0,
                'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'review_note' => $note]);
            $this->audit($actor, 'space.quest_'.$decision, $submission, ['points' => $submission->points_awarded]);
            $submission->user->notify(new BrudelNotice(
                $decision === 'approved' ? 'Quest selesai: +'.$quest->points.' poin' : 'Quest perlu diperbaiki',
                $quest->title.($note ? ' · '.$note : ''),
                '/ruang/'.$quest->club_id.'?tab=quest',
            ));
        });
    }

    /**
     * Active members ranked by points (approved quests plus attendance at the organization's activities):
     * the top $limit rows, the viewer's own row, and the member count.
     *
     * @return array{rows: Collection<int, array{user: User, points: int, quests: int, attended: int, level: string, rank: int, id: int}>, me: ?array<string, mixed>, total: int}
     */
    public function leaderboard(Club $club, int $limit = 50, ?User $viewer = null): array
    {
        // Scores come from two grouped queries; user records are loaded only for the rows shown, so a HIMAJU
        // with thousands of members stays fast.
        $members = Membership::where('club_id', $club->id)->where('status', 'active')->orderBy('user_id')->pluck('user_id');
        $quests = QuestSubmission::where('status', 'approved')->whereHas('quest', fn ($q) => $q->where('club_id', $club->id))
            ->selectRaw('user_id, sum(points_awarded) as points, count(*) as total')->groupBy('user_id')->get()->keyBy('user_id');
        $attended = ActivityAttendance::whereHas('activity', fn ($q) => $q->where('club_id', $club->id))
            ->selectRaw('user_id, count(*) as total')->groupBy('user_id')->pluck('total', 'user_id');
        $scores = $members->map(fn (int $id) => [
            'id' => $id, 'points' => (int) ($quests[$id]->points ?? 0) + self::ATTENDANCE_POINTS * (int) ($attended[$id] ?? 0),
            'quests' => (int) ($quests[$id]->total ?? 0), 'attended' => (int) ($attended[$id] ?? 0),
        ])->sortBy([['points', 'desc'], ['id', 'asc']])->values()->map(fn (array $row, int $index) => $row + ['rank' => $index + 1]);
        $mine = $viewer ? $scores->firstWhere('id', $viewer->id) : null;
        $shown = $scores->take($limit);
        $users = User::whereIn('id', $shown->pluck('id')->push($mine['id'] ?? 0))->get()->keyBy('id');
        $row = fn (array $score) => ['user' => $users[$score['id']], 'level' => self::level($score['points'])] + $score;

        return [
            'rows' => $shown->map($row)->values(),
            'me' => $mine ? $row($mine) : null,
            'total' => $scores->count(),
        ];
    }

    public static function level(int $points): string
    {
        return collect(self::LEVELS)->filter(fn ($title, $minimum) => $points >= $minimum)->last();
    }

    /**
     * Progress toward the next level, for the progress bar in the member space.
     *
     * @return array{next: ?string, needed: int, percent: int}
     */
    public static function progress(int $points): array
    {
        $levels = collect(self::LEVELS);
        $floor = $levels->keys()->filter(fn ($minimum) => $points >= $minimum)->last();
        $next = $levels->keys()->first(fn ($minimum) => $minimum > $points);
        if ($next === null) {
            return ['next' => null, 'needed' => 0, 'percent' => 100];
        }

        return ['next' => $levels[$next], 'needed' => $next - $points, 'percent' => (int) round(100 * ($points - $floor) / ($next - $floor))];
    }

    /**
     * @return Collection<int, User>
     */
    private function members(Club $club, User $except): Collection
    {
        return User::whereIn('id', Membership::where('club_id', $club->id)->where('status', 'active')->pluck('user_id'))->whereKeyNot($except->id)->get();
    }
}
