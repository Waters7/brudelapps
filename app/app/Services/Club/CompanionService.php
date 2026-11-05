<?php

namespace App\Services\Club;

use App\Models\ClubActivity;
use App\Models\Companion;
use App\Models\Competition;
use App\Models\User;
use App\Services\DomainService;
use Illuminate\Database\Eloquent\Model;

/**
 * Dosen and tendik accompanying an activity or a competition, recorded by whoever runs it.
 */
class CompanionService extends DomainService
{
    public const MAX_PER_SUBJECT = 10;

    public function __construct(private ActivityService $activities) {}

    public function canManage(User $user, Model $subject): bool
    {
        return match (true) {
            $subject instanceof ClubActivity => $this->activities->canManage($user, $subject),
            $subject instanceof Competition => $user->managesCompetition($subject),
            default => false,
        };
    }

    /**
     * @param  array{name: string, kind: string, identifier?: ?string}  $data
     */
    public function add(User $actor, ClubActivity|Competition $subject, array $data): Companion
    {
        abort_unless($this->canManage($actor, $subject), 403);
        $this->ensure(array_key_exists($data['kind'], Companion::KINDS), 'Pilih dosen atau tendik.');
        $this->ensure($subject->companions()->count() < self::MAX_PER_SUBJECT, 'Maksimal '.self::MAX_PER_SUBJECT.' pendamping.');
        $name = trim((string) preg_replace('/\s+/', ' ', $data['name']));
        $this->ensure(mb_strlen($name) >= 3, 'Nama pendamping minimal 3 karakter.');
        $this->ensure(! $subject->companions()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->exists(), $name.' sudah tercatat sebagai pendamping.');

        $companion = $subject->companions()->create(['name' => $name, 'kind' => $data['kind'], 'identifier' => filled($data['identifier'] ?? null) ? trim($data['identifier']) : null, 'created_by' => $actor->id]);
        $this->audit($actor, 'companion.created', $companion, ['name' => $name, 'kind' => $data['kind']]);

        return $companion;
    }

    public function remove(User $actor, Companion $companion): void
    {
        abort_unless($this->canManage($actor, $companion->subject), 403);
        $this->audit($actor, 'companion.deleted', $companion, ['name' => $companion->name]);
        $companion->delete();
    }
}
