<x-layout title="Proposal & LPJ">
<div class="page-heading"><div><p class="eyebrow">ADMINISTRASI ORGANISASI</p><h1>Proposal & LPJ.</h1><p>Pengelola organisasi mengajukan proposal kegiatan serta laporan pertanggungjawaban kegiatan atau kepengurusan ke Kemahasiswaan Jurusan atau Direktorat, lalu memantau validasinya di sini.</p></div></div>

<div class="kpi-grid">
    @foreach(['submitted', 'revision', 'accepted', 'rejected'] as $status)<a class="kpi {{ $status === 'revision' && ($counts[$status] ?? 0) ? 'has-task' : '' }}" href="/proposal?status={{ $status }}"><span>{{ config('brudel.status_labels')[$status] }}</span><strong>{{ $counts[$status] ?? 0 }}</strong></a>@endforeach
</div>

@if($clubs->isNotEmpty())
<section class="panel"><h2>Ajukan dokumen</h2>
    <form class="form-grid" method="post" action="/proposal" enctype="multipart/form-data">@csrf
        <label>Organisasi<select name="club_id" required>@foreach($clubs as $club)<option value="{{ $club->id }}" @selected(old('club_id') == $club->id)>{{ $club->name }}</option>@endforeach</select></label>
        <label>Jenis dokumen<select name="type" required>@foreach(\App\Models\ClubSubmission::TYPES as $value => $label)<option value="{{ $value }}" @selected(old('type') === $value)>{{ $label }}</option>@endforeach</select></label>
        <label class="span-2">Judul<input name="title" required minlength="5" maxlength="190" value="{{ old('title') }}" placeholder="Mis. Proposal Bakti Sosial Mahasiswa 2026"></label>
        <label class="span-2">Ringkasan (opsional)<textarea name="summary" rows="3" maxlength="2000">{{ old('summary') }}</textarea></label>
        <label>Ditujukan kepada<select name="target" required><option value="direktorat" @selected(old('target') === 'direktorat')>Kemahasiswaan Direktorat</option>@foreach($departments as $department)<option value="jurusan:{{ $department->id }}" @selected(old('target') === 'jurusan:'.$department->id)>Kemahasiswaan Jurusan {{ $department->name }}</option>@endforeach</select></label>
        <label>Kegiatan (untuk LPJ kegiatan)<select name="club_activity_id"><option value="">Pilih kegiatan</option>@foreach($activities as $activity)<option value="{{ $activity->id }}" @selected(old('club_activity_id') == $activity->id)>{{ $activity->title }} · {{ $activity->club->name }} · {{ \App\Services\Wita::show($activity->starts_at, 'd M Y') }}</option>@endforeach</select></label>
        <label>Periode (untuk LPJ kepengurusan)<input name="period" maxlength="30" value="{{ old('period', $runningPeriod) }}" placeholder="Mis. 2025/2026"></label>
        <label>Dokumen (PDF/DOCX ≤ 5 MB)<input type="file" name="document" required accept=".pdf,.docx,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document"></label>
        <div class="span-2"><button class="button primary">Kirim pengajuan</button></div>
    </form>
</section>
@endif

<section class="section-block">
<form class="filters" method="get" action="/proposal">
    <label>Jenis<select name="jenis"><option value="">Semua jenis</option>@foreach(\App\Models\ClubSubmission::TYPES as $value => $label)<option value="{{ $value }}" @selected(request('jenis') === $value)>{{ $label }}</option>@endforeach</select></label>
    <label>Status<select name="status"><option value="">Semua status</option>@foreach(['submitted', 'revision', 'accepted', 'rejected'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ config('brudel.status_labels')[$status] }}</option>@endforeach</select></label>
    <button class="button outline">Terapkan</button>
</form>
<div class="panel table-panel"><div class="table-wrap"><table><thead><tr><th>Dokumen</th><th>Organisasi</th><th>Tujuan</th><th>Status</th><th>Diajukan</th></tr></thead><tbody>
@forelse($submissions as $submission)
<tr><td><a class="text-link" href="/proposal/{{ $submission->id }}">{{ $submission->title }}</a><small>{{ $submission->typeLabel() }} · versi {{ $submission->version }}</small></td>
<td>{{ $submission->club->name }}</td><td>{{ $submission->targetLabel() }}</td><td><x-status :value="$submission->status"/>@if($submission->status === 'revision' && $submission->review_note)<small>{{ \Illuminate\Support\Str::limit($submission->review_note, 80) }}</small>@endif</td><td>{{ \App\Services\Wita::show($submission->submitted_at, 'd M Y') }}</td></tr>
@empty<tr><td colspan="5" class="empty">Belum ada pengajuan.</td></tr>@endforelse
</tbody></table></div></div>
{{ $submissions->links() }}
</section>
</x-layout>
