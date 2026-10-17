<x-layout title="Laporan">
<div class="page-heading"><div><p class="eyebrow">DASHBOARD & LAPORAN</p><h1>Laporan BRUDEL.</h1><p>Satu penghargaan tim dihitung satu kali sebagai penghargaan, walaupun penerimanya beberapa mahasiswa.</p></div></div>
@if($dashboard)
<form class="filters" method="get">
    <label>Tahun<input type="number" name="year" min="2000" max="2100" value="{{ $filters['year'] }}" placeholder="Semua"></label>
    <label>Tingkat<select name="level"><option value="">Semua tingkat</option>@foreach(config('brudel.levels') as $key => $label)<option value="{{ $key }}" @selected($filters['level'] === $key)>{{ $label }}</option>@endforeach</select></label>
    <label>Bidang<select name="field"><option value="">Semua bidang</option>@foreach($master->allFields() as $field)<option @selected($filters['field'] === $field)>{{ $field }}</option>@endforeach</select></label>
    <label>Jurusan<select name="department_id">@unless($scoped)<option value="">Semua jurusan</option>@endunless @foreach($departments as $department)<option value="{{ $department->id }}" @selected($filters['department_id'] === $department->id)>{{ $department->name }}</option>@endforeach</select></label>
    <label>UKM<select name="club_id"><option value="">Semua UKM</option>@foreach($clubs as $club)<option value="{{ $club->id }}" @selected($filters['club_id'] === $club->id)>{{ $club->name }}</option>@endforeach</select></label>
    <label>Format<select name="team"><option value="">Individu & tim</option><option value="individu" @selected($filters['team'] === 'individu')>Individu</option><option value="tim" @selected($filters['team'] === 'tim')>Tim</option></select></label>
    <button class="button primary">Terapkan</button><a class="text-link" href="/laporan">Atur ulang</a>
</form>

<div class="kpi-grid">
    <div class="kpi"><span>Jumlah penghargaan</span><strong>{{ $summary['award_count'] }}</strong><small>{{ $summary['team_awards'] }} penghargaan tim</small></div>
    <div class="kpi"><span>Mahasiswa berprestasi unik</span><strong>{{ $summary['unique_achievers'] }}</strong><small>{{ $summary['recipient_rows'] }} baris penerima</small></div>
    <div class="kpi"><span>Peserta lomba diterima</span><strong>{{ $summary['participant_entries'] }}</strong><small>{{ $summary['unique_participants'] }} mahasiswa unik</small></div>
    <div class="kpi"><span>Tim · individu</span><strong>{{ $summary['team_count'] }} · {{ $summary['individual_count'] }}</strong><small>pendaftaran diterima</small></div>
    <div class="kpi"><span>Keanggotaan UKM aktif</span><strong>{{ $summary['membership_count'] }}</strong><small>{{ $summary['unique_members'] }} mahasiswa unik</small></div>
</div>

@include('reports._iku', ['iku' => $iku, 'year' => $filters['year']])

<div class="detail-columns">
<section class="panel"><h2>Penghargaan per tingkat (IKU)</h2>
    @php($max = max(1, $summary['by_level']->max()))
    @foreach(config('brudel.levels') as $key => $label)
    <div class="bar-row"><span>{{ $label }}</span><div class="bar"><i style="width: {{ round($summary['by_level'][$key] / $max * 100) }}%"></i></div><strong>{{ $summary['by_level'][$key] }}</strong></div>
    @endforeach
</section>
<section class="panel"><h2>Penghargaan per bidang</h2>
    @php($maxField = max(1, $summary['by_field']->max()))
    @foreach($summary['by_field'] as $field => $count)
    <div class="bar-row"><span>{{ $field }}</span><div class="bar {{ strtolower($field) }}"><i style="width: {{ round($count / $maxField * 100) }}%"></i></div><strong>{{ $count }}</strong></div>
    @endforeach
</section>
</div>

<section class="panel table-panel"><div class="section-heading padded"><h2>Penghargaan terbaru sesuai filter</h2></div><div class="table-wrap"><table><thead><tr><th>Kegiatan</th><th>Penghargaan</th><th>Tingkat</th><th>Bidang</th><th>Format</th><th>Penerima</th></tr></thead><tbody>
@forelse($awards as $award)<tr><td>{{ $award->title }}<small>{{ $award->awarded_on->format('d/m/Y') }} · {{ $award->source === 'brudel' ? 'BRUDEL' : 'Eksternal' }}</small></td><td>{{ $award->award }}</td><td>{{ $award->levelLabel() }}</td><td>{{ $award->field }}</td><td>{{ $award->is_team ? 'Tim' : 'Individu' }}</td><td>{{ $award->recipients_count }}</td></tr>
@empty<tr><td colspan="6" class="empty">Tidak ada penghargaan terverifikasi sesuai filter.</td></tr>@endforelse
</tbody></table></div></section>
@else
<div class="alert warning">Dashboard statistik kampus tersedia untuk kemahasiswaan dan pimpinan. Anda dapat mengunduh laporan dalam lingkup penugasan.</div>
@endif

<section class="panel"><h2>Ekspor CSV sesuai hak akses</h2>
<div class="export-grid">@foreach($exports as $type => $label)<a class="button outline" href="/laporan/ekspor/{{ $type }}?{{ http_build_query(array_filter($filters)) }}"><x-icon name="download"/> {{ $label }}</a>@endforeach</div>
<p class="form-note">Pengurus UKM hanya dapat mengunduh keanggotaan UKM sendiri; admin event hanya data event yang ditugaskan. Setiap ekspor tercatat pada audit; berkas CSV UTF-8 dilindungi dari injeksi formula.</p>
</section>
</x-layout>
