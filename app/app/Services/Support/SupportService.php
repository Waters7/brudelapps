<?php

namespace App\Services\Support;

use App\Models\SupportMessage;
use App\Models\SupportThread;
use App\Models\User;
use App\Notifications\BrudelNotice;
use App\Services\DomainService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Help chat: any signed-in user reports a problem or requests a feature, kemahasiswaan direktorat answers.
 * Users see only their own conversations; read markers drive the unread badge on the floating button.
 */
class SupportService extends DomainService
{
    public const MAX_OPEN_THREADS = 10;

    public function canView(User $user, SupportThread $thread): bool
    {
        return $thread->user_id === $user->id || $user->isStudentAffairs();
    }

    public function start(User $user, string $category, string $subject, string $body): SupportThread
    {
        $this->ensure(array_key_exists($category, SupportThread::CATEGORIES), 'Jenis pesan tidak valid.');
        $this->ensure(mb_strlen(trim($subject)) >= 3, 'Judul minimal 3 karakter.');
        $this->ensure(mb_strlen(trim($body)) >= 5, 'Pesan minimal 5 karakter.');
        $this->ensure(SupportThread::where('user_id', $user->id)->where('status', 'open')->count() < self::MAX_OPEN_THREADS,
            'Masih ada '.self::MAX_OPEN_THREADS.' percakapan terbuka. Lanjutkan percakapan yang ada.');

        return DB::transaction(function () use ($user, $category, $subject, $body) {
            $thread = SupportThread::create([
                'user_id' => $user->id, 'category' => $category, 'subject' => trim($subject), 'status' => 'open', 'last_message_at' => now(),
            ]);
            $message = $thread->messages()->create(['user_id' => $user->id, 'from_admin' => false, 'body' => trim($body)]);
            $thread->update(['last_message_id' => $message->id, 'user_read_id' => $message->id]);
            $this->audit($user, 'support.opened', $thread, ['category' => $category]);
            $admins = User::whereHas('currentAssignments', fn ($q) => $q->where('role', 'kemahasiswaan'))->whereKeyNot($user->id)->get();
            Notification::send($admins, new BrudelNotice($thread->categoryLabel().' dari '.$user->name, $thread->subject, '/bantuan'));

            return $thread;
        });
    }

    public function reply(User $user, SupportThread $thread, string $body): SupportMessage
    {
        abort_unless($this->canView($user, $thread), 403);
        $body = trim($body);
        $this->ensure($body !== '' && mb_strlen($body) <= 2000, 'Pesan 1–2000 karakter.');
        $fromAdmin = $user->isStudentAffairs() && $thread->user_id !== $user->id;

        return DB::transaction(function () use ($user, $thread, $body, $fromAdmin) {
            $message = $thread->messages()->create(['user_id' => $user->id, 'from_admin' => $fromAdmin, 'body' => $body]);
            $thread->update(['last_message_at' => now(), 'last_message_id' => $message->id, 'status' => 'open', $fromAdmin ? 'admin_read_id' : 'user_read_id' => $message->id]);
            if ($fromAdmin) {
                $thread->user->notify(new BrudelNotice('Balasan Kemahasiswaan Direktorat', $thread->subject, '/bantuan'));
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
        abort_unless($admin->isStudentAffairs(), 403);
        $this->ensure(in_array($status, ['open', 'closed'], true), 'Status tidak valid.');
        $thread->update(['status' => $status]);
        $this->audit($admin, 'support.'.$status, $thread);
    }

    /**
     * Conversations with a message the user has not read: every conversation for kemahasiswaan direktorat,
     * otherwise the user's own.
     */
    public function unreadCount(User $user): int
    {
        $admin = $user->isStudentAffairs();
        $column = $admin ? 'admin_read_id' : 'user_read_id';

        return SupportThread::when(! $admin, fn ($q) => $q->where('user_id', $user->id))
            ->where(fn ($q) => $q->whereNull($column)->orWhereColumn('last_message_id', '>', $column))->count();
    }
}
