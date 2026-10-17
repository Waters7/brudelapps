<x-layout title="Audit Log">
<div class="page-heading"><div><p class="eyebrow">PENGATURAN</p><h1>Audit log.</h1><p>Seluruh tindakan yang tercatat, dikelompokkan menurut penugasan pelaku pada saat tindakan dilakukan. Satu pengguna dengan beberapa peran muncul di setiap kelompoknya.</p></div></div>

<section class="panel">
<h2>Kelompok penugasan <small class="muted">· 30 hari terakhir</small></h2>
<div class="chip-row">
    <a class="chip {{ $group === null ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['kelompok' => null, 'page' => null]) }}">Semua</a>
    @foreach($groups as $key => $label)
    <a class="chip {{ $group === $key ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['kelompok' => $key, 'page' => null]) }}" @if($group === $key) aria-current="true" @endif>{{ $label }} <strong>{{ $counts[$key] ?? 0 }}</strong></a>
    @endforeach
</div>
</section>

<form class="filters" method="get">
    @if($group)<input type="hidden" name="kelompok" value="{{ $group }}">@endif
    <label>Modul<select name="modul"><option value="">Semua modul</option>@foreach($modules as $key => $label)<option value="{{ $key }}" @selected($module === $key)>{{ $label }}</option>@endforeach</select></label>
    <label>Cari pelaku<input name="cari" value="{{ $search }}" maxlength="80" placeholder="Nama, NIM, username, atau email"></label>
    <button class="button outline">Terapkan</button>
</form>

<section class="section-block">
<h2>{{ $group ? $groups[$group] : 'Semua kelompok' }} <span class="count-pill">{{ $entries->total() }}</span></h2>
<div class="panel table-panel"><div class="table-wrap"><table><thead><tr><th>Waktu (WITA)</th><th>Pelaku & penugasan</th><th>Tindakan</th><th>Rincian</th></tr></thead><tbody>
@forelse($entries as $entry)
<tr><td>{{ \App\Services\Wita::show(\Illuminate\Support\Carbon::parse($entry->created_at), 'd M Y H:i') }}</td>
<td>@if($entry->user_id)<strong>{{ $entry->name }}</strong><small>{{ $entry->nim ?? $entry->username ?? $entry->email }}</small><small>{{ $entry->roles->isNotEmpty() ? $entry->roles->join(', ') : ($entry->nim ? 'Mahasiswa' : 'Tanpa penugasan') }}</small>@else<strong>Sistem</strong><small>Tugas terjadwal / perintah server</small>@endif</td>
<td>{{ $entry->label }}<small><code>{{ $entry->action }}</code> · {{ $entry->subject_type }} #{{ $entry->subject_id ?? '—' }}</small></td>
<td><small>{{ \Illuminate\Support\Str::limit($entry->summary, 220) ?: '—' }}</small></td></tr>
@empty<tr><td colspan="4" class="empty">Belum ada catatan untuk kelompok ini.</td></tr>@endforelse
</tbody></table></div></div>
{{ $entries->links() }}
</section>
</x-layout>
