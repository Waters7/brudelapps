<x-layout title="Pengguna per Jurusan">
<div class="page-heading"><div><p class="eyebrow">MASTER DATA</p><h1>Pengguna per jurusan.</h1><p>Akun mahasiswa dikelompokkan menurut jurusan prodinya dan masuk memakai NIM atau email. Ketua dan pengurus memakai akun mahasiswanya, satu orang satu akun.</p></div>
<a class="button outline" href="/kelola/mahasiswa">Mahasiswa & Impor</a></div>

<div class="feature-grid master-grid">
@foreach($groups as $group)
<a class="feature-card master-card {{ $group['selected'] ? 'border-brand-teal!' : '' }}" href="/kelola/master/pengguna?jurusan={{ $group['key'] }}" @if($group['selected']) aria-current="true" @endif>
    <span class="icon-tile teal"><x-icon name="users"/></span>
    <h3>{{ $group['label'] }}</h3>
    <p>{{ number_format($group['total'], 0, ',', '.') }} akun · {{ number_format($group['signed_in'], 0, ',', '.') }} sudah masuk</p>
    @if($group['officers'])<span class="badge status-accepted">{{ $group['officers'] }} ketua & pengurus</span>@endif
</a>
@endforeach
</div>

<section class="section-block">
<div class="section-heading"><h2>{{ $selected ? 'Jurusan '.$selected->name : 'Staf & tanpa jurusan' }} <span class="count-pill">{{ number_format($users->total(), 0, ',', '.') }}</span></h2></div>
<form class="filters" method="get" action="/kelola/master/pengguna">
    <input type="hidden" name="jurusan" value="{{ $selected?->id ?? 'lainnya' }}">
    <label>Cari<input name="q" value="{{ request('q') }}" placeholder="NIM, nama, atau email" maxlength="80"></label>
    @if($selected)<label>Prodi<select name="prodi"><option value="">Semua prodi</option>@foreach($programs as $program)<option value="{{ $program->id }}" @selected(request('prodi') == $program->id)>{{ $program->name }}</option>@endforeach</select></label>@endif
    <label>Status<select name="status"><option value="">Semua</option>@foreach(\App\Http\Controllers\Campus\UserDirectoryController::STATUSES as $value => $label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select></label>
    <button class="button outline">Terapkan</button>
</form>
<div class="panel table-panel"><div class="table-wrap"><table><thead><tr><th>Pengguna</th><th>Prodi</th><th>{{ $selected ? 'Jabatan' : 'Peran' }}</th><th>Status</th><th>Tindakan</th></tr></thead><tbody>
@forelse($users as $person)
@php($roles = $person->nim ? $person->currentAssignments->where('role', 'pengurus')->map(fn ($assignment) => ($assignment->position ?: 'Pengurus').' '.$assignment->club?->name) : $person->currentAssignments->map(fn ($assignment) => $assignment->roleLabel()))
<tr><td><strong>{{ $person->name }}</strong><small>{{ $person->nim ?? $person->username }} · {{ $person->email }}</small></td>
<td>{{ $person->program?->name ?? '—' }}</td>
<td>{{ $roles->join(', ') ?: '—' }}</td>
<td>@if(! $person->active)<x-status value="inactive"/>@elseif($person->email_verified_at)<span class="badge status-accepted">Sudah masuk</span>@else<span class="badge status-submitted">Belum masuk</span>@endif</td>
<td>@if($selected)<a class="text-link whitespace-nowrap" href="/kelola/mahasiswa?q={{ $person->nim }}">Kelola →</a>@endif
@unless($person->is(auth()->user()))<form method="post" action="/kelola/akun/{{ $person->id }}/sandi-sementara" onsubmit="return confirm('Buat sandi sementara? Sandi lama tidak berlaku lagi.')">@csrf<button class="text-link plain whitespace-nowrap">Sandi sementara</button></form>@endunless</td></tr>
@empty<tr><td colspan="5" class="empty">Tidak ada pengguna sesuai filter.</td></tr>@endforelse
</tbody></table></div></div>
{{ $users->links() }}
</section>
</x-layout>
