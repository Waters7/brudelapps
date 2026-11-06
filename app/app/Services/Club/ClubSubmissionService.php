<?php

namespace App\Services\Club;

use App\Models\Club;
use App\Models\ClubSubmission;
use App\Models\Department;
use App\Models\User;
use App\Notifications\BrudelNotice;
use App\Services\DomainService;
use App\Services\PrivateFiles;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Proposals and accountability reports from officers. A submission goes to kemahasiswaan jurusan or direktorat, who
 * accept it, reject it, or send it back for revision; a revised document returns to the same reviewer as a new version.
 */
class ClubSubmissionService extends DomainService
{
    public const DECISIONS = ['accepted', 'revision', 'rejected'];

    public function __construct(private PrivateFiles $files) {}

    public function canSubmit(User $user, Club $club): bool
    {
        return $user->assigned('pengurus', $club->id);
    }

    public function canReview(User $user, ClubSubmission $submission): bool
    {
        return $user->isStudentAffairs()
            || ($submission->target === 'jurusan' && $user->scopeIds('jurusan', 'department_id')->contains($submission->department_id));
    }

    public function canView(User $user, ClubSubmission $submission): bool
    {
        return $this->canReview($user, $submission) || $user->coachesClub($submission->club_id);
    }

    /**
     * @param  array{type: string, title: string, summary?: ?string, target: string, department_id?: ?int}  $data
     */
    public function submit(User $actor, Club $club, array $data, UploadedFile $document): ClubSubmission
    {
        abort_unless($this->canSubmit($actor, $club), 403);
        $this->ensure(array_key_exists($data['type'], ClubSubmission::TYPES), 'Jenis dokumen tidak dikenal.');
        $department = $data['target'] === 'jurusan' ? Department::find($data['department_id'] ?? 0) : null;
        $this->ensure($data['target'] === 'direktorat' || $department !== null, 'Pilih tujuan pengajuan.');
        $this->ensure($club->category !== 'himaju' || $department === null || $department->id === $club->department_id, 'HIMAJU mengajukan ke jurusannya sendiri.');

        [$path, $disk] = $this->files->storeDocument($document, 'club-submissions');
        try {
            return DB::transaction(function () use ($actor, $club, $data, $department, $path, $disk) {
                $submission = ClubSubmission::create([
                    'club_id' => $club->id, 'type' => $data['type'], 'title' => trim($data['title']), 'summary' => $data['summary'] ?? null,
                    'target' => $data['target'], 'department_id' => $department?->id, 'file_path' => $path, 'file_disk' => $disk,
                    'submitted_by' => $actor->id, 'submitted_at' => now(),
                ]);
                $this->history($submission, null, 'submitted', $actor);
                $this->audit($actor, 'submission.submitted', $submission, ['type' => $data['type'], 'target' => $data['target']]);
                $this->notifyReviewers($submission, $submission->typeLabel().' baru dari '.$club->name);

                return $submission;
            });
        } catch (\Throwable $exception) {
            $this->files->delete($path, $disk);
            throw $exception;
        }
    }

    public function review(User $actor, ClubSubmission $submission, string $decision, ?string $note): void
    {
        abort_unless($this->canReview($actor, $submission), 403);
        abort_if($submission->submitted_by === $actor->id, 403, 'Tidak dapat meninjau pengajuan sendiri.');
        $this->ensure(in_array($decision, self::DECISIONS, true), 'Keputusan tidak dikenal.');
        $note = trim((string) $note);
        $this->ensure($decision === 'accepted' || mb_strlen($note) >= 5, 'Catatan minimal 5 karakter wajib diisi bila diminta perbaikan atau ditolak.');

        DB::transaction(function () use ($actor, $submission, $decision, $note) {
            $locked = ClubSubmission::lockForUpdate()->findOrFail($submission->id);
            $this->ensure($locked->status === 'submitted', 'Pengajuan ini sudah diputuskan.');
            $locked->update(['status' => $decision, 'review_note' => $note ?: null, 'reviewed_by' => $actor->id, 'reviewed_at' => now()]);
            $this->history($locked, 'submitted', $decision, $actor, $note ?: null);
            $this->audit($actor, 'submission.'.$decision, $locked);
            $locked->submitter->notify(new BrudelNotice($locked->typeLabel().': '.config('brudel.status_labels')[$decision], $locked->title, '/proposal/'.$locked->id));
        });
    }

    /**
     * Sends a corrected document back to the same reviewer. Only a submission returned for revision can come back.
     */
    public function resubmit(User $actor, ClubSubmission $submission, ?string $summary, UploadedFile $document): void
    {
        abort_unless($this->canSubmit($actor, $submission->club), 403);
        $this->ensure($submission->status === 'revision', 'Hanya pengajuan yang diminta perbaikan yang dapat dikirim ulang.');

        [$path, $disk] = $this->files->storeDocument($document, 'club-submissions');
        try {
            DB::transaction(function () use ($actor, $submission, $summary, $path, $disk) {
                $locked = ClubSubmission::lockForUpdate()->findOrFail($submission->id);
                $this->ensure($locked->status === 'revision', 'Pengajuan ini sudah dikirim ulang.');
                $locked->update([
                    'status' => 'submitted', 'version' => $locked->version + 1, 'file_path' => $path, 'file_disk' => $disk, 'summary' => $summary ?? $locked->summary,
                    'submitted_by' => $actor->id, 'submitted_at' => now(), 'reviewed_by' => null, 'reviewed_at' => null,
                ]);
                $this->history($locked, 'revision', 'submitted', $actor, 'Perbaikan versi '.$locked->version);
                $this->audit($actor, 'submission.resubmitted', $locked, ['version' => $locked->version]);
                $this->notifyReviewers($locked, 'Perbaikan '.$locked->typeLabel().' dari '.$locked->club->name);
            });
        } catch (\Throwable $exception) {
            $this->files->delete($path, $disk);
            throw $exception;
        }
    }

    private function notifyReviewers(ClubSubmission $submission, string $title): void
    {
        Notification::send($this->reviewers($submission), new BrudelNotice($title, $submission->title, '/proposal/'.$submission->id));
    }

    /**
     * @return Collection<int, User>
     */
    private function reviewers(ClubSubmission $submission): Collection
    {
        $holders = fn (string $role, ?int $department = null) => User::where('active', true)->whereHas('currentAssignments', fn ($q) => $q->where('role', $role)
            ->when($department, fn ($scope) => $scope->where('department_id', $department)))->get();
        $jurusan = $submission->target === 'jurusan' ? $holders('jurusan', $submission->department_id) : collect();

        return $jurusan->isNotEmpty() ? $jurusan : $holders('kemahasiswaan');
    }
}
