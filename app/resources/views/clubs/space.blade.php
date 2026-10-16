<x-layout :title="'Ruang '.$club->name">
<a class="back-link" href="/ukm/{{ $club->id }}">← Profil {{ $club->name }}</a>
<div class="space-hero {{ $club->tone() }}">
    @if($club->banner_path)<img src="/banner/{{ $club->id }}?v={{ $club->updated_at?->timestamp }}" alt="">@endif
    <div class="space-hero-body">
        <span class="eyebrow">RUANG {{ strtoupper($club->categoryShort()) }}</span>
        <h1>{{ $club->name }}</h1>
        <p>{{ $members->count() }} anggota aktif · {{ $quests->where('status', 'open')->count() }} quest terbuka</p>
    </div>
    @if($me)
    <div class="space-me"><span class="space-level"><x-icon name="star"/>{{ $me['level'] }}</span><strong>{{ $me['points'] }} poin</strong><small>Peringkat {{ $me['rank'] }} dari {{ $leaderboard->count() }}</small></div>
    @endif
</div>

<nav class="space-tabs" aria-label="Bagian ruang organisasi">
    @foreach(\App\Http\Controllers\Club\SpaceController::TABS as $key => $label)
    <a href="/ruang/{{ $club->id }}?tab={{ $key }}" class="{{ $tab === $key ? 'selected' : '' }}" @if($tab === $key) aria-current="page" @endif>{{ $label }}@if($key === 'quest' && $canLead && $reviews->isNotEmpty())<span class="count-pill">{{ $reviews->count() }}</span>@endif</a>
    @endforeach
</nav>

@if($tab === 'beranda')
<div class="detail-columns">
<div>
    @if($canLead)
    <details class="panel collapsible" @if($posts->isEmpty()) open @endif><summary><strong><x-icon name="megaphone"/> Buat pengumuman</strong></summary>
        <form method="post" action="/ruang/{{ $club->id }}/pengumuman">@csrf
            <label>Judul<input name="title" required maxlength="150"></label>
            <label>Isi pengumuman<textarea name="body" required maxlength="5000" rows="4"></textarea></label>
            <label class="checkbox-label"><input type="hidden" name="pinned" value="0"><input type="checkbox" name="pinned" value="1"><span>Sematkan di atas</span></label>
            <button class="button primary">Terbitkan & beri tahu anggota</button>
        </form>
    </details>
    @endif
    @forelse($posts as $post)
    <article class="panel space-post {{ $post->pinned ? 'is-pinned' : '' }}">
        <div class="section-heading"><div>@if($post->pinned)<span class="badge status-accepted">Disematkan</span>@endif<h2>{{ $post->title }}</h2><small class="muted">{{ $post->author->name }} · {{ \App\Services\Wita::show($post->created_at, 'd M Y H:i') }} WITA</small></div>
        @if($canLead)<div class="inline-actions"><form method="post" action="/ruang/pengumuman/{{ $post->id }}/sematkan">@csrf<button class="button outline small">{{ $post->pinned ? 'Lepas sematan' : 'Sematkan' }}</button></form><form method="post" action="/ruang/pengumuman/{{ $post->id }}/hapus" onsubmit="return confirm('Hapus pengumuman ini?')">@csrf<button class="button outline small danger">Hapus</button></form></div>@endif</div>
        <p class="prewrap">{{ $post->body }}</p>
    </article>
    @empty
    <div class="panel empty"><x-icon name="megaphone"/><p>Belum ada pengumuman.</p></div>
    @endforelse
</div>
<div>
    <section class="panel"><h2>Kegiatan mendatang</h2>
        <ul class="plain-list">@forelse($activities as $activity)<li><a class="text-link" href="/kegiatan/{{ $activity->id }}">{{ $activity->title }}</a> <span class="muted">· {{ $activity->typeLabel() }} · {{ \App\Services\Wita::show($activity->starts_at, 'd M H:i') }} WITA · hadir +{{ \App\Services\Club\SpaceService::ATTENDANCE_POINTS }} poin</span></li>@empty<li class="muted">Belum ada kegiatan terjadwal.</li>@endforelse</ul>
        @if($canLead)<a class="text-link" href="/kegiatan">Buat kegiatan →</a>@endif
    </section>
    <section class="panel"><h2>Papan atas</h2>
        <ol class="leader-mini">@foreach($leaderboard->take(5) as $row)<li><x-avatar :user="$row['user']"/><span>{{ $row['user']->name }}<small>{{ $row['level'] }}</small></span><strong>{{ $row['points'] }}</strong></li>@endforeach</ol>
        <a class="text-link" href="/ruang/{{ $club->id }}?tab=peringkat">Lihat peringkat lengkap →</a>
    </section>
</div>
</div>

@elseif($tab === 'obrolan')
<section class="panel space-chat-panel">
    <h2>Obrolan {{ $club->name }}</h2>
    <p class="muted">Pesan tampil langsung untuk seluruh anggota aktif dan pengurus. Jaga sopan santun; pengurus dapat menghapus pesan.</p>
    <livewire:club-chat :club-id="$club->id" />
</section>

@elseif($tab === 'quest')
@if($canLead)
<details class="panel collapsible" @if($quests->isEmpty()) open @endif><summary><strong><x-icon name="star"/> Buat quest baru</strong></summary>
    <form class="form-grid" method="post" action="/ruang/{{ $club->id }}/quest">@csrf
        <label class="span-2">Judul quest<input name="title" required maxlength="150" placeholder="Mis. Ajak 1 teman ikut latihan terbuka"></label>
        <label class="span-2">Cara menyelesaikan<textarea name="description" required maxlength="3000" rows="3"></textarea></label>
        <label>Poin<input type="number" name="points" min="5" max="500" value="20" required></label>
        <label>Tenggat (opsional, WITA)<input type="datetime-local" name="due_at"></label>
        <div class="span-2"><button class="button primary">Terbitkan quest</button></div>
    </form>
