<x-layout title="{{ $canImport ? 'Mahasiswa & Impor' : 'Mahasiswa Jurusan' }}">
<div class="page-heading"><div><p class="eyebrow">{{ $canImport ? 'ADMINISTRASI KAMPUS' : 'KEMAHASISWAAN JURUSAN' }}</p><h1>Data mahasiswa.</h1><p>@if($canImport)Akun mahasiswa berasal dari impor data resmi kampus, lalu diaktifkan melalui email terdaftar.@else Hanya mahasiswa {{ $departments->map(fn ($d) => 'Jurusan '.$d->name)->join(', ', ' dan ') }}. Impor data resmi dilakukan Kemahasiswaan Direktorat.@endif</p></div></div>

@if($canImport)
<div class="detail-columns">
<section class="panel">
    <h2>Impor CSV data resmi</h2>
    <p>Kolom wajib: <code>nim</code>, <code>nama</code>, <code>email</code>, <code>prodi</code> (kode atau nama prodi terdaftar). Kolom <code>status</code> opsional: aktif/nonaktif. Pemisah koma atau titik koma, maksimal {{ \App\Services\Account\StudentImportService::MAX_ROWS }} baris.</p>
    <form method="post" action="/kelola/mahasiswa/impor" enctype="multipart/form-data">@csrf
        <label>Berkas CSV (≤ 2 MB)<input type="file" name="file" accept=".csv,text/csv" required></label>
        <div class="inline-actions"><button class="button outline" name="mode" value="preview">Pratinjau validasi</button><button class="button primary" name="mode" value="commit" onclick="return confirm('Impor baris yang valid sekarang? Baris bermasalah dilewati dan dilaporkan.')">Impor baris valid</button></div>
        <p class="form-note">Akun baru belum aktif sampai mahasiswa membuat kata sandi melalui tautan aktivasi email. Akun teraktivasi tidak dapat diganti emailnya lewat impor.</p>
    </form>
    @if($lastImport)
        <div class="import-result">
            <h3>Ringkasan {{ $lastImport->mode === 'commit' ? 'impor' : 'pratinjau' }} · {{ $lastImport->filename }}</h3>
            <p>{{ $lastImport->total_rows }} baris · {{ $lastImport->created_count }} baru · {{ $lastImport->updated_count }} diperbarui · <strong>{{ $lastImport->failed_count }} bermasalah</strong></p>
            @if($lastImport->row_errors)
            <div class="table-wrap"><table class="compact"><thead><tr><th>Baris</th><th>NIM</th><th>Kesalahan</th></tr></thead><tbody>
            @foreach($lastImport->row_errors as $error)<tr><td>{{ $error['line'] }}</td><td>{{ $error['nim'] }}</td><td>{{ implode(' ', $error['messages']) }}</td></tr>@endforeach
            </tbody></table></div>
            @endif
        </div>
    @endif
</section>
<section class="panel">
    <h2>Riwayat impor</h2>
    <div class="table-wrap"><table class="compact"><thead><tr><th>Waktu</th><th>Berkas</th><th>Mode</th><th>Hasil</th></tr></thead><tbody>
    @forelse($imports as $import)<tr><td>{{ \App\Services\Wita::show($import->created_at, 'd M H:i') }}</td><td>{{ $import->filename }}<small>{{ $import->user->name }}</small></td><td>{{ $import->mode === 'commit' ? 'Impor' : 'Pratinjau' }}</td><td>{{ $import->created_count }} baru · {{ $import->updated_count }} ubah · {{ $import->failed_count }} gagal</td></tr>
    @empty<tr><td colspan="4" class="empty">Belum ada impor.</td></tr>@endforelse
    </tbody></table></div>
</section>
</div>
@endif

<section class="section-block">
<form class="filters" method="get">
    <label>Cari<input name="q" value="{{ request('q') }}" placeholder="NIM, nama, atau email" maxlength="80"></label>
    <label>Prodi<select name="program"><option value="">Semua prodi</option>@foreach($programs as $program)<option value="{{ $program->id }}" @selected(request('program') == $program->id)>{{ $program->name }}</option>@endforeach</select></label>
    <label>Status<select name="status"><option value="">Semua</option><option value="nonaktif" @selected(request('status') === 'nonaktif')>Nonaktif</option><option value="belum" @selected(request('status') === 'belum')>Belum aktivasi</option></select></label>
    <button class="button outline">Terapkan</button>
</form>
<div class="panel table-panel"><div class="table-wrap"><table><thead><tr><th>Mahasiswa</th><th>Prodi</th><th>Aktivasi</th><th>Status</th><th>Tindakan</th></tr></thead><tbody>
@forelse($students as $student)
<tr><td><strong>{{ $student->name }}</strong><small>{{ $student->nim }} · {{ $student->email }}{{ $student->phone ? ' · '.$student->phone : '' }}</small></td><td>{{ $student->program?->name ?? '—' }}</td>
<td>@if($student->email_verified_at)<span class="badge status-accepted">Aktif sejak {{ \App\Services\Wita::show($student->email_verified_at, 'd M Y') }}</span>@else<span class="badge status-submitted">Belum aktivasi</span><form method="post" action="/kelola/mahasiswa/{{ $student->id }}/aktivasi">@csrf<button class="text-link plain">Kirim ulang tautan</button></form>@endif</td>
<td><x-status :value="$student->active ? 'active' : 'inactive'"/></td>
<td><form class="inline-form" method="post" action="/kelola/mahasiswa/{{ $student->id }}/status">@csrf<input type="hidden" name="active" value="{{ $student->active ? 0 : 1 }}"><input name="reason" required minlength="5" maxlength="500" placeholder="Alasan {{ $student->active ? 'penonaktifan' : 'pengaktifan' }}"><button class="button {{ $student->active ? 'outline' : 'primary' }} small">{{ $student->active ? 'Nonaktifkan' : 'Aktifkan' }}</button></form></td></tr>
@empty<tr><td colspan="5" class="empty">Tidak ada mahasiswa sesuai filter.</td></tr>@endforelse
</tbody></table></div></div>
{{ $students->links() }}
</section>
</x-layout>
