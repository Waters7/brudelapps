<x-layout title="Penilaian Juri">
<div class="page-heading"><div><p class="eyebrow">RUANG PENILAI</p><h1>Penugasan penilaian.</h1><p>Anda hanya dapat menilai nomor lomba sesuai penugasan aktif.</p></div></div>
<div class="catalog-grid">
@forelse($competitions as $c)
<a class="panel" href="/penilaian/{{ $c->id }}"><span class="eyebrow">{{ $c->event->name }}</span><h2>{{ $c->name }}</h2><p>{{ $c->accepted_count }} peserta diterima · {{ $c->resultsPublished() ? 'Hasil sudah disahkan' : 'Penilaian terbuka' }}</p><span class="text-link">Buka lembar nilai →</span></a>
@empty<div class="panel empty">Belum ada penugasan penilaian.</div>@endforelse
</div>
</x-layout>
