<?php

namespace App\Livewire;

use App\Models\SupportThread;
use App\Services\Support\SupportService;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Floating help chat (bottom right of every page) and the full /bantuan page. Users write to kemahasiswaan
 * direktorat or to the kemahasiswaan of their jurusan; direktorat sees every conversation and kemahasiswaan
 * jurusan those of its jurusan. Refreshed by polling, faster while the panel is open.
 */
class SupportChat extends Component
{
    #[Locked]
    public bool $page = false;

    public bool $open = false;

    public ?int $threadId = null;

    public bool $composing = false;

    public string $category = 'kendala';

    public string $recipient = 'direktorat';

    public string $subject = '';

    public string $body = '';

    public string $filter = 'open';

    public function mount(bool $page = false): void
    {
        $this->page = $page;
        $this->open = $page;
    }

    public function toggle(): void
    {
        $this->open = ! $this->open;
    }

    public function select(int $id, SupportService $support): void
    {
        $thread = SupportThread::findOrFail($id);
        abort_unless($support->canView(auth()->user(), $thread), 403);
        $this->threadId = $thread->id;
        $this->composing = false;
        $this->resetErrorBag();
    }

    public function back(): void
    {
        $this->threadId = null;
        $this->composing = false;
    }

    public function compose(): void
    {
        $this->threadId = null;
        $this->composing = true;
    }

    public function start(SupportService $support): void
    {
        $data = $this->validate([
            'category' => 'required|in:'.implode(',', array_keys(SupportThread::CATEGORIES)),
            'recipient' => 'required|in:'.implode(',', array_keys($support->recipients(auth()->user()))),
            'subject' => 'required|string|min:3|max:150',
            'body' => 'required|string|min:5|max:2000',
        ]);
        $this->throttle();
        $thread = $support->start(auth()->user(), $data['category'], $data['subject'], $data['body'], $data['recipient']);
        $this->reset('subject', 'body', 'composing', 'recipient');
        $this->threadId = $thread->id;
    }

    public function send(SupportService $support): void
    {
        $this->validate(['body' => 'required|string|max:2000']);
        $this->throttle();
        $support->reply(auth()->user(), SupportThread::findOrFail($this->threadId), $this->body);
        $this->reset('body');
    }

    public function setStatus(string $status, SupportService $support): void
    {
        $support->setStatus(auth()->user(), SupportThread::findOrFail($this->threadId), $status);
    }

    private function throttle(): void
    {
        if (! RateLimiter::attempt('support-chat|'.auth()->id(), 20, fn () => true, 60)) {
            throw ValidationException::withMessages(['body' => 'Terlalu banyak pesan. Coba lagi sebentar lagi.']);
        }
    }

    public function render(SupportService $support): View
    {
        $user = auth()->user();
        $admin = $user->isStudentAffairs();
        $handled = $support->handledThreads($user);
        $thread = null;
        if ($this->open && $this->threadId) {
            $thread = SupportThread::with('messages.author', 'user', 'department')->find($this->threadId);
            if ($thread && $support->canView($user, $thread)) {
                $support->markRead($user, $thread);
            } else {
                [$thread, $this->threadId] = [null, null];
            }
        }
        $threads = $this->open && ! $thread && ! $this->composing
            ? SupportThread::with('user', 'department')
                ->when(! $admin, fn ($q) => $q->where(fn ($mine) => $mine->where('user_id', $user->id)
                    ->when($handled, fn ($w) => $w->orWhereIn('id', (clone $handled)->select('id')))))
                ->when($handled && $this->filter !== 'all', fn ($q) => $q->where('status', $this->filter))
                ->orderByDesc('last_message_at')->limit(50)->get()
            : collect();

        return view('livewire.support-chat', [
            'admin' => $admin,
            'inbox' => $handled !== null,
            'answering' => $thread && $thread->user_id !== $user->id && $support->handles($user, $thread),
            'recipients' => $support->recipients($user),
            'thread' => $thread,
            'threads' => $threads,
            'unread' => $support->unreadCount($user),
        ]);
    }
}
