<?php

namespace App\Services\Account;

use App\Models\Club;
use App\Models\Department;
use App\Models\User;
use App\Services\DomainService;
use App\Services\Wita;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Opens dosen and tendik accounts and gives them their first task in one step. A task is a role with its scope:
 * pendamping of an organization, kemahasiswaan of a jurusan, or a pimpinan position. Superadmin and the per-event
 * roles stay in Penugasan Peran, where they are granted one at a time.
 */
class EmployeeService extends DomainService
{
    public function __construct(private AccountService $accounts, private AssignmentService $assignments) {}

    /**
     * @return array<string, array<string, string>> Task labels by group, keyed "role:scope".
     */
    public function tasks(): array
    {
        return [
            'Pendamping organisasi' => Club::where('active', true)->ordered()->get()->mapWithKeys(fn (Club $club) => ['pembina:'.$club->id => $club->name])->all(),
            'Kemahasiswaan Jurusan' => Department::orderBy('id')->get()->mapWithKeys(fn (Department $department) => ['jurusan:'.$department->id => $department->name])->all(),
            'Pimpinan' => collect(config('brudel.pimpinan_positions'))->mapWithKeys(fn (string $position) => ['pimpinan:'.$position => $position])->all(),
        ];
    }

    /**
     * Activates whoever is still closed and, when a task is chosen, assigns it to everyone selected. Someone who
     * already holds the task is reported, not treated as an error, so one person never blocks the rest.
     *
     * @param  list<int>  $ids
     * @return array{activated: int, assigned: int, already: list<string>}
     */
    public function apply(User $actor, array $ids, ?string $task, ?string $endsAt): array
    {
        abort_unless($actor->isStudentAffairs(), 403);
        $people = User::whereIn('id', $ids)->whereKeyNot($actor->id)->has('employee')->get();
        $result = ['activated' => 0, 'assigned' => 0, 'already' => []];

        DB::transaction(function () use ($actor, $people, $task, $endsAt, &$result) {
            $result['activated'] = $this->accounts->activateEmployees($actor, $people->pluck('id')->all());
            if (! $task) {
                return;
            }
            [$role, $scope] = explode(':', $task, 2);
            foreach ($people as $person) {
                try {
                    $this->assignments->grant($actor, [
                        'user' => $person->username, 'role' => $role, 'scope_id' => $role === 'pimpinan' ? null : (int) $scope,
                        'position' => $role === 'pimpinan' ? $scope : null,
                        'starts_at' => Wita::input(now()), 'ends_at' => (string) $endsAt,
                    ]);
                    $result['assigned']++;
                } catch (ValidationException) {
                    $result['already'][] = $person->name;
                }
            }
        });

        return $result;
    }
}
