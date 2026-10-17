<x-layout title="Penugasan Peran">
<div class="page-heading"><div><p class="eyebrow">HAK AKSES BERJANGKA</p><h1>Penugasan peran.</h1><p>Satu pengguna dapat memiliki beberapa peran. Akses dibatasi menurut UKM, event, jurusan, atau nomor lomba. Ketua UKM tidak otomatis menjadi admin event.</p></div></div>

<section class="panel">
    <h2>Impor ketua & pengurus organisasi (CSV)</h2>
    <p class="muted">Kolom: <code>organisasi, nama, nim, prodi</code>, opsional <code>jabatan</code> (Ketua, Ketua Putra, Ketua Putri, Wakil Ketua, Sekretaris, …), <code>email</code>, <code>periode</code>. Organisasi boleh ditulis seperti "BEM", "MPM", "HMJ Kesling", atau nama lengkap. Mahasiswa yang belum ada dibuat dari NIM; tanpa email diberi email sementara yang tertimpa saat impor resmi mahasiswa (dicocokkan NIM), sehingga aktivasi menunggu email resmi. Prodi baru dibuat dan dipetakan ke jurusan menurut namanya. Periode bawaan {{ \App\Services\Account\OfficerImportService::currentPeriod() }}, berlaku 1 tahun.</p>
    <form class="inline-form" method="post" action="/kelola/penugasan/impor-ketua" enctype="multipart/form-data">@csrf
        <input type="file" name="file" accept=".csv,text/csv" required aria-label="Berkas CSV ketua organisasi">
        <button class="button outline" name="mode" value="preview">Pratinjau</button>
        <button class="button primary" name="mode" value="commit">Impor</button>
        <a class="text-link" href="/kelola/penugasan/template-ketua.csv">Unduh template (semua organisasi) ↓</a>
    </form>
    @if($report = session('officer_import'))
    <div class="alert {{ $report['errors'] ? 'warning' : 'success' }}">{{ $report['committed'] ? 'Hasil impor' : 'Pratinjau' }}: {{ count($report['rows']) }} baris, {{ $report['errors'] }} gagal{{ $report['committed'] ? ', '.$report['assigned'].' penugasan dibuat, '.$report['created_users'].' akun baru' : '' }}.@if($report['new_programs']) Prodi baru: {{ implode(', ', $report['new_programs']) }}.@endif</div>
    <div class="table-wrap"><table class="compact"><thead><tr><th>Baris</th><th>Organisasi</th><th>Jabatan</th><th>Mahasiswa</th><th>Prodi · jurusan</th><th>Hasil</th></tr></thead><tbody>
    @foreach($report['rows'] as $row)
    <tr><td>{{ $row['line'] }}</td><td>{{ $row['organization'] }}</td><td>{{ $row['position'] }}</td><td>{{ $row['name'] }}<small>{{ $row['nim'] }}</small></td><td>{{ $row['program'] }}<small>{{ $row['department'] ?? 'Jurusan belum dipetakan' }}</small></td>
    <td>@if($row['messages'])<span class="badge status-rejected">Gagal</span><small>{{ implode(' ', $row['messages']) }}</small>@else<span class="badge {{ $row['result'] === 'Sudah menjabat' ? 'status-draft' : 'status-accepted' }}">{{ $row['result'] }}</span>@endif</td></tr>
    @endforeach
    </tbody></table></div>
    @endif
</section>

