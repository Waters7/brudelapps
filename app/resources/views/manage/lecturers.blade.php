<x-layout title="Dosen">
<div class="page-heading"><div><p class="eyebrow">MASTER DATA</p><h1>Dosen.</h1><p>Akun dosen dari Daftar Urut Kepangkatan. Dosen masuk memakai NIP atau email, dan baru dapat masuk setelah diaktifkan di sini.</p></div></div>

<div class="kpi-grid">
    <div class="kpi"><span>Dosen</span><strong>{{ $counts['total'] }}</strong></div>
    <div class="kpi"><span>Menunggu aktivasi</span><strong>{{ $counts['waiting'] }}</strong></div>
    <div class="kpi"><span>Aktif</span><strong>{{ $counts['active'] }}</strong></div>
    <div class="kpi"><span>Sudah masuk</span><strong>{{ $counts['signed_in'] }}</strong></div>
</div>

<section class="section-block">
<form class="filters" method="get" action="/kelola/master/dosen">
    <label>Cari<input name="q" value="{{ request('q') }}" placeholder="Nama, NIP, atau email" maxlength="80"></label>
    <label>Penempatan<select name="jurusan"><option value="">Semua</option>@foreach($departments as $department)<option value="{{ $department->id }}" @selected(request('jurusan') == $department->id)>{{ $department->name }}</option>@endforeach<option value="unit" @selected(request('jurusan') === 'unit')>Direktorat / Unit</option></select></label>
    <label>Status<select name="status"><option value="">Semua</option><option value="menunggu" @selected(request('status') === 'menunggu')>Menunggu aktivasi</option><option value="aktif" @selected(request('status') === 'aktif')>Aktif</option></select></label>
    <button class="button outline">Terapkan</button>
</form>
<form id="aktivasi-dosen" method="post" action="/kelola/master/dosen/aktifkan" onsubmit="return confirm('Aktifkan dosen yang dicentang? Mereka dapat masuk memakai NIP.')">@csrf</form>
@if($counts['waiting'])<button class="button primary" form="aktivasi-dosen">Aktifkan yang dicentang</button>@endif
<div class="panel table-panel"><div class="table-wrap"><table><thead><tr><th><input type="checkbox" aria-label="Pilih semua yang menunggu" onchange="document.querySelectorAll('[data-pick]').forEach(box => box.checked = this.checked)"></th><th>Dosen</th><th>Jabatan</th><th>Penempatan</th><th>Status</th><th>Tindakan</th></tr></thead><tbody>
@forelse($lecturers as $lecturer)
@php($person = $lecturer->user)
<tr><td>@unless($person->active)<input type="checkbox" name="lecturers[]" value="{{ $person->id }}" form="aktivasi-dosen" data-pick aria-label="Pilih {{ $person->name }}">@endunless</td>
<td><strong>{{ $person->name }}</strong><small>{{ $person->username }} · {{ $person->email }}</small></td>
<td>{{ $lecturer->position ?? '—' }}<small>{{ collect([$lecturer->rank, $lecturer->structural_position])->filter()->join(' · ') }}</small></td>
<td>{{ $lecturer->placement() }}</td>
<td>@if(! $person->active)<span class="badge status-submitted">Menunggu aktivasi</span>@elseif($person->email_verified_at)<span class="badge status-accepted">Sudah masuk</span>@else<span class="badge status-draft">Aktif, belum masuk</span>@endif</td>
<td>@if(! $person->active)<form method="post" action="/kelola/master/dosen/aktifkan">@csrf<input type="hidden" name="lecturers[]" value="{{ $person->id }}"><button class="button primary small">Aktifkan</button></form>
@else<form class="inline-form" method="post" action="/kelola/mahasiswa/{{ $person->id }}/status">@csrf<input type="hidden" name="active" value="0"><input name="reason" required minlength="5" maxlength="500" placeholder="Alasan penonaktifan"><button class="button outline small">Nonaktifkan</button></form>
<form method="post" action="/kelola/akun/{{ $person->id }}/sandi-sementara" onsubmit="return confirm('Buat sandi sementara? Sandi lama tidak berlaku lagi.')">@csrf<button class="text-link plain">Buat sandi sementara</button></form>@endif</td></tr>
@empty<tr><td colspan="6" class="empty">Belum ada data dosen sesuai filter.</td></tr>@endforelse
</tbody></table></div></div>
{{ $lecturers->links() }}
</section>
</x-layout>
