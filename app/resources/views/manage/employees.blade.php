<x-layout :title="$label">
<div class="page-heading"><div><p class="eyebrow">MASTER DATA</p><h1>{{ $label }}.</h1><p>Akun {{ strtolower($label) }} dari Daftar Urut Kepangkatan. Pemilik akun masuk memakai NIP atau email, dan baru dapat masuk setelah diaktifkan di sini.</p></div></div>

<div class="kpi-grid">
    <div class="kpi"><span>{{ $label }}</span><strong>{{ $counts['total'] }}</strong></div>
    <div class="kpi"><span>Menunggu aktivasi</span><strong>{{ $counts['waiting'] }}</strong></div>
    <div class="kpi"><span>Aktif</span><strong>{{ $counts['active'] }}</strong></div>
    <div class="kpi"><span>Sudah masuk</span><strong>{{ $counts['signed_in'] }}</strong></div>
</div>

<section class="section-block">
<form class="filters" method="get" action="/kelola/master/{{ $kind }}">
    <label>Cari<input name="q" value="{{ request('q') }}" placeholder="Nama, NIP, atau email" maxlength="80"></label>
    <label>Penempatan<select name="jurusan"><option value="">Semua</option>@foreach($departments as $department)<option value="{{ $department->id }}" @selected(request('jurusan') == $department->id)>{{ $department->name }}</option>@endforeach<option value="unit" @selected(request('jurusan') === 'unit')>Direktorat / Unit</option></select></label>
    <label>Status<select name="status"><option value="">Semua</option><option value="menunggu" @selected(request('status') === 'menunggu')>Menunggu aktivasi</option><option value="aktif" @selected(request('status') === 'aktif')>Aktif</option></select></label>
    <button class="button outline">Terapkan</button>
</form>
<form id="aktivasi-pegawai" method="post" action="/kelola/master/pegawai/aktifkan" onsubmit="return confirm('Terapkan ke akun yang dicentang? Akun nonaktif menjadi aktif dan pemiliknya dapat masuk memakai NIP.')">@csrf</form>
<section class="panel">
    <h2>Aktifkan dan tugaskan</h2>
    <p class="muted">Centang nama di tabel, pilih tugasnya, lalu terapkan. Pilih User biasa bila akun hanya perlu diaktifkan. Akun yang sudah aktif juga bisa dicentang untuk ditugaskan. Peran lain ada di <a class="text-link" href="/kelola/penugasan">Penugasan Peran</a>.</p>
    <div class="form-grid">
        <label>Aktifkan sebagai<select name="task" form="aktivasi-pegawai"><option value="">User biasa (tanpa tugas)</option>@foreach($tasks as $group => $options)<optgroup label="{{ $group }}">@foreach($options as $value => $option)<option value="{{ $value }}" @selected(old('task') === $value)>{{ $option }}</option>@endforeach</optgroup>@endforeach</select></label>
        <label>Tugas berlaku sampai (WITA)<input type="datetime-local" name="ends_at" form="aktivasi-pegawai" value="{{ old('ends_at', \App\Services\Wita::input(now()->addYear())) }}"></label>
    </div>
    <button class="button primary" form="aktivasi-pegawai">Terapkan ke yang dicentang</button>
</section>
<div class="panel table-panel"><div class="table-wrap"><table><thead><tr><th><input type="checkbox" aria-label="Pilih semua" onchange="document.querySelectorAll('[data-pick]').forEach(box => box.checked = this.checked)"></th><th>{{ $label }}</th><th>Jabatan</th><th>Penempatan</th><th>Tugas</th><th>Status</th><th>Tindakan</th></tr></thead><tbody>
@forelse($employees as $employee)
@php($person = $employee->user)
<tr><td><input type="checkbox" name="employees[]" value="{{ $person->id }}" form="aktivasi-pegawai" data-pick aria-label="Pilih {{ $person->name }}"></td>
<td><strong>{{ $person->name }}</strong><small>{{ $person->username }} · {{ $person->email }}</small></td>
<td>{{ $employee->position ?? '—' }}<small>{{ collect([$employee->rank, $employee->structural_position])->filter()->join(' · ') }}</small></td>
<td>{{ $employee->placement() }}</td>
<td>{{ $person->currentAssignments->map(fn ($assignment) => $assignment->role === 'pembina' ? 'Pendamping '.$assignment->club?->name : ($assignment->position ?: $assignment->roleLabel()))->join(', ') ?: 'User biasa' }}</td>
<td>@if(! $person->active)<span class="badge status-submitted">Menunggu aktivasi</span>@elseif($person->email_verified_at)<span class="badge status-accepted">Sudah masuk</span>@else<span class="badge status-draft">Aktif, belum masuk</span>@endif</td>
<td>@if(! $person->active)<form method="post" action="/kelola/master/pegawai/aktifkan">@csrf<input type="hidden" name="employees[]" value="{{ $person->id }}"><button class="button primary small">Aktifkan</button></form>
@else<form class="inline-form" method="post" action="/kelola/mahasiswa/{{ $person->id }}/status">@csrf<input type="hidden" name="active" value="0"><input name="reason" required minlength="5" maxlength="500" placeholder="Alasan penonaktifan"><button class="button outline small">Nonaktifkan</button></form>
<form method="post" action="/kelola/akun/{{ $person->id }}/sandi-sementara" onsubmit="return confirm('Buat sandi sementara? Sandi lama tidak berlaku lagi.')">@csrf<button class="text-link plain">Buat sandi sementara</button></form>@endif</td></tr>
@empty<tr><td colspan="7" class="empty">Belum ada data {{ strtolower($label) }} sesuai filter.</td></tr>@endforelse
</tbody></table></div></div>
{{ $employees->links() }}
</section>
</x-layout>
