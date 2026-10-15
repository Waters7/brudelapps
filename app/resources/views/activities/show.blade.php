<x-layout :title="$activity->title">
<a class="back-link" href="/kegiatan">← Jelajah Kegiatan</a>
<div class="page-heading"><div><p class="eyebrow">{{ strtoupper($activity->club->name) }} · {{ strtoupper($activity->typeLabel()) }}</p><h1>{{ $activity->title }}</h1>
<p>{{ \App\Services\Wita::show($activity->starts_at, 'l, d F Y · H:i') }}–{{ \App\Services\Wita::show($activity->ends_at, 'H:i') }} WITA · {{ $activity->location }}</p></div><x-status :value="$activity->status"/></div>

<div class="detail-columns">
<section class="panel">
    <h2>Tentang kegiatan</h2>
    <p class="prewrap">{{ $activity->description ?: 'Tidak ada keterangan tambahan.' }}</p>
    <dl><dt>Dibuat oleh</dt><dd>{{ $activity->creator->name }}</dd><dt>Akses</dt><dd>{{ $activity->is_public ? 'Terbuka (tampil di halaman depan)' : 'Internal anggota UKM' }}</dd>
    @if($activity->status === 'completed')
        @if($activity->type === 'persahabatan')<dt>Hasil</dt><dd><strong>{{ $activity->club->name }} {{ $activity->score_home }} – {{ $activity->score_away }} {{ $activity->opponent }}</strong></dd>@endif
        @if($activity->notes)<dt>{{ $activity->type === 'persahabatan' ? 'Catatan untuk latihan selanjutnya' : 'Catatan' }}</dt><dd class="prewrap">{{ $activity->notes }}</dd>@endif
        @if($activity->minutes_path)<dt>Notulen</dt><dd><a class="text-link" href="/kegiatan/{{ $activity->id }}/notulen">Unduh notulen ↓</a></dd>@endif
    @elseif($activity->status === 'cancelled')<dt>Status</dt><dd>{{ $activity->notes }}</dd>@endif
    </dl>
    @if($attended)<div class="alert success">Kehadiran Anda tercatat {{ \App\Services\Wita::show($attended->checked_in_at, 'H:i') }} WITA.</div>@endif
</section>

@if($canManage)
<section class="panel code-panel">
    <h2>Kode kehadiran</h2>
    @if($activity->codeIsOpen())
        <div class="attendance-code" aria-label="Kode kehadiran">{{ $activity->attendance_code }}</div>
        <p class="muted">Berlaku sampai {{ \App\Services\Wita::show($activity->code_expires_at, 'H:i') }} WITA · {{ $summary['present']->count() }} hadir dari {{ $summary['members'] }} anggota aktif</p>
        <form method="post" action="/kegiatan/{{ $activity->id }}/kode/tutup">@csrf<button class="button outline small">Tutup kode</button></form>
    @elseif($activity->status === 'scheduled')
        <p>Buat kode saat kegiatan dimulai. Anggota memasukkan kode ini di menu Jelajah Kegiatan.</p>
        <form class="inline-form" method="post" action="/kegiatan/{{ $activity->id }}/kode">@csrf<label>Berlaku (menit)<input type="number" name="minutes" min="5" max="480" value="{{ config('brudel.activity_code_minutes') }}"></label><button class="button primary small">Buat kode kehadiran</button></form>
    @else
        <p class="muted">Kode tidak aktif.</p>
    @endif
    <div class="inline-actions"><a class="button outline small" href="/kegiatan/{{ $activity->id }}/laporan" target="_blank" rel="noopener">Laporan (cetak/PDF)</a><a class="button outline small" href="/kegiatan/{{ $activity->id }}/laporan.csv">CSV kehadiran ↓</a></div>
</section>
@endif
</div>

@if($canManage)
<div class="detail-columns">
<section class="panel"><h2>Hadir · {{ $summary['present']->count() }}</h2>
    <ul class="plain-list">@forelse($summary['present'] as $attendance)<li><div class="person"><x-avatar :user="$attendance->user"/><span>{{ $attendance->user->name }}<small>{{ \App\Services\Wita::show($attendance->checked_in_at, 'H:i') }} · {{ $attendance->method === 'code' ? 'kode' : 'dicatat manual' }}</small></span></div></li>@empty<li class="muted">Belum ada yang hadir.</li>@endforelse</ul>
</section>
<section class="panel"><h2>Tidak hadir · {{ $summary['absent']->count() }}</h2>
    <ul class="plain-list">@forelse($summary['absent'] as $member)<li class="list-row"><span>{{ $member->name }}<small>{{ $member->nim }}</small></span>
        @if($activity->status !== 'cancelled')<form method="post" action="/kegiatan/{{ $activity->id }}/hadir-manual">@csrf<input type="hidden" name="user_id" value="{{ $member->id }}"><button class="text-link plain">Tandai hadir</button></form>@endif</li>
    @empty<li class="muted">Semua anggota aktif hadir.</li>@endforelse</ul>
</section>
</div>

@if($activity->status !== 'cancelled' && $activity->starts_at->isPast())
<section class="panel">
    <h2>{{ $activity->status === 'completed' ? 'Perbarui laporan' : 'Buat laporan kegiatan' }}</h2>
    <form class="form-grid" method="post" action="/kegiatan/{{ $activity->id }}/laporan" enctype="multipart/form-data">@csrf
        @if($activity->type === 'persahabatan')
            <label class="span-2">Lawan<input name="opponent" required maxlength="150" value="{{ old('opponent', $activity->opponent) }}"></label>
            <label>Skor {{ $activity->club->name }}<input type="number" name="score_home" required min="0" value="{{ old('score_home', $activity->score_home) }}"></label>
            <label>Skor lawan<input type="number" name="score_away" required min="0" value="{{ old('score_away', $activity->score_away) }}"></label>
            <label class="span-2">Catatan penting untuk selanjutnya<textarea name="notes" required minlength="10" rows="4" maxlength="5000">{{ old('notes', $activity->notes) }}</textarea></label>
        @elseif($activity->type === 'meeting')
            <label class="span-2">File notulen (PDF/DOCX ≤ 5 MB){{ $activity->minutes_path ? ' — kosongkan bila tidak diganti' : ' — wajib' }}<input type="file" name="minutes" accept=".pdf,.docx,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document" @required(!$activity->minutes_path)></label>
            <label class="span-2">Ringkasan keputusan (opsional)<textarea name="notes" rows="3" maxlength="5000">{{ old('notes', $activity->notes) }}</textarea></label>
        @else
            <label class="span-2">Catatan latihan (opsional)<textarea name="notes" rows="3" maxlength="5000">{{ old('notes', $activity->notes) }}</textarea></label>
            <p class="form-note span-2">Daftar hadir dan anggota yang tidak datang otomatis masuk ke laporan.</p>
        @endif
        <div class="span-2"><button class="button primary">Simpan laporan</button></div>
    </form>
</section>
@endif
@if($activity->status === 'scheduled')
<details class="panel collapsible"><summary><strong>Batalkan kegiatan</strong></summary>
<form class="inline-form" method="post" action="/kegiatan/{{ $activity->id }}/batal">@csrf<input name="reason" required minlength="5" placeholder="Alasan pembatalan"><button class="button outline small danger">Batalkan</button></form></details>
@endif
@endif
</x-layout>
