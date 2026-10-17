<x-layout title="Pengumuman">
<div class="page-heading"><div><p class="eyebrow">PENGUMUMAN KEMAHASISWAAN</p><h1>{{ $broadcast->title }}</h1><p>Pengumuman resmi Kemahasiswaan Direktorat untuk organisasi kemahasiswaan.</p></div></div>
<div class="max-w-3xl">
    <x-broadcast-card :broadcast="$broadcast" :full="true"/>
    <section class="panel mt-6"><h2>Organisasi sasaran <span class="count-pill">{{ $broadcast->clubs->count() }}</span></h2>
        <div class="chip-row">@foreach($broadcast->clubs as $club)<a class="chip" href="/ukm/{{ $club->id }}">{{ $club->name }}</a>@endforeach</div>
    </section>
</div>
</x-layout>
