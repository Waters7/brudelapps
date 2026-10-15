<x-public title="Bakat, Rekognisi, Unjuk Diri, dan Eksplorasi Lomba">
<section class="landing-hero">
    <div class="landing-copy">
        <span class="hero-label"><span class="dot"></span> POLTEKKES KEMENKES MANADO</span>
        <h1>Temukan bakatmu.<br>Wujudkan <em>prestasi</em>mu.</h1>
        <p>BRUDEL — Bakat, Rekognisi, Unjuk Diri, dan Eksplorasi Lomba. Satu tempat untuk info lomba, kegiatan UKM, dan prestasi mahasiswa.</p>
        <div class="hero-actions"><a class="button lime" href="/login">Masuk ke BRUDEL <x-icon name="arrow"/></a><a class="hero-link" href="/live">Live score <span>↗</span></a></div>
        <p class="landing-slogan">Sportif Berkompetisi, Unggul Berprestasi.</p>
    </div>
    <div class="landing-art" aria-hidden="true"><img src="/brand/logo-mark.svg" alt=""></div>
</section>

<div class="kpi-grid landing-stats">
    <div class="kpi"><span>Organisasi & UKM aktif</span><strong>{{ $stats['clubs'] }}</strong></div>
    <div class="kpi"><span>Lomba membuka pendaftaran</span><strong>{{ $stats['open'] }}</strong></div>
    <div class="kpi"><span>Prestasi terverifikasi</span><strong>{{ $stats['awards'] }}</strong></div>
</div>

<section class="section-block" id="info-lomba">
    <div class="section-heading"><div><h2>Info lomba mahasiswa</h2><p>Kompetisi yang sedang atau akan membuka pendaftaran.</p></div><a class="text-link" href="/login">Daftar setelah masuk →</a></div>
    <div class="catalog-grid">
    @forelse($competitions as $competition)
        <article class="panel landing-card">
            <span class="eyebrow">{{ $competition->field }} · {{ $competition->level }}</span>
            <h3>{{ $competition->name }}</h3>
            <p class="muted">{{ $competition->event->name }}</p>
            <ul class="plain-list compact-list">
                <li><x-icon name="calendar"/> Pendaftaran {{ $competition->opens_at->isFuture() ? 'dibuka '.\App\Services\Wita::show($competition->opens_at, 'd M Y') : 'ditutup '.\App\Services\Wita::show($competition->closes_at, 'd M Y, H:i').' WITA' }}</li>
                <li><x-icon name="users"/> {{ $competition->format === 'team' ? 'Tim '.$competition->min_members.'–'.$competition->max_members.' orang' : 'Individu' }} · {{ $competition->feeLabel() }}</li>
                <li><x-icon name="flag"/> {{ \App\Services\Wita::show($competition->starts_at, 'd M Y') }} · {{ $competition->location }}</li>
            </ul>
            <a class="button outline full" href="/lomba/{{ $competition->id }}">Lihat & daftar <x-icon name="arrow"/></a>
        </article>
    @empty<div class="panel empty">Belum ada lomba yang dibuka.</div>@endforelse
    </div>
</section>

<section class="section-block" id="info-kegiatan">
    <div class="section-heading"><div><h2>Info kegiatan mahasiswa</h2><p>Jadwal latihan dan pertandingan persahabatan UKM yang terbuka.</p></div></div>
    <div class="catalog-grid">
    @forelse($activities as $activity)
        <article class="panel activity-tile type-{{ $activity->type }}">
            <span class="eyebrow">{{ $activity->club->name }} · {{ $activity->typeLabel() }}</span>
            <h3>{{ $activity->title }}</h3>
            <p><x-icon name="calendar"/> {{ \App\Services\Wita::show($activity->starts_at, 'D, d M Y · H:i') }} WITA</p>
            <p class="muted">{{ $activity->location }}</p>
        </article>
    @empty<div class="panel empty">Belum ada kegiatan terbuka yang dijadwalkan.</div>@endforelse
    </div>
</section>

<section class="section-block" id="ukm-baru">
    <div class="section-heading"><div><h2>Kegiatan UKM baru: buka pendaftaran anggota</h2><p>UKM yang sedang menerima anggota. Pendaftaran UKM gratis.</p></div></div>
    <div class="catalog-grid">
    @forelse($recruitments as $recruitment)
        <article class="panel landing-card">
            <span class="large-icon {{ $recruitment->club->tone() }}"><x-icon :name="$recruitment->club->symbol"/></span>
            <h3>{{ $recruitment->club->name }}</h3>
            <p>{{ \Illuminate\Support\Str::limit($recruitment->club->description, 120) }}</p>
            <p class="muted">{{ $recruitment->name }} · ditutup {{ \App\Services\Wita::show($recruitment->closes_at, 'd M Y') }}</p>
            <a class="button outline full" href="/ukm/{{ $recruitment->club_id }}">Gabung setelah masuk <x-icon name="arrow"/></a>
        </article>
    @empty<div class="panel empty">Belum ada UKM yang membuka pendaftaran.</div>@endforelse
    </div>
</section>

<section class="landing-cta panel"><div><h2>Siap memulai?</h2><p>Akun mahasiswa berasal dari data resmi kampus. Belum aktif? Aktifkan lewat email terdaftar.</p></div>
<div class="inline-actions"><a class="button primary" href="/login">Masuk</a><a class="button outline" href="/aktivasi">Aktivasi akun</a></div></section>
</x-public>
