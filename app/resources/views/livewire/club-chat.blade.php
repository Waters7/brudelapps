<div class="club-chat" wire:poll.4s>
    <div class="support-messages club-chat-messages" aria-live="polite">
        @forelse($messages as $entry)
        <div class="bubble {{ $entry->user_id === auth()->id() ? 'mine' : 'theirs' }}">
            @if($entry->user_id !== auth()->id())<strong class="bubble-author">{{ $entry->author->name }}</strong>@endif
            <p class="prewrap">{{ $entry->body }}</p>
            <small>{{ \App\Services\Wita::show($entry->created_at, 'd M H:i') }}@if($entry->user_id === auth()->id() || $canModerate) · <button type="button" class="text-link plain bubble-remove" wire:click="remove({{ $entry->id }})" wire:confirm="Hapus pesan ini?">hapus</button>@endif</small>
        </div>
        @empty
        <p class="muted support-empty">Belum ada obrolan. Sapa teman-teman organisasimu!</p>
        @endforelse
    </div>
    <form class="support-compose" wire:submit="send">
        <textarea wire:model="body" rows="2" maxlength="1000" required placeholder="Tulis pesan untuk semua anggota…" aria-label="Pesan obrolan"></textarea>
        <button class="button primary small">Kirim</button>
    </form>
    @error('body')<p class="field-error">{{ $message }}</p>@enderror
</div>
