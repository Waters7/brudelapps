<x-layout :title="'Rekrutmen pengurus '.$club->name">
<a class="back-link" href="/kelola/ukm/{{ $club->id }}">← Kelola {{ $club->name }}</a>
<div class="page-heading"><div><p class="eyebrow">PENGURUS BARU · {{ strtoupper($club->categoryShort()) }}</p><h1>Rekrutmen pengurus.</h1><p>Pengurus lama menyusun kriteria dan memvalidasi mahasiswa yang mengajukan diri.@if($club->category === 'himaju') Hanya mahasiswa Jurusan {{ $club->department?->name }}.@endif</p></div></div>

<section class="section-block"><h2>Daftar rekrutmen</h2>
@forelse($recruitments as $item)
<a class="panel list-row" href="/kelola/rekrutmen-pengurus/{{ $item->id }}"><div><strong>{{ $item->title }}</strong><small>Periode {{ $item->period }} · pendaftaran {{ \App\Services\Wita::show($item->opens_at, 'd M Y') }} – {{ \App\Services\Wita::show($item->closes_at, 'd M Y') }}</small></div><small>{{ $item->isOpen() ? 'Dibuka' : 'Ditutup' }} · {{ $item->waiting_count }} menunggu · {{ $item->accepted_count }} diterima</small></a>
@empty<div class="panel empty"><p>Belum ada rekrutmen pengurus.</p></div>@endforelse
</section>

@if($canManage)
<section class="panel"><h2>Buka rekrutmen baru</h2>
    @include('clubs._officer-recruitment-form', ['action' => '/kelola/ukm/'.$club->id.'/rekrutmen-pengurus'])
</section>
@endif
</x-layout>
