<x-layout title="Persetujuan Prodi">
<div class="page-heading"><div><p class="eyebrow">JURUSAN / PROGRAM STUDI</p><h1>Mahasiswa dalam unit Anda.</h1><p>Lingkup: {{ $programs->pluck('name')->join(', ') ?: '—' }}. Tim lintas prodi memerlukan persetujuan setiap unit terkait.</p></div></div>

<section class="section-block"><h2>Menunggu persetujuan · {{ $pending->count() }}</h2>
@forelse($pending as $approval)
@php($r = $approval->registration)
<article class="panel">
    <div class="section-heading"><div><span class="eyebrow">{{ $r->competition->event->name }} · Prodi {{ $approval->program->name }}</span><h2>{{ $r->competition->name }}{{ $r->team_name ? ' — '.$r->team_name : '' }}</h2></div><x-status :value="$r->status"/></div>
    <p>{{ $r->motivation }}</p>
    <ul class="plain-list">@foreach($r->participants as $p)<li>{{ $p->user->name }} · {{ $p->user->nim }} · <span class="{{ $p->user->program_id === $approval->program_id ? '' : 'muted' }}">{{ $p->user->program?->name }}</span></li>@endforeach</ul>
    <form class="review-form" method="post" action="/prodi/{{ $approval->id }}">@csrf
        <label>Keputusan<select name="decision" required><option value="">Pilih</option><option value="approved">Setujui</option><option value="rejected">Minta perbaikan / tolak</option></select></label>
        <label>Alasan<input name="reason" maxlength="2000" placeholder="Wajib bila tidak disetujui"></label>
        <button class="button primary">Simpan</button>
    </form>
</article>
@empty<div class="panel empty">Tidak ada pendaftaran yang menunggu persetujuan prodi.</div>@endforelse
</section>

<div class="detail-columns">
<section class="panel"><h2>Pendaftaran lomba mahasiswa unit</h2>
<div class="table-wrap"><table class="compact"><thead><tr><th>Lomba</th><th>Peserta</th><th>Status</th></tr></thead><tbody>
@forelse($registrations as $r)<tr><td>{{ $r->competition->name }}</td><td>{{ $r->participants->map(fn ($p) => $p->user->name)->join(', ') }}</td><td><x-status :value="$r->status"/></td></tr>@empty<tr><td colspan="3" class="empty">Belum ada.</td></tr>@endforelse
</tbody></table></div></section>
<section class="panel"><h2>Pendaftaran UKM mahasiswa unit</h2>
<div class="table-wrap"><table class="compact"><thead><tr><th>UKM</th><th>Mahasiswa</th><th>Status</th></tr></thead><tbody>
@forelse($clubApplications as $a)<tr><td>{{ $a->recruitment->club->name }}</td><td>{{ $a->user->name }}</td><td><x-status :value="$a->status"/></td></tr>@empty<tr><td colspan="3" class="empty">Belum ada.</td></tr>@endforelse
</tbody></table></div></section>
</div>

@if($decided->isNotEmpty())
<section class="panel"><h2>Keputusan terakhir</h2>
<ul class="plain-list">@foreach($decided as $approval)<li>{{ $approval->registration->competition->name }} · {{ $approval->program->name }} · <x-status :value="$approval->status"/>@if($approval->decider) oleh {{ $approval->decider->name }}@endif {{ \App\Services\Wita::show($approval->decided_at, 'd M H:i') }}@if($approval->reason) · {{ $approval->reason }}@endif</li>@endforeach</ul></section>
@endif
</x-layout>
