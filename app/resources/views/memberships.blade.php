<x-layout title="UKM Saya">
<div class="page-heading"><div><p class="eyebrow">TEMAN BERTUMBUH, RUANG BERKARYA</p><h1>Komunitas pilihanmu.</h1><p>Keanggotaan yang diterima beserta divisi dan riwayat statusnya.</p></div><a class="button primary" href="/ukm">Jelajahi UKM →</a></div>
<div class="catalog-grid">
@forelse($memberships as $m)
<article class="panel">
    <span class="large-icon {{ $m->club->tone() }}"><x-icon :name="$m->club->symbol"/></span>
    <h2>{{ $m->club->name }}</h2><p>{{ \Illuminate\Support\Str::limit($m->club->description, 140) }}</p>
    <div class="inline-actions"><x-status :value="$m->status"/>@if($m->division)<span class="chip">Divisi {{ $m->division }}</span>@endif</div>
    <p class="muted">Bergabung {{ \App\Services\Wita::show($m->joined_at, 'd M Y') }}@if($m->left_at) · berakhir {{ \App\Services\Wita::show($m->left_at, 'd M Y') }}@endif</p>
    <x-history :items="$m->histories"/>
    <a class="button outline full" href="/ukm/{{ $m->club_id }}">Lihat profil UKM →</a>
</article>
@empty<div class="panel empty"><h2>Temukan tempatmu bertumbuh.</h2><p>Belum ada keanggotaan. Pendaftaran yang menunggu tinjauan tersedia di Pendaftaran Saya.</p><a href="/ukm" class="text-link">Jelajahi UKM →</a></div>@endforelse
</div>
</x-layout>
