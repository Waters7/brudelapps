<x-dynamic-component :component="auth()->check() ? 'layout' : 'public'" title="Live Score">
<div class="page-heading"><div><p class="eyebrow">LIVE SCORE</p><h1>Pertandingan & penampilan.</h1><p>Skor sementara diperbarui operator berkala. Hasil resmi ditandai setelah disahkan.</p></div></div>
<div class="catalog-grid">
@forelse($competitions as $c)
<a class="panel" href="/live/{{ $c->id }}">
    <span class="eyebrow">{{ $c->event->name }} · {{ $c->field }}</span><h2>{{ $c->name }}</h2>
    <p>{{ \App\Services\Wita::show($c->starts_at) }} WITA · {{ $c->location }}</p>
    <div class="inline-actions"><span class="badge {{ $c->resultsPublished() ? 'status-accepted' : 'status-submitted' }}">{{ $c->resultsPublished() ? 'Hasil resmi' : 'Sementara' }}</span><span class="chip">{{ $c->live_visibility === 'public' ? 'Publik' : 'Khusus pengguna masuk' }}</span></div>
</a>
@empty<div class="panel empty">Belum ada live score yang tersedia{{ auth()->check() ? '' : ' untuk publik' }}.</div>@endforelse
</div>
</x-dynamic-component>
