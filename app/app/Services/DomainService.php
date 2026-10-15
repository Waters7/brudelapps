<?php

namespace App\Services;

use App\Models\StatusHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Shared rules for module services: business-rule failures, audit trail and status history.
 */
abstract class DomainService
{
    public function ensure(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['action' => $message]);
        }
    }

    /**
     * Record an important action inside the caller's transaction.
     *
     * @param  array<string, mixed>  $details
     */
    public function audit(User $actor, string $action, Model $subject, array $details = []): void
    {
        DB::table('audit_logs')->insert([
            'user_id' => $actor->id,
            'action' => $action,
            'subject_type' => $subject->getTable(),
            'subject_id' => $subject->getKey(),
            'details' => json_encode($details),
            'created_at' => now(),
        ]);
    }

    /**
     * Record a status transition that the affected student can see.
     */
    public function history(Model $subject, ?string $from, string $to, ?User $actor, ?string $reason = null): void
    {
        StatusHistory::create([
            'subject_type' => $subject->getTable(),
            'subject_id' => $subject->getKey(),
            'from_status' => $from,
            'to_status' => $to,
            'reason' => $reason,
            'user_id' => $actor?->id,
            'created_at' => now(),
        ]);
    }

    public function label(string $status): string
    {
        return config('brudel.status_labels')[$status] ?? $status;
    }

    public function requireReason(string $decision, ?string $reason, array $withoutReason = ['accepted', 'approved']): void
    {
        $this->ensure(in_array($decision, $withoutReason, true) || mb_strlen(trim($reason ?? '')) >= 5, 'Alasan keputusan minimal 5 karakter.');
    }
}
