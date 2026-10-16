<x-layout title="Penilaian Juri">
<div class="page-heading"><div><p class="eyebrow">RUANG PENILAI</p><h1>Penugasan penilaian.</h1><p>Anda hanya dapat menilai nomor lomba sesuai penugasan aktif. Nilai Anda hanya terlihat oleh Anda dan pengelola lomba.</p></div></div>
<div class="catalog-grid">
@forelse($competitions as $c)
@php
    [$badge, $state] = match (true) {
        $c->resultsPublished() => ['status-draft', 'Hasil sudah disahkan · nilai terkunci'],
        $c->criteria_count === 0 => ['status-submitted', 'Menunggu rubrik dari pengelola lomba'],
        $c->accepted_count === 0 => ['status-submitted', 'Belum ada peserta diterima'],
        default => ['status-accepted', 'Penilaian terbuka'],
    };
@endphp
<a class="panel" href="/penilaian/{{ $c->id }}"><span class="eyebrow">{{ $c->event->name }}</span><h2>{{ $c->name }}</h2><span class="badge {{ $badge }}">{{ $state }}</span><p>{{ $c->accepted_count }} peserta diterima · {{ $c->criteria_count }} kriteria · {{ $c->my_scores }} nilai Anda tersimpan</p><span class="text-link">Buka lembar nilai →</span></a>
@empty<div class="panel empty">Belum ada penugasan penilaian.</div>@endforelse
</div>
</x-layout>