<section class="panel" x-data="{ role: '{{ old('role', 'pengurus') }}', scopes: @js(collect(config('brudel.roles'))->map(fn ($r) => $r['scope'])) }">
    <h2>Tambah penugasan</h2>
    <form class="form-grid" method="post" action="/kelola/penugasan">@csrf
        <label>Email, NIM, atau username pengguna<input name="user" required maxlength="190" value="{{ old('user') }}" placeholder="nama@email.kampus, NIM, atau username"></label>
        <label>Peran<select name="role" x-model="role" required>@foreach($roleOptions as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></label>
        @foreach(['club_id' => 'UKM', 'event_id' => 'Event', 'department_id' => 'Jurusan', 'competition_id' => 'Nomor lomba'] as $column => $label)
        <template x-if="scopes[role] === '{{ $column }}'"><label>Lingkup: {{ $label }}<select name="scope_id" required><option value="">Pilih {{ strtolower($label) }}</option>@foreach($scopes[$column] as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select></label></template>
        @endforeach
        <template x-if="role === 'pimpinan'"><label>Jabatan pimpinan<select name="position">@foreach(config('brudel.pimpinan_positions') as $position)<option>{{ $position }}</option>@endforeach</select></label></template>
        <template x-if="role === 'pengurus'"><label>Jabatan<select name="position"><option>Ketua</option><option>Wakil Ketua</option><option>Sekretaris</option><option>Bendahara</option><option>Koordinator Divisi</option><option>Anggota pengurus</option></select></label></template>
        <label>Periode kepengurusan (opsional)<input name="period" maxlength="30" placeholder="2026/2027" value="{{ old('period') }}"></label>
        <label>Mulai (WITA)<input type="datetime-local" name="starts_at" required value="{{ old('starts_at', now()->timezone('Asia/Makassar')->format('Y-m-d\TH:i')) }}"></label>
        <label>Berakhir (WITA)<input type="datetime-local" name="ends_at" required value="{{ old('ends_at', now()->timezone('Asia/Makassar')->addYear()->format('Y-m-d\TH:i')) }}"></label>
        <div class="span-2"><button class="button primary">Simpan penugasan</button><p class="form-note">Perubahan hak akses dicatat pada audit. Anda tidak dapat mengubah hak akses sendiri.</p></div>
    </form>
</section>

<section class="panel">
    <h2>Akun staf baru</h2>
    <p class="muted">Untuk petugas yang belum punya akun, misalnya Kemahasiswaan Jurusan. Petugas masuk dengan username dan sandi awal, lalu wajib membuat sandi baru saat pertama masuk. Akses langsung terbatas pada jurusan yang dipilih.</p>
    <form class="form-grid" method="post" action="/kelola/penugasan/akun-staf">@csrf
        <label>Nama lengkap<input name="name" required maxlength="120" value="{{ old('name') }}" placeholder="Nama petugas"></label>
        <label>Username<input name="username" required maxlength="60" pattern="[a-z0-9._\-]{3,60}" value="{{ old('username') }}" placeholder="mis. kemahasiswaan-kep" autocomplete="off"></label>
        <label>Peran<select name="role" required>@foreach($staffRoleOptions as $key => $label)<option value="{{ $key }}" @selected(old('role') === $key)>{{ $label }}</option>@endforeach</select></label>
        <label>Email (opsional, untuk pemulihan sandi)<input type="email" name="email" maxlength="190" value="{{ old('email') }}" placeholder="nama@poltekkes-manado.ac.id"></label>
        <label>Sandi awal<input type="password" name="password" required minlength="10" autocomplete="new-password"></label>
        <label>Ulangi sandi awal<input type="password" name="password_confirmation" required minlength="10" autocomplete="new-password"></label>
        <label>Akses berakhir (WITA)<input type="datetime-local" name="ends_at" required value="{{ old('ends_at', now()->timezone('Asia/Makassar')->addYear()->format('Y-m-d\TH:i')) }}"></label>
        <div class="span-2"><button class="button primary">Buat akun & tugaskan</button><p class="form-note">Username hanya huruf kecil, angka, titik, minus, atau garis bawah. Sandi minimal 10 karakter berisi huruf dan angka.</p></div>
    </form>
</section>

<form class="filters" method="get">
    <label>Peran<select name="role"><option value="">Semua peran</option><option value="jurusan" @selected(request('role') === 'jurusan')>Kemahasiswaan Jurusan (semua jurusan)</option>@foreach($roleOptions as $key => $label)<option value="{{ $key }}" @selected(request('role') === $key)>{{ $label }}</option>@endforeach</select></label>
    <label>Masa<select name="aktif"><option value="">Masih berlaku</option><option value="0" @selected(request('aktif') === '0')>Termasuk berakhir</option></select></label>
    <button class="button outline">Terapkan</button>
</form>
<div class="panel table-panel"><div class="table-wrap"><table><thead><tr><th>Pengguna</th><th>Peran & lingkup</th><th>Masa berlaku (WITA)</th><th>Tindakan</th></tr></thead><tbody>
@forelse($assignments as $a)
<tr><td><strong>{{ $a->user->name }}</strong><small>{{ $a->user->email }}</small></td>
<td>{{ $a->roleLabel() }}{{ $a->position ? ' · '.$a->position : '' }}<small>{{ collect([$a->role === 'jurusan' ? null : $a->scopeLabel(), $a->period])->filter()->join(' · ') }}</small></td>
<td>{{ \App\Services\Wita::show($a->starts_at, 'd M Y') }} – {{ \App\Services\Wita::show($a->ends_at, 'd M Y') }}<small>{{ $a->isCurrent() ? 'Berlaku' : ($a->ends_at->isPast() ? 'Berakhir'.($a->ended_reason ? ': '.$a->ended_reason : '') : 'Belum mulai') }}</small></td>
<td>@if($a->ends_at->isFuture())<form class="inline-form" method="post" action="/kelola/penugasan/{{ $a->id }}/akhiri">@csrf<input name="reason" required minlength="5" maxlength="500" placeholder="Alasan diakhiri"><button class="button outline small">Akhiri</button></form>@endif</td></tr>
@empty<tr><td colspan="4" class="empty">Belum ada penugasan.</td></tr>@endforelse
</tbody></table></div></div>
{{ $assignments->links() }}
</x-layout>
