<x-layout title="Penugasan Peran">
<div class="page-heading"><div><p class="eyebrow">HAK AKSES BERJANGKA</p><h1>Penugasan peran.</h1><p>Satu pengguna dapat memiliki beberapa peran. Akses dibatasi menurut UKM, event, prodi, atau nomor lomba. Ketua UKM tidak otomatis menjadi admin event.</p></div></div>

<section class="panel" x-data="{ role: '{{ old('role', 'pengurus') }}', scopes: @js(collect(config('brudel.roles'))->map(fn ($r) => $r['scope'])) }">
    <h2>Tambah penugasan</h2>
    <form class="form-grid" method="post" action="/kelola/penugasan">@csrf
        <label>Email atau NIM pengguna<input name="user" required maxlength="190" value="{{ old('user') }}" placeholder="nama@email.kampus atau NIM"></label>
        <label>Peran<select name="role" x-model="role" required>@foreach(config('brudel.roles') as $key => $role)<option value="{{ $key }}">{{ $role['label'] }}</option>@endforeach</select></label>
        @foreach(['club_id' => 'UKM', 'event_id' => 'Event', 'program_id' => 'Program studi', 'competition_id' => 'Nomor lomba'] as $column => $label)
        <template x-if="scopes[role] === '{{ $column }}'"><label>Lingkup: {{ $label }}<select name="scope_id" required><option value="">Pilih {{ strtolower($label) }}</option>@foreach($scopes[$column] as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select></label></template>
        @endforeach
        <template x-if="role === 'pengurus'"><label>Jabatan<select name="position"><option>Ketua</option><option>Wakil Ketua</option><option>Sekretaris</option><option>Bendahara</option><option>Koordinator Divisi</option><option>Anggota pengurus</option></select></label></template>
        <label>Periode kepengurusan (opsional)<input name="period" maxlength="30" placeholder="2026/2027" value="{{ old('period') }}"></label>
        <label>Mulai (WITA)<input type="datetime-local" name="starts_at" required value="{{ old('starts_at', now()->timezone('Asia/Makassar')->format('Y-m-d\TH:i')) }}"></label>
        <label>Berakhir (WITA)<input type="datetime-local" name="ends_at" required value="{{ old('ends_at', now()->timezone('Asia/Makassar')->addYear()->format('Y-m-d\TH:i')) }}"></label>
        <div class="span-2"><button class="button primary">Simpan penugasan</button><p class="form-note">Perubahan hak akses dicatat pada audit. Anda tidak dapat mengubah hak akses sendiri.</p></div>
    </form>
</section>

<form class="filters" method="get">
    <label>Peran<select name="role"><option value="">Semua peran</option>@foreach(config('brudel.roles') as $key => $role)<option value="{{ $key }}" @selected(request('role') === $key)>{{ $role['label'] }}</option>@endforeach</select></label>
    <label>Masa<select name="aktif"><option value="">Masih berlaku</option><option value="0" @selected(request('aktif') === '0')>Termasuk berakhir</option></select></label>
    <button class="button outline">Terapkan</button>
</form>
<div class="panel table-panel"><div class="table-wrap"><table><thead><tr><th>Pengguna</th><th>Peran & lingkup</th><th>Masa berlaku (WITA)</th><th>Tindakan</th></tr></thead><tbody>
@forelse($assignments as $a)
<tr><td><strong>{{ $a->user->name }}</strong><small>{{ $a->user->email }}</small></td>
<td>{{ config('brudel.roles')[$a->role]['label'] ?? $a->role }}{{ $a->position ? ' · '.$a->position : '' }}<small>{{ $a->scopeLabel() }}{{ $a->period ? ' · '.$a->period : '' }}</small></td>
<td>{{ \App\Services\Wita::show($a->starts_at, 'd M Y') }} – {{ \App\Services\Wita::show($a->ends_at, 'd M Y') }}<small>{{ $a->isCurrent() ? 'Berlaku' : ($a->ends_at->isPast() ? 'Berakhir'.($a->ended_reason ? ': '.$a->ended_reason : '') : 'Belum mulai') }}</small></td>
<td>@if($a->ends_at->isFuture())<form class="inline-form" method="post" action="/kelola/penugasan/{{ $a->id }}/akhiri">@csrf<input name="reason" required minlength="5" maxlength="500" placeholder="Alasan diakhiri"><button class="button outline small">Akhiri</button></form>@endif</td></tr>
@empty<tr><td colspan="4" class="empty">Belum ada penugasan.</td></tr>@endforelse
</tbody></table></div></div>
{{ $assignments->links() }}
</x-layout>
