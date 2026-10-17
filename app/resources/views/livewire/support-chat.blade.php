<div class="support-chat {{ $page ? 'is-page' : '' }}" @if($open) wire:poll.4s @else wire:poll.30s @endif>
    @unless($page)
    <button type="button" class="support-fab" wire:click="toggle" aria-expanded="{{ $open ? 'true' : 'false' }}" aria-label="Bantuan: hubungi kemahasiswaan, laporkan kendala, atau usulkan fitur">
        <x-icon :name="$open ? 'close' : 'chat'"/>@if($unread && ! $open)<span class="support-unread">{{ min($unread, 99) }}</span>@endif
    </button>
    @endunless
    @if($open)
    <section class="support-panel" aria-label="Bantuan BRUDEL">
        <header class="support-head">
            @if($thread || $composing)<button type="button" class="icon-button" wire:click="back" aria-label="Kembali ke daftar">←</button>@endif
            <div><strong>{{ $thread ? $thread->subject : ($composing ? 'Pesan baru' : ($inbox ? 'Kotak masuk bantuan' : 'Bantuan BRUDEL')) }}</strong>
                <small>@if($thread){{ $thread->categoryLabel() }} · {{ $answering ? $thread->user->name : $thread->recipientLabel() }}@elseif($admin)Semua percakapan · pesan tampil langsung @elseif($inbox)Pesan untuk jurusan Anda dan pesan Anda @else Kemahasiswaan menjawab di sini · pesan tampil langsung @endif</small></div>
            @unless($page)<button type="button" class="icon-button" wire:click="toggle" aria-label="Tutup bantuan"><x-icon name="close"/></button>@endunless
        </header>
        @if($thread)
            <div class="support-messages" aria-live="polite">
                @foreach($thread->messages->reverse() as $entry)
                <div class="bubble {{ $entry->user_id === auth()->id() ? 'mine' : 'theirs' }}"><p class="prewrap">{{ $entry->body }}</p><small>{{ $entry->from_admin ? ($thread->department_id ? 'Kemahasiswaan Jurusan' : 'Kemahasiswaan Direktorat').' · '.$entry->author->name : $entry->author->name }} · {{ \App\Services\Wita::show($entry->created_at, 'd M H:i') }}</small></div>
                @endforeach
            </div>
            @if($answering)
            <div class="support-tools">@if($thread->status === 'open')<button type="button" class="text-link plain" wire:click="setStatus('closed')">Tandai selesai</button>@else<span class="chip">Selesai</span><button type="button" class="text-link plain" wire:click="setStatus('open')">Buka kembali</button>@endif</div>
            @endif
            <form class="support-compose" wire:submit="send">
                <textarea wire:model="body" rows="2" maxlength="2000" required placeholder="Tulis pesan…" aria-label="Pesan"></textarea>
                <button class="button primary small">Kirim</button>
            </form>
            @error('body')<p class="field-error">{{ $message }}</p>@enderror
            @error('action')<p class="field-error">{{ $message }}</p>@enderror
        @elseif($composing)
            <form class="support-new" wire:submit="start">
                @if(count($recipients) > 1)
                <label>Tujuan<select wire:model.live="recipient">@foreach($recipients as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></label>
                @endif
                <label>Jenis pesan<select wire:model="category">@foreach(\App\Models\SupportThread::CATEGORIES as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></label>
                <label>Judul<input wire:model="subject" maxlength="150" required placeholder="Mis. Tidak bisa unggah bukti bayar"></label>
                <label>Pesan<textarea wire:model="body" rows="5" maxlength="2000" required placeholder="Ceritakan halaman, langkah, dan pesan error yang muncul, atau hal yang ingin ditanyakan."></textarea></label>
                @foreach(['recipient', 'category', 'subject', 'body', 'action'] as $field)@error($field)<p class="field-error">{{ $message }}</p>@enderror @endforeach
                <button class="button primary full">Kirim ke {{ $recipients[$recipient] ?? 'Kemahasiswaan Direktorat' }}</button>
            </form>
        @else
            <div class="support-list">
                @if($inbox)
                <div class="filter-tabs" role="group" aria-label="Filter percakapan">@foreach(['open' => 'Terbuka', 'closed' => 'Selesai', 'all' => 'Semua'] as $key => $label)<button type="button" class="filter-tab {{ $filter === $key ? 'selected' : '' }}" wire:click="$set('filter', '{{ $key }}')">{{ $label }}</button>@endforeach</div>
                @endif
                @unless($admin)
                <button type="button" class="button primary full" wire:click="compose">+ Hubungi kemahasiswaan</button>
                @endunless
                @forelse($threads as $item)
                @php($mine = $item->user_id === auth()->id())
                <button type="button" class="support-item" wire:click="select({{ $item->id }})">
                    <span><strong>{{ $item->subject }}</strong><small>{{ $item->categoryLabel() }} · {{ $mine ? 'ke '.$item->recipientLabel() : $item->user->name.($admin && $item->department_id ? ' · '.$item->recipientLabel() : '') }} · {{ \App\Services\Wita::show($item->last_message_at, 'd M H:i') }}</small></span>
                    @if($item->unreadFor(! $mine))<span class="support-dot" title="Belum dibaca"></span>@endif
                    @if($item->status === 'closed')<span class="chip">Selesai</span>@endif
                </button>
                @empty
                <p class="muted support-empty">{{ $inbox ? 'Belum ada percakapan pada filter ini.' : 'Belum ada percakapan. Tanyakan sesuatu, laporkan kendala, atau usulkan fitur; kemahasiswaan akan membalas di sini.' }}</p>
                @endforelse
            </div>
        @endif
    </section>
    @endif
</div>