</details>
@if($reviews->isNotEmpty())
<section class="panel"><h2>Laporan quest menunggu tinjauan <span class="count-pill">{{ $reviews->count() }}</span></h2>
    @foreach($reviews as $submission)
    <div class="list-row"><div><strong>{{ $submission->user->name }} · {{ $submission->quest->title }}</strong><small class="prewrap">{{ $submission->note }}</small></div>
    @if($submission->user_id !== auth()->id())<form class="inline-form" method="post" action="/ruang/laporan-quest/{{ $submission->id }}">@csrf<input name="note" maxlength="1000" placeholder="Catatan (wajib bila ditolak)"><button class="button primary small" name="decision" value="approved">Setujui +{{ $submission->quest->points }}</button><button class="button outline small danger" name="decision" value="rejected">Kembalikan</button></form>@endif</div>
    @endforeach
</section>
@endif
@endif
<div class="catalog-grid">
@forelse($quests as $quest)
@php($mine = $quest->submissions->first())
<article class="panel quest-card {{ $quest->isOpen() ? '' : 'is-closed' }}">
    <div class="section-heading"><span class="quest-points"><x-icon name="star"/>{{ $quest->points }} poin</span>@if(! $quest->isOpen())<span class="badge status-draft">Ditutup</span>@elseif($quest->due_at)<span class="badge status-submitted">s.d. {{ \App\Services\Wita::show($quest->due_at, 'd M H:i') }}</span>@endif</div>
    <h2>{{ $quest->title }}</h2>
    <p class="prewrap">{{ $quest->description }}</p>
    <p class="muted">{{ $quest->done_count }} anggota sudah menyelesaikan</p>
    @if($mine && $mine->status === 'approved')<div class="alert success">Selesai · +{{ $mine->points_awarded }} poin</div>
    @elseif($mine && $mine->status === 'submitted')<div class="alert warning">Laporanmu menunggu tinjauan pengurus.</div>
    @elseif($isMember && $quest->isOpen())
        @if($mine && $mine->status === 'rejected')<div class="alert error">Dikembalikan: {{ $mine->review_note }}</div>@endif
        <form method="post" action="/ruang/quest/{{ $quest->id }}/lapor">@csrf<label>Bukti penyelesaian<textarea name="note" required minlength="10" maxlength="2000" rows="2" placeholder="Ceritakan apa yang sudah kamu lakukan (tautan foto/video boleh)."></textarea></label><button class="button outline full">Laporkan selesai</button></form>
    @endif
    @if($canLead && $quest->isOpen())<form method="post" action="/ruang/quest/{{ $quest->id }}/tutup">@csrf<button class="text-link plain">Tutup quest</button></form>@endif
</article>
@empty
<div class="panel empty"><x-icon name="star"/><p>Belum ada quest. {{ $canLead ? 'Buat tantangan seru untuk anggota: hadir latihan, ajak teman, bikin konten, atau bantu acara.' : 'Pengurus akan menerbitkan tantangan seru di sini.' }}</p></div>
@endforelse
</div>

@elseif($tab === 'peringkat')
<section class="panel table-panel"><div class="table-wrap"><table><thead><tr><th>#</th><th>Anggota</th><th>Level</th><th>Quest</th><th>Hadir kegiatan</th><th>Poin</th></tr></thead><tbody>
@forelse($leaderboard as $row)
<tr class="{{ $row['user']->id === auth()->id() ? 'is-me' : '' }}"><td>{{ $row['rank'] <= 3 ? ['🥇', '🥈', '🥉'][$row['rank'] - 1] : $row['rank'] }}</td><td><div class="person"><x-avatar :user="$row['user']"/><span>{{ $row['user']->name }}</span></div></td><td>{{ $row['level'] }}</td><td>{{ $row['quests'] }}</td><td>{{ $row['attended'] }}</td><td><strong>{{ $row['points'] }}</strong></td></tr>
@empty<tr><td colspan="6" class="empty">Belum ada anggota aktif.</td></tr>@endforelse
</tbody></table></div></section>
<p class="muted">Poin = quest yang disetujui + {{ \App\Services\Club\SpaceService::ATTENDANCE_POINTS }} poin setiap hadir kegiatan dengan kode kehadiran. Level: @foreach(\App\Services\Club\SpaceService::LEVELS as $minimum => $title){{ $title }} ({{ $minimum }}+)@if(! $loop->last), @endif @endforeach.</p>

@else
<div class="detail-columns">
<section class="panel"><h2>Pengurus</h2>
    <ul class="plain-list">@forelse($officers as $officer)<li><div class="person"><x-avatar :user="$officer->user"/><span>{{ $officer->user->name }}<small>{{ $officer->position }}{{ $officer->period ? ' · '.$officer->period : '' }}</small></span></div></li>@empty<li class="muted">Belum ditetapkan.</li>@endforelse</ul>
</section>
<section class="panel"><h2>Anggota aktif <span class="count-pill">{{ $members->count() }}</span></h2>
    <ul class="plain-list">@foreach($members as $member)<li><div class="person"><x-avatar :user="$member->user"/><span>{{ $member->user->name }}<small>{{ $member->division ?? 'Anggota' }}</small></span></div></li>@endforeach</ul>
</section>
</div>
@endif
</x-layout>
