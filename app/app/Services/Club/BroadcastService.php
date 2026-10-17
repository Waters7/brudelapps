<?php

namespace App\Services\Club;

use App\Models\Assignment;
use App\Models\Broadcast;
use App\Models\Club;
use App\Models\Membership;
use App\Models\User;
use App\Notifications\BrudelNotice;
use App\Services\DomainService;
use App\Services\Wita;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Announcements of kemahasiswaan direktorat to many organizations at once. Every active member and coach of the
 * chosen organizations is notified once, however many of those organizations they belong to.
 */
class BroadcastService extends DomainService
{
    /**
     * A public announcement is also shown to everyone on the sign-in page.
     *
     * @param  array{title: string, body: string, audience: string, clubs?: list<int>, expires_at?: ?string, is_public?: ?bool}  $data
     */
    public function publish(User $actor, array $data): Broadcast
    {
        abort_unless($actor->isStudentAffairs(), 403);
        $this->ensure(mb_strlen(trim($data['title'])) >= 3 && mb_strlen(trim($data['body'])) >= 5, 'Judul minimal 3 dan isi minimal 5 karakter.');
        $this->ensure(array_key_exists($data['audience'], Broadcast::AUDIENCES), 'Sasaran pengumuman tidak dikenal.');
        $clubs = $this->audience($data['audience'], $data['clubs'] ?? []);
        $this->ensure($clubs->isNotEmpty(), 'Pilih minimal satu organisasi sasaran.');
        $expires = Wita::toUtc($data['expires_at'] ?? null);
        $this->ensure($expires === null || $expires->isFuture(), 'Batas tampil harus di masa depan.');

        return DB::transaction(function () use ($actor, $data, $clubs, $expires) {
            $broadcast = Broadcast::create(['user_id' => $actor->id, 'title' => trim($data['title']), 'body' => trim($data['body']),
                'audience' => $data['audience'], 'is_public' => (bool) ($data['is_public'] ?? false), 'expires_at' => $expires]);
            $broadcast->clubs()->attach($clubs);
            $this->audit($actor, 'broadcast.published', $broadcast, ['audience' => $data['audience'], 'clubs' => $clubs->count()]);
            $this->recipients($clubs, $actor)->chunk(500)->each(fn (Collection $ids) => Notification::send(User::whereIn('id', $ids)->get(),
                new BrudelNotice('Pengumuman Kemahasiswaan Direktorat', $broadcast->title, '/pengumuman/'.$broadcast->id)));

            return $broadcast;
        });
    }

    public function delete(User $actor, Broadcast $broadcast): void
    {
        abort_unless($actor->isStudentAffairs(), 403);
        $this->audit($actor, 'broadcast.deleted', $broadcast, ['title' => $broadcast->title]);
        $broadcast->delete();
    }

    /**
     * Kemahasiswaan direktorat, and the active members and coaches of an addressed organization, may read it.
     */
    public function canRead(User $user, Broadcast $broadcast): bool
    {
        if ($user->isStudentAffairs()) {
            return true;
        }
        $clubs = $broadcast->clubs()->pluck('clubs.id');

        return Membership::where('user_id', $user->id)->where('status', 'active')->whereIn('club_id', $clubs)->exists()
            || $clubs->contains(fn ($club) => $user->coachesClub($club));
    }

    /**
     * Announcements for the organizations a user belongs to or coaches, newest first.
     *
     * @return Collection<int, Broadcast>
     */
    public function forUser(User $user, int $limit = 3): Collection
    {
        $clubs = Membership::where('user_id', $user->id)->where('status', 'active')->pluck('club_id')
            ->merge(Club::coachedBy($user)->pluck('id'))->unique();

        return Broadcast::with('author')->shown()->when(! $user->isStudentAffairs(), fn ($q) => $q->forClubs($clubs))
            ->latest('id')->limit($limit)->get();
    }

    /**
     * @param  list<int>  $chosen
     * @return Collection<int, int>
     */
    private function audience(string $audience, array $chosen): Collection
    {
        return Club::where('active', true)
            ->when($audience === 'ukm', fn ($q) => $q->where('category', 'ukm'))
            ->when($audience === 'himaju', fn ($q) => $q->where('category', 'himaju'))
            ->when($audience === 'lembaga', fn ($q) => $q->where('category', 'lembaga'))
            ->when($audience === 'pilihan', fn ($q) => $q->whereIn('id', $chosen))
            ->pluck('id');
    }

    /**
     * Distinct user ids of the active members and coaches of the clubs, without the author.
     *
     * @param  Collection<int, int>  $clubs
     * @return Collection<int, int>
     */
    private function recipients(Collection $clubs, User $actor): Collection
    {
        return Membership::whereIn('club_id', $clubs)->where('status', 'active')->pluck('user_id')
            ->merge(Assignment::whereIn('club_id', $clubs)->whereIn('role', ['pengurus', 'pembina'])->where('starts_at', '<=', now())->where('ends_at', '>', now())->pluck('user_id'))
            ->unique()->reject(fn ($id) => $id === $actor->id)->values();
    }
}
