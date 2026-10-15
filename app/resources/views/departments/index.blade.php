<x-layout title="Jurusan">
<div class="page-heading"><div><p class="eyebrow">RUANG JURUSAN</p><h1>{{ $selected ? 'Jurusan '.$selected->name : 'Jurusan' }}</h1><p>Persetujuan lomba, keaktifan UKM (selain HIMAJU yang otomatis), sertifikat juara, dan prestasi mahasiswa jurusan Anda saja.</p></div>
@if($selected)<div class="inline-actions"><a class="button outline" href="/jurusan/{{ $selected->id }}/ekspor?data=ukm">CSV UKM mahasiswa ↓</a><a class="button outline" href="/jurusan/{{ $selected->id }}/ekspor?data=prestasi">CSV prestasi ↓</a></div>@endif</div>

@if($departments->count() > 1)
<form class="filters" method="get"><label>Jurusan<select name="jurusan" onchange="this.form.submit()">@foreach($departments as $department)<option value="{{ $department->id }}" @selected($selected?->id === $department->id)>{{ $department->name }}</option>@endforeach</select></label></form>
@endif

<div class="kpi-grid">
    <a class="kpi" href="?jurusan={{ $selected?->id }}"><span>Mahasiswa aktif</span><strong>{{ $counts['total'] }}</strong></a>
    <a class="kpi" href="?jurusan={{ $selected?->id }}&ukm=aktif"><span>Aktif di UKM/BEM/MPM</span><strong>{{ $counts['active'] }}</strong></a>
    <a class="kpi {{ $counts['inactive'] ? 'has-task' : '' }}" href="?jurusan={{ $selected?->id }}&ukm=tidak"><span>Tidak aktif di UKM, BEM, maupun MPM</span><strong>{{ $counts['inactive'] }}</strong></a>
    <div class="kpi"><span>Menunggu persetujuan</span><strong>{{ $pending->count() }}</strong></div>
</div>

<section class="section-block"><h2>Menunggu persetujuan · {{ $pending->count() }}</h2>
@forelse($pending as $approval)
@php($r = $approval->registration)
<article class="panel">
    <div class="section-heading"><div><span class="eyebrow">{{ $r->competition->event->name }} · Jurusan {{ $approval->department->name }}</span><h2>{{ $r->competition->name }}{{ $r->team_name ? ' — '.$r->team_name : '' }}</h2></div><x-status :value="$r->status"/></div>
    <p>{{ $r->motivation }}</p>
    <ul class="plain-list">@foreach($r->participants as $p)<li>{{ $p->user->name }} · {{ $p->user->nim }} · <span class="{{ $p->user->program?->department_id === $approval->department_id ? '' : 'muted' }}">{{ $p->user->program?->name }}</span></li>@endforeach</ul>
    <form class="review-form" method="post" action="/jurusan/persetujuan/{{ $approval->id }}">@csrf
        <label>Keputusan<select name="decision" required><option value="">Pilih</option><option value="approved">Setujui</option><option value="rejected">Minta perbaikan / tolak</option></select></label>
        <label>Alasan<input name="reason" maxlength="2000" placeholder="Wajib bila tidak disetujui"></label>
        <button class="button primary">Simpan</button>
    </form>
</article>
@empty<div class="panel empty">Tidak ada pendaftaran yang menunggu persetujuan jurusan.</div>@endforelse
</section>

<section class="section-block">
<div class="section-heading"><h2>Mahasiswa & UKM</h2>
<form method="get" class="inline-form"><input type="hidden" name="jurusan" value="{{ $selected?->id }}"><select name="ukm" onchange="this.form.submit()"><option value="">Semua mahasiswa</option><option value="aktif" @selected($filter === 'aktif')>Aktif di UKM</option><option value="tidak" @selected($filter === 'tidak')>Tidak aktif di UKM</option></select></form></div>
<div class="panel table-panel"><div class="table-wrap"><table><thead><tr><th>Mahasiswa</th><th>Prodi</th><th>UKM/BEM/MPM aktif</th><th>Riwayat</th></tr></thead><tbody>
@forelse($students as $student)
<tr><td><div class="person"><x-avatar :user="$student"/><span><strong>{{ $student->name }}</strong><small>{{ $student->nim }}</small></span></div></td><td>{{ $student->program?->name }}</td>
<td>@forelse($student->memberships->where('status', 'active') as $m)<span class="chip">{{ $m->club->name }}{{ $m->division ? ' · '.$m->division : '' }}</span>@empty<span class="badge status-inactive">Tidak aktif di UKM</span>@endforelse</td>
<td>@foreach($student->memberships->where('status', '!=', 'active') as $m)<small>{{ $m->club->name }} · {{ config('brudel.status_labels')[$m->status] ?? $m->status }}</small>@endforeach</td></tr>
@empty<tr><td colspan="4" class="empty">Tidak ada mahasiswa sesuai filter.</td></tr>@endforelse
</tbody></table></div></div>
{{ $students->links() }}
</section>

<div class="detail-columns">
<section class="panel"><h2>Sertifikat juara</h2>
<div class="table-wrap"><table class="compact"><thead><tr><th>Mahasiswa</th><th>Lomba & penghargaan</th><th></th></tr></thead><tbody>
@forelse($certificates as $certificate)<tr><td>{{ $certificate->user->name }}<small>{{ $certificate->user->program?->name }}</small></td><td>{{ $certificate->award_type }}<small>{{ $certificate->number }} · {{ $certificate->title }}</small></td><td><a class="text-link" href="/sertifikat/{{ $certificate->id }}/unduh">Unduh ↓</a></td></tr>
@empty<tr><td colspan="3" class="empty">Belum ada sertifikat juara.</td></tr>@endforelse
</tbody></table></div></section>
<section class="panel"><h2>Prestasi mahasiswa</h2>
<div class="table-wrap"><table class="compact"><thead><tr><th>Prestasi</th><th>Tingkat</th><th>Mahasiswa jurusan</th></tr></thead><tbody>
@forelse($awards as $award)<tr><td>{{ $award->award }}<small>{{ $award->title }} · {{ $award->awarded_on->format('Y') }}</small>@if($award->evidence_path)<a class="text-link" href="/prestasi/{{ $award->id }}/bukti">Bukti ↓</a>@endif</td><td>{{ $award->levelLabel() }}</td>
<td>{{ $award->recipients->filter(fn ($r) => $r->user->program?->department_id === $selected?->id)->map(fn ($r) => $r->user->name)->join(', ') }}</td></tr>
@empty<tr><td colspan="3" class="empty">Belum ada prestasi terverifikasi.</td></tr>@endforelse
</tbody></table></div></section>
</div>

@if($decided->isNotEmpty())
<section class="panel"><h2>Keputusan terakhir</h2>
<ul class="plain-list">@foreach($decided as $approval)<li>{{ $approval->registration->competition->name }} · <x-status :value="$approval->status"/>@if($approval->decider) oleh {{ $approval->decider->name }}@endif {{ \App\Services\Wita::show($approval->decided_at, 'd M H:i') }}@if($approval->reason) · {{ $approval->reason }}@endif</li>@endforeach</ul></section>
@endif
</x-layout>
