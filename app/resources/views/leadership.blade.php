<x-layout title="Dashboard Pimpinan">
<div class="page-heading"><div><p class="eyebrow">RUANG PIMPINAN{{ $position ? ' · '.strtoupper($position) : '' }}</p><h1>Ringkasan prestasi & delegasi.</h1><p>Laporan prestasi mahasiswa sesuai IKU dan notifikasi delegasi yang memerlukan perhatian.</p></div>
<form class="inline-form" method="get"><label>Tahun<input type="number" name="year" min="2000" max="2100" value="{{ $filters['year'] }}"></label><button class="button outline small">Tampilkan</button></form></div>

<div class="kpi-grid">
    <div class="kpi"><span>Penghargaan {{ $filters['year'] }}</span><strong>{{ $summary['award_count'] }}</strong><small>{{ $summary['team_awards'] }} penghargaan tim</small></div>
    <div class="kpi"><span>Mahasiswa berprestasi unik</span><strong>{{ $summary['unique_achievers'] }}</strong></div>
    <div class="kpi"><span>Peserta lomba diterima</span><strong>{{ $summary['participant_entries'] }}</strong><small>{{ $summary['unique_participants'] }} mahasiswa unik</small></div>
    <div class="kpi"><span>Anggota UKM aktif</span><strong>{{ $summary['unique_members'] }}</strong><small>{{ $summary['membership_count'] }} keanggotaan</small></div>
    <a class="kpi {{ $pending->isNotEmpty() ? 'has-task' : '' }}" href="/delegasi"><span>Usulan delegasi menunggu</span><strong>{{ $pending->count() }}</strong></a>
</div>

@include('reports._iku', ['iku' => $iku, 'year' => $filters['year']])

<div class="detail-columns">
<section class="panel"><h2>Usulan delegasi menunggu keputusan</h2>
@forelse($pending as $delegation)
    <a class="list-row" href="/delegasi/{{ $delegation->id }}"><div><strong>{{ $delegation->title }}</strong><small>{{ $delegation->destination_event }} · {{ config('brudel.levels')[$delegation->level] }} · diajukan {{ \App\Services\Wita::show($delegation->submitted_at, 'd M Y') }}</small></div>
    <div class="avatar-stack">@foreach($delegation->members->take(5) as $member)<x-avatar :user="$member->user"/>@endforeach</div></a>
@empty<p class="muted">Tidak ada usulan yang menunggu.</p>@endforelse
</section>
<section class="panel"><h2>Notifikasi delegasi</h2>
@forelse($notifications as $n)
    <a class="activity" href="{{ $n->data['url'] ?? '/delegasi' }}"><span class="activity-dot"></span><div><strong>{{ $n->data['title'] ?? 'Delegasi' }}</strong><p>{{ $n->data['body'] ?? '' }}</p><small>{{ \App\Services\Wita::show($n->created_at, 'd M Y · H:i') }} WITA</small></div></a>
@empty<p class="muted">Belum ada notifikasi delegasi.</p>@endforelse
</section>
</div>
<div class="inline-actions"><a class="button primary" href="/laporan?year={{ $filters['year'] }}">Buka laporan lengkap</a><a class="button outline" href="/laporan/ekspor/iku?year={{ $filters['year'] }}">CSV IKU per jurusan ↓</a></div>
</x-layout>
