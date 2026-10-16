<x-layout title="Master Data">
<div class="page-heading"><div><p class="eyebrow">KEMAHASISWAAN DIREKTORAT</p><h1>Master data.</h1><p>Daftar pilihan yang dipakai di seluruh BRUDEL. Perubahan langsung berlaku di form terkait; pilihan yang masih dipakai data lama cukup dinonaktifkan.</p></div></div>

<div class="feature-grid master-grid">
@foreach(\App\Services\Campus\MasterData::TYPES as $type => $spec)
<a class="feature-card master-card" href="/kelola/master/{{ $type }}">
    <span class="icon-tile teal"><x-icon :name="$spec['icon']"/></span>
    <h3>{{ $spec['label'] }}</h3>
    <p>{{ $spec['description'] }}</p>
    <span class="badge status-accepted">{{ $counts[$type]['active'] }} aktif</span>@if($counts[$type]['total'] > $counts[$type]['active']) <span class="badge status-draft">{{ $counts[$type]['total'] - $counts[$type]['active'] }} nonaktif</span>@endif
</a>
@endforeach
<a class="feature-card master-card" href="/kelola/pengaturan#prodi">
    <span class="icon-tile blue"><x-icon name="book"/></span>
    <h3>Jurusan &amp; program studi</h3>
    <p>{{ $departments }} jurusan resmi dan {{ $programs }} program studi. Prodi dipetakan ke jurusan untuk persetujuan lomba, HIMAJU, dan laporan.</p>
    @if($unmapped)<span class="badge status-rejected">{{ $unmapped }} prodi belum dipetakan</span>@else<span class="badge status-accepted">Semua prodi terpetakan</span>@endif
</a>
<a class="feature-card master-card" href="/kelola/pengaturan#rekening">
    <span class="icon-tile amber"><x-icon name="file"/></span>
    <h3>Rekening resmi</h3>
    <p>Rekening tujuan pembayaran lomba berbayar.</p>
    <span class="badge {{ $accounts ? 'status-accepted' : 'status-draft' }}">{{ $accounts }} aktif</span>
</a>
<div class="feature-card master-card is-fixed">
    <span class="icon-tile purple"><x-icon name="trophy"/></span>
    <h3>Tingkat prestasi (IKU)</h3>
    <p>Tetap mengikuti IKU: {{ implode(', ', config('brudel.levels')) }}. Tidak dapat diubah agar laporan IKU konsisten.</p>
    <span class="badge status-draft">Tetap</span>
</div>
</div>
</x-layout>
