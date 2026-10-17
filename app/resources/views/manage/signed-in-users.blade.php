<x-layout title="Pengguna Login">
<div class="page-heading"><div><p class="eyebrow">PENGATURAN</p><h1>Pengguna login.</h1><p>Pengguna yang sedang masuk ke BRUDEL dan riwayat masuk terbaru. Sesi berlaku {{ config('session.lifetime') }} menit sejak aktivitas terakhir; "Online" berarti aktif dalam {{ \App\Http\Controllers\Account\SignedInUserController::ONLINE_MINUTES }} menit terakhir.</p></div></div>

<div class="stats-grid">
@foreach([[$online, 'Online sekarang', 'Aktif '.\App\Http\Controllers\Account\SignedInUserController::ONLINE_MINUTES.' menit terakhir', 'user', 'teal'], [$signedIn->count(), 'Sesi berlaku', 'Masih login di perangkat', 'users', 'blue'], [$todayLogins, 'Login hari ini', 'Sejak pukul 00.00 WITA', 'clock', 'lime']] as [$count, $label, $caption, $icon, $color])
<div class="stat-card"><div class="stat-top"><span class="icon-tile {{ $color }}"><x-icon :name="$icon"/></span></div><div class="stat-number">{{ str_pad($count, 2, '0', STR_PAD_LEFT) }} <span>{{ $label }}</span></div><p>{{ $caption }}</p></div>
@endforeach
</div>

<form class="filters" method="get">
    <label>Cari pengguna<input name="cari" value="{{ $search }}" maxlength="80" placeholder="Nama, NIM, username, atau email"></label>
    <button class="button outline">Cari</button>
</form>

<section class="section-block">
<h2>Sedang login <span class="count-pill">{{ $signedIn->count() }}</span></h2>
<div class="panel table-panel"><div class="table-wrap"><table><thead><tr><th>Pengguna</th><th>Peran</th><th>Perangkat</th><th>Aktivitas terakhir (WITA)</th></tr></thead><tbody>
@forelse($signedIn as $row)
<tr><td><strong>{{ $row['user']->name }}</strong><small>{{ $row['user']->nim ?? $row['user']->username ?? $row['user']->email }}</small></td>
<td>{{ $row['user']->currentAssignments->first()?->roleLabel() ?? ($row['user']->nim ? 'Mahasiswa' : 'Pengguna') }}</td>
<td>{{ $row['device'] }}<small>{{ $row['ip'] }}{{ $row['devices'] > 1 ? ' · '.$row['devices'].' sesi' : '' }}</small></td>
<td>@if($row['last']->gt(now()->subMinutes(\App\Http\Controllers\Account\SignedInUserController::ONLINE_MINUTES)))<span class="badge status-accepted">Online</span>@endif {{ \App\Services\Wita::show($row['last'], 'd M H:i') }}<small>{{ $row['last']->locale('id')->diffForHumans() }}</small></td></tr>
@empty<tr><td colspan="4" class="empty">Tidak ada pengguna yang sedang login{{ $search !== '' ? ' sesuai pencarian' : '' }}.</td></tr>@endforelse
</tbody></table></div></div>
</section>

<section class="section-block">
<h2>Riwayat login</h2>
<div class="panel table-panel"><div class="table-wrap"><table><thead><tr><th>Waktu (WITA)</th><th>Pengguna</th><th>Perangkat</th></tr></thead><tbody>
@forelse($history as $login)
@php($details = json_decode($login->details, true) ?: [])
<tr><td>{{ \App\Services\Wita::show(\Illuminate\Support\Carbon::parse($login->created_at), 'd M Y H:i') }}</td>
<td><strong>{{ $login->name }}</strong><small>{{ $login->nim ?? $login->username ?? $login->email }}</small></td>
<td>{{ \App\Services\Account\AuditLogService::device($details['agent'] ?? null) }}<small>{{ $details['ip'] ?? '—' }}</small></td></tr>
@empty<tr><td colspan="3" class="empty">Belum ada riwayat login{{ $search !== '' ? ' sesuai pencarian' : '' }}.</td></tr>@endforelse
</tbody></table></div></div>
{{ $history->links() }}
</section>
</x-layout>
