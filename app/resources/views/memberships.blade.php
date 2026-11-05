<x-layout title="UKM Saya">
<div class="page-heading"><div><p class="eyebrow">TEMAN BERTUMBUH, RUANG BERKARYA</p><h1>Komunitas pilihanmu.</h1><p>Keanggotaan yang diterima beserta divisi dan riwayat statusnya.</p></div><a class="button primary" href="/ukm">Jelajahi UKM →</a></div>
@if($pending->isNotEmpty())
<section class="section-block"><h2>Menunggu <span class="count-pill">{{ $pending->count() }}</span></h2>
<div class="catalog-grid">
@foreach($pending as $item)
<article class="panel">
    <span class="large-icon {{ $item->club->tone() }}"><x-icon :name="$item->club->symbol"/></span>
    <h2>{{ $item->club->name }}</h2>
    @if($item->isInvitation())
    <p>{{ $item->creator->name }} mengundangmu bergabung.</p>
    @if($item->message)<p class="muted prewrap">{{ $item->message }}</p>@endif
    <form class="inline-actions" method="post" action="/undangan-organisasi/{{ $item->id }}">@csrf<button class="button primary small" name="decision" value="accepted">Terima</button><button class="button outline small" name="decision" value="declined">Tolak</button></form>
    @else
    <p>Permintaan bergabungmu menunggu tinjauan pengurus.</p>
    <form method="post" action="/permintaan-gabung/{{ $item->id }}/batal">@csrf<button class="text-link plain">Batalkan permintaan</button></form>
    @endif
</article>
@endforeach
</div></section>
@endif
<div class="catalog-grid">
@forelse($memberships as $m)
<article class="panel">
    <span class="large-icon {{ $m->club->tone() }}"><x-icon :name="$m->club->symbol"/></span>
    <h2>{{ $m->club->name }}</h2><p>{{ \Illuminate\Support\Str::limit($m->club->description, 140) }}</p>
    <div class="inline-actions"><x-status :value="$m->status"/>@if($m->division)<span class="chip">Divisi {{ $m->division }}</span>@endif</div>
    <p class="muted">Bergabung {{ \App\Services\Wita::show($m->joined_at, 'd M Y') }}@if($m->left_at) · berakhir {{ \App\Services\Wita::show($m->left_at, 'd M Y') }}@endif</p>
    <x-history :items="$m->histories"/>
    @if($m->status === 'active')<a class="button primary full" href="/ruang/{{ $m->club_id }}">Masuk ruang organisasi →</a>@endif<a class="text-link" href="/ukm/{{ $m->club_id }}">Lihat profil →</a>
</article>
@empty<div class="panel empty"><h2>Temukan tempatmu bertumbuh.</h2><p>Belum ada keanggotaan. Pendaftaran yang menunggu tinjauan tersedia di Pendaftaran Saya.</p><a href="/ukm" class="text-link">Jelajahi UKM →</a></div>@endforelse
</div>
</x-layout>
