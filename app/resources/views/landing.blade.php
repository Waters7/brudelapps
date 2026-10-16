<x-public title="Bakat, Rekognisi, Unjuk Diri, dan Eksplorasi Lomba">
@php
    $defaults = [
        ['eyebrow' => 'POLTEKKES KEMENKES MANADO', 'title' => 'Temukan bakatmu. Wujudkan prestasimu.', 'caption' => 'BRUDEL — Bakat, Rekognisi, Unjuk Diri, dan Eksplorasi Lomba. Satu tempat untuk info lomba, kegiatan UKM, dan prestasi mahasiswa.', 'url' => '/login', 'label' => 'Masuk ke BRUDEL', 'theme' => 'one', 'icon' => 'spark'],
        ['eyebrow' => 'INFO LOMBA', 'title' => $stats['open'].' lomba sedang membuka pendaftaran', 'caption' => 'Pilih tantangan, siapkan timmu, dan daftar langsung dari BRUDEL.', 'url' => '#info-lomba', 'label' => 'Lihat info lomba', 'theme' => 'two', 'icon' => 'trophy'],
        ['eyebrow' => 'ORGANISASI KEMAHASISWAAN', 'title' => $stats['clubs'].' organisasi siap menyambutmu', 'caption' => 'BEM, MPM, HIMAJU, dan UKM: temukan komunitas untuk bertumbuh bersama.', 'url' => '#organisasi', 'label' => 'Kenali organisasi', 'theme' => 'three', 'icon' => 'users'],
    ];
@endphp
<section class="hero-slider" data-slider aria-roledescription="carousel" aria-label="Sorotan BRUDEL">
    <div class="hero-track" data-slider-track>
    @forelse($slides as $slide)
        <article class="hero-slide" aria-roledescription="slide" aria-label="{{ $loop->iteration }} dari {{ $loop->count }}">
            @if($slide->isVideo())<video class="hero-media" src="{{ $slide->mediaUrl() }}" muted loop playsinline preload="metadata" aria-hidden="true"></video>
            @else<img class="hero-media" src="{{ $slide->mediaUrl() }}" alt="">@endif
            <div class="hero-shade" aria-hidden="true"></div>
            <div class="hero-text">
                <span class="hero-label"><span class="dot"></span> BRUDEL · POLTEKKES KEMENKES MANADO</span>
                <h1>{{ $slide->title }}</h1>
                @if($slide->caption)<p>{{ $slide->caption }}</p>@endif
                <div class="hero-actions">@if($slide->link_url)<a class="button lime" href="{{ $slide->link_url }}">{{ $slide->link_label ?: 'Selengkapnya' }} <x-icon name="arrow"/></a>@endif<a class="hero-link" href="/login">Masuk ke BRUDEL <span>↗</span></a></div>
            </div>
        </article>
    @empty
        @foreach($defaults as $slide)
        <article class="hero-slide theme-{{ $slide['theme'] }}" aria-roledescription="slide" aria-label="{{ $loop->iteration }} dari {{ $loop->count }}">
            <div class="hero-motion" aria-hidden="true"><span class="orb orb-a"></span><span class="orb orb-b"></span><span class="orb orb-c"></span><span class="hero-glyph"><x-icon :name="$slide['icon']"/></span><img src="/brand/logo-mark.svg" alt=""></div>
            <div class="hero-text">
                <span class="hero-label"><span class="dot"></span> {{ $slide['eyebrow'] }}</span>
                <h1>{{ $slide['title'] }}</h1>
                <p>{{ $slide['caption'] }}</p>
                <div class="hero-actions"><a class="button lime" href="{{ $slide['url'] }}">{{ $slide['label'] }} <x-icon name="arrow"/></a><a class="hero-link" href="/live">Live score <span>↗</span></a></div>
            </div>
        </article>
        @endforeach
    @endforelse
    </div>
    <div class="hero-controls"><button type="button" class="hero-arrow" data-slider-prev aria-label="Slide sebelumnya">‹</button><div class="hero-dots" data-slider-dots></div><button type="button" class="hero-arrow" data-slider-next aria-label="Slide berikutnya">›</button></div>
</section>

<div class="kpi-grid landing-stats">
    <div class="kpi stat-tile"><span class="icon-tile teal"><x-icon name="users"/></span><span>Organisasi & UKM aktif</span><strong>{{ $stats['clubs'] }}</strong></div>
    <div class="kpi stat-tile"><span class="icon-tile amber"><x-icon name="trophy"/></span><span>Lomba membuka pendaftaran</span><strong>{{ $stats['open'] }}</strong></div>
    <div class="kpi stat-tile"><span class="icon-tile purple"><x-icon name="star"/></span><span>Prestasi terverifikasi</span><strong>{{ $stats['awards'] }}</strong></div>
</div>

@if($organizations->isNotEmpty())
<section class="org-strip" id="organisasi" aria-label="Organisasi kemahasiswaan">
    <div class="org-marquee">
        @foreach([false, true] as $copy)
        <ul class="org-row" @if($copy) aria-hidden="true" @endif>
            @foreach($organizations as $club)
            <li class="org-chip">@if($club->banner_path)<img src="/banner/{{ $club->id }}?v={{ $club->updated_at?->timestamp }}" alt="">@else<span class="icon-tile {{ $club->tone() }}"><x-icon :name="$club->symbol"/></span>@endif<span><strong>{{ $club->name }}</strong><small>{{ $club->categoryShort() }}{{ $club->field ? ' · '.$club->field : '' }}</small></span></li>
            @endforeach
        </ul>
        @endforeach
    </div>
</section>
@endif

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
            @if($recruitment->club->banner_path)<img class="landing-card-banner" src="/banner/{{ $recruitment->club_id }}?v={{ $recruitment->club->updated_at?->timestamp }}" alt="" loading="lazy">@else<span class="large-icon {{ $recruitment->club->tone() }}"><x-icon :name="$recruitment->club->symbol"/></span>@endif
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
