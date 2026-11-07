<x-layout title="Penyimpanan">
@php($human = fn (int $bytes) => \App\Services\Campus\StorageMonitor::human($bytes))
<div class="page-heading"><div><p class="eyebrow">PENGELOLAAN SISTEM</p><h1>Penyimpanan.</h1><p>Kapasitas disk server dan bagian yang dipakai BRUDEL. Kemahasiswaan mendapat notifikasi saat pemakaian melewati ambang.</p></div></div>

@if($disk['level'] !== 'ok')
<div class="alert {{ $disk['level'] === 'critical' ? 'error' : 'warning' }}"><strong>{{ \App\Services\Campus\StorageMonitor::LEVELS[$disk['level']] }}:</strong> disk terpakai {{ $disk['percent'] }}%, sisa {{ $human($disk['free']) }}. Hapus cadangan lama yang sudah diunduh, atau tambah kapasitas server.</div>
@endif

<div class="kpi-grid">
    <div class="kpi"><span>Disk server terpakai</span><strong>{{ $disk['percent'] }}%</strong><small>{{ $human($disk['used']) }} dari {{ $human($disk['total']) }}</small></div>
    <div class="kpi"><span>Sisa disk server</span><strong>{{ $human($disk['free']) }}</strong><small>Status: {{ \App\Services\Campus\StorageMonitor::LEVELS[$disk['level']] }}</small></div>
    <div class="kpi"><span>Dipakai BRUDEL</span><strong>{{ $human(array_sum($application)) }}</strong><small>{{ $disk['total'] ? round(array_sum($application) * 100 / $disk['total'], 2) : 0 }}% dari disk server</small></div>
</div>

<section class="panel">
    <h2>Pemakaian disk server</h2>
    <div class="space-progress" role="progressbar" aria-valuenow="{{ $disk['percent'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="Pemakaian disk server"><i style="width: {{ min(100, $disk['percent']) }}%; background: {{ $disk['level'] === 'critical' ? '#c2410c' : ($disk['level'] === 'warning' ? '#ca8a04' : '') }}"></i></div>
    <p class="form-note">Peringatan pada {{ $thresholds['warning'] }}%, kritis pada {{ $thresholds['critical'] }}%.</p>
</section>

<div class="detail-columns">
<section class="panel">
    <h2>Rincian BRUDEL</h2>
    <table><tbody>@foreach($application as $label => $bytes)<tr><td>{{ $label }}</td><td>{{ $human($bytes) }}</td></tr>@endforeach</tbody></table>
    <p class="form-note">Ukuran folder dihitung ulang setiap 10 menit. <a class="text-link" href="/kelola/sistem/penyimpanan?hitung=1">Hitung sekarang</a> · <a class="text-link" href="/kelola/sistem/backup">Kelola cadangan</a></p>
</section>
<section class="panel">
    <h2>Ambang peringatan dini</h2>
    <form class="form-grid" method="post" action="/kelola/sistem/penyimpanan">@csrf
        <label>Peringatan (%)<input type="number" name="warning" required min="50" max="95" value="{{ old('warning', $thresholds['warning']) }}"></label>
        <label>Kritis (%)<input type="number" name="critical" required min="55" max="99" value="{{ old('critical', $thresholds['critical']) }}"></label>
        <div class="span-2"><button class="button outline">Simpan ambang</button><p class="form-note">Pemeriksaan otomatis berjalan setiap jam. Notifikasi dikirim sekali sehari selama pemakaian di atas ambang, dan langsung bila naik ke kritis.</p></div>
    </form>
</section>
</div>
</x-layout>
