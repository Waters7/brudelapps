<?php

namespace App\Services\Support;

use App\Models\SupportMessage;
use App\Models\SupportThread;
use App\Models\User;
use App\Notifications\BrudelNotice;
use App\Services\DomainService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Help chat: any signed-in user reports a problem or requests a feature to kemahasiswaan direktorat or to the
 * kemahasiswaan of their own jurusan. Direktorat sees every conversation, kemahasiswaan jurusan those addressed
 * to its jurusan, users only their own. Read markers drive the unread badge on the floating button.
 */
class SupportService extends DomainService
{
    public const MAX_OPEN_THREADS = 10;

    /**
     * Answers the conversation on behalf of kemahasiswaan.
     */
    public function handles(User $user, SupportThread $thread): bool
    {
        return $user->isStudentAffairs() || ($thread->department_id !== null && $user->assigned('jurusan', department: $thread->department_id));
    }

    public function canView(User $user, SupportThread $thread): bool
    {
        return $thread->user_id === $user->id || $this->handles($user, $thread);
    }

    /**
     * Conversations the user answers for kemahasiswaan; null when the user answers none.
     *
     * @return Builder<SupportThread>|null
     */
    public function handledThreads(User $user): ?Builder
    {
        if ($user->isStudentAffairs()) {
            return SupportThread::query();
        }
        $departments = $user->scopeIds('jurusan', 'department_id');

        return $departments->isEmpty() ? null : SupportThread::whereIn('department_id', $departments);
    }

    /**
     * Where the user can send a new conversation: always direktorat, and the kemahasiswaan of the user's jurusan.
     *
     * @return array<string, string>
     */
    public function recipients(User $user): array
    {
        $department = $user->program?->department;

        return ['direktorat' => 'Kemahasiswaan Direktorat'] + ($department ? ['jurusan' => 'Kemahasiswaan Jurusan '.$department->name] : []);
    }

    public function start(User $user, string $category, string $subject, string $body, string $recipient = 'direktorat'): SupportThread
    {
        $this->ensure(array_key_exists($category, SupportThread::CATEGORIES), 'Jenis pesan tidak valid.');
        $this->ensure(array_key_exists($recipient, $this->recipients($user)), 'Tujuan pesan tidak valid.');
        $this->ensure(mb_strlen(trim($subject)) >= 3, 'Judul minimal 3 karakter.');
        $this->ensure(mb_strlen(trim($body)) >= 5, 'Pesan minimal 5 karakter.');
        $this->ensure(SupportThread::where('user_id', $user->id)->where('status', 'open')->count() < self::MAX_OPEN_THREADS,
            'Masih ada '.self::MAX_OPEN_THREADS.' percakapan terbuka. Lanjutkan percakapan yang ada.');
        $department = $recipient === 'jurusan' ? $user->program->department_id : null;

        return DB::transaction(function () use ($user, $category, $subject, $body, $department) {
            $thread = SupportThread::create([
                'user_id' => $user->id, 'department_id' => $department, 'category' => $category, 'subject' => trim($subject), 'status' => 'open', 'last_message_at' => now(),
            ]);
            $message = $thread->messages()->create(['user_id' => $user->id, 'from_admin' => false, 'body' => trim($body)]);
            $thread->update(['last_message_id' => $message->id, 'user_read_id' => $message->id]);
            $this->audit($user, 'support.opened', $thread, ['category' => $category, 'department_id' => $department]);
            Notification::send($this->answerers($department)->reject(fn (User $answerer) => $answerer->id === $user->id),
                new BrudelNotice($thread->categoryLabel().' dari '.$user->name, $thread->subject, '/bantuan'));

            return $thread;
        });
    }

    public function reply(User $user, SupportThread $thread, string $body): SupportMessage
    {
        abort_unless($this->canView($user, $thread), 403);
        $body = trim($body);
        $this->ensure($body !== '' && mb_strlen($body) <= 2000, 'Pesan 1–2000 karakter.');
        $fromAdmin = $thread->user_id !== $user->id && $this->handles($user, $thread);

        return DB::transaction(function () use ($user, $thread, $body, $fromAdmin) {
            $message = $thread->messages()->create(['user_id' => $user->id, 'from_admin' => $fromAdmin, 'body' => $body]);
            $thread->update(['last_message_at' => now(), 'last_message_id' => $message->id, 'status' => 'open', $fromAdmin ? 'admin_read_id' : 'user_read_id' => $message->id]);
            if ($fromAdmin) {
                $thread->user->notify(new BrudelNotice('Balasan '.$thread->recipientLabel(), $thread->subject, '/bantuan'));
            }

            return $message;
        });
    }

    public function markRead(User $user, SupportThread $thread): void
    {
        abort_unless($this->canView($user, $thread), 403);
        $column = $thread->user_id === $user->id ? 'user_read_id' : 'admin_read_id';
        if ($thread->{$column} === null || $thread->last_message_id > $thread->{$column}) {
            $thread->forceFill([$column => $thread->last_message_id])->saveQuietly();
        }
    }

    public function setStatus(User $admin, SupportThread $thread, string $status): void
    {
        abort_unless($this->handles($admin, $thread), 403);
        $this->ensure(in_array($status, ['open', 'closed'], true), 'Status tidak valid.');
        $thread->update(['status' => $status]);
        $this->audit($admin, 'support.'.$status, $thread);
    }

    /**
     * Conversations with a message the user has not read: their own, plus those they answer for kemahasiswaan.
     */
    public function unreadCount(User $user): int
    {
        $unread = fn (string $column) => fn ($q) => $q->whereNull($column)->orWhereColumn('last_message_id', '>', $column);
        $own = SupportThread::where('user_id', $user->id)->where($unread('user_read_id'))->count();
        $handled = $this->handledThreads($user)?->where('user_id', '!=', $user->id)->where($unread('admin_read_id'))->count() ?? 0;

        return $own + $handled;
    }

    /**
     * Who is told about a new conversation: the kemahasiswaan of the jurusan, or direktorat when the jurusan has
     * nobody assigned yet (direktorat sees every conversation anyway).
     *
     * @return Collection<int, User>
     */
    private function answerers(?int $department): Collection
    {
        $jurusan = $department
            ? User::whereHas('currentAssignments', fn ($q) => $q->where('role', 'jurusan')->where('department_id', $department))->get()
            : collect();

        return $jurusan->isNotEmpty() ? $jurusan : User::whereHas('currentAssignments', fn ($q) => $q->where('role', 'kemahasiswaan'))->get();
    }
}
