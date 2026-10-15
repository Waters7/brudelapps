<x-layout :title="$delegation->title">
<a class="back-link" href="/delegasi">← Delegasi</a>
<div class="page-heading"><div><p class="eyebrow">{{ strtoupper($delegation->field) }} · {{ strtoupper(config('brudel.levels')[$delegation->level]) }}</p><h1>{{ $delegation->title }}</h1><p>{{ $delegation->destination_event }} · {{ $delegation->organizer }}</p></div><x-status :value="$delegation->status"/></div>

<ol class="flow-steps">
@foreach(['draft' => 'Usulan', 'submitted' => 'Menunggu pimpinan', 'approved' => 'Disetujui', 'letter' => 'Surat tugas', 'completed' => 'Hasil dicatat'] as $step => $label)
    @php($done = match ($step) { 'draft' => true, 'submitted' => $delegation->submitted_at !== null, 'approved' => in_array($delegation->status, ['approved', 'completed']), 'letter' => (bool) $delegation->letter_path, 'completed' => $delegation->status === 'completed' })
    <li class="{{ $done ? 'done' : '' }}">{{ $label }}</li>
@endforeach
</ol>

<div class="detail-columns">
<section class="panel">
    <h2>Rincian usulan</h2>
    <dl>
        <dt>Seleksi asal</dt><dd>{{ $delegation->competition ? $delegation->competition->name.' · '.$delegation->competition->event->name : 'Tanpa seleksi BRUDEL' }}</dd>
        <dt>Jadwal</dt><dd>{{ \App\Services\Wita::show($delegation->starts_at) }}{{ $delegation->ends_at ? ' – '.\App\Services\Wita::show($delegation->ends_at) : '' }} WITA · {{ $delegation->location }}</dd>
        <dt>Alasan usulan</dt><dd class="prewrap">{{ $delegation->reason }}</dd>
        <dt>Diusulkan</dt><dd>{{ $delegation->proposer->name }}</dd>
        @if($delegation->decided_at)<dt>Keputusan pimpinan</dt><dd>{{ $delegation->decider?->name }} · {{ \App\Services\Wita::show($delegation->decided_at) }} WITA{{ $delegation->decision_note ? ' · '.$delegation->decision_note : '' }}</dd>@endif
        @if($delegation->outcome)<dt>Hasil keikutsertaan</dt><dd class="prewrap">{{ $delegation->outcome }}</dd>@endif
    </dl>
    <x-history :items="$histories"/>
</section>
<section class="panel">
    <h2>Anggota delegasi</h2>
    <ul class="plain-list">@foreach($delegation->members as $member)<li><strong>{{ $member->user->name }}</strong> · {{ ucfirst($member->role) }}@if($member->user->program) · {{ $member->user->program->name }}@endif</li>@endforeach</ul>
    @if($delegation->letter_path)<div class="info-strip"><x-icon name="file"/><span>Surat tugas {{ $delegation->letter_number }} · <a class="text-link" href="/delegasi/{{ $delegation->id }}/surat-tugas">Unduh PDF ↓</a></span></div>@endif
</section>
</div>

@if($canManage && in_array($delegation->status, ['draft', 'revision']))
<form method="post" action="/delegasi/{{ $delegation->id }}/ajukan">@csrf<button class="button primary">Ajukan ke pimpinan <x-icon name="arrow"/></button></form>
@endif

@if($canDecide)
<section class="panel"><h2>Keputusan pimpinan</h2>
<form class="review-form" method="post" action="/delegasi/{{ $delegation->id }}/keputusan">@csrf
    <label>Keputusan<select name="decision" required><option value="">Pilih</option><option value="approved">Setujui</option><option value="revision">Minta revisi</option><option value="rejected">Tolak</option></select></label>
    <label>Catatan<input name="note" maxlength="2000" placeholder="Wajib bila revisi/ditolak"></label>
    <button class="button primary">Simpan keputusan</button>
</form></section>
@endif

@if(auth()->user()->isStudentAffairs() && $delegation->status === 'approved')
<section class="panel"><h2>Surat tugas resmi</h2><p>Unggah PDF surat tugas yang telah disahkan melalui prosedur kampus.</p>
<form class="inline-form" method="post" action="/delegasi/{{ $delegation->id }}/surat-tugas" enctype="multipart/form-data">@csrf<input name="letter_number" required maxlength="80" placeholder="Nomor surat" value="{{ $delegation->letter_number }}"><input type="file" name="file" accept="application/pdf,.pdf" required><button class="button primary small">{{ $delegation->letter_path ? 'Ganti surat' : 'Unggah surat' }}</button></form></section>
@endif

@if($canManage && $delegation->status === 'approved' && $delegation->letter_path)
<section class="panel"><h2>Catat hasil keikutsertaan</h2>
<form class="form-grid" method="post" action="/delegasi/{{ $delegation->id }}/hasil" enctype="multipart/form-data">@csrf
    <label class="span-2">Ringkasan hasil<textarea name="outcome" required minlength="5" maxlength="2000" rows="3"></textarea></label>
    <label>Penghargaan (opsional)<input name="award" maxlength="80" placeholder="Juara 2 / Medali Perak"></label>
    <label>Tanggal penghargaan<input type="date" name="awarded_on" max="{{ now()->timezone('Asia/Makassar')->toDateString() }}"></label>
    <label class="span-2">Bukti penghargaan (PDF, wajib bila ada penghargaan)<input type="file" name="evidence" accept="application/pdf,.pdf"></label>
    <div class="span-2"><button class="button primary">Simpan hasil</button><p class="form-note">Penghargaan delegasi dicatat sebagai prestasi eksternal dan menunggu verifikasi kemahasiswaan.</p></div>
</form></section>
@endif
</x-layout>
