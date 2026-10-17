<x-landing-page title="Bakat, Rekognisi, Unjuk Diri, dan Eksplorasi Lomba">
@php
    $defaults = [
        ['eyebrow' => 'POLTEKKES KEMENKES MANADO', 'title' => 'Temukan bakatmu. Wujudkan prestasimu.', 'caption' => 'BRUDEL — Bakat, Rekognisi, Unjuk Diri, dan Eksplorasi Lomba. Satu tempat untuk info lomba, kegiatan UKM, dan prestasi mahasiswa.', 'url' => '/login', 'label' => 'Masuk ke BRUDEL', 'gradient' => 'from-brand-deep via-brand-teal to-brand-bright', 'icon' => 'star'],
        ['eyebrow' => 'INFO LOMBA', 'title' => $stats['open'].' lomba sedang membuka pendaftaran', 'caption' => 'Pilih tantangan, siapkan timmu, dan daftar langsung dari BRUDEL.', 'url' => '#info-lomba', 'label' => 'Lihat info lomba', 'gradient' => 'from-[#1f2a5a] via-[#3446a3] to-brand-bright', 'icon' => 'trophy'],
        ['eyebrow' => 'ORGANISASI KEMAHASISWAAN', 'title' => $stats['clubs'].' organisasi siap menyambutmu', 'caption' => 'BEM, MPM, HIMAJU, dan UKM: temukan komunitas untuk bertumbuh bersama.', 'url' => '#organisasi', 'label' => 'Kenali organisasi', 'gradient' => 'from-[#3b1f4f] via-[#7a3b8f] to-[#e0794f]', 'icon' => 'users'],
    ];
    $fields = $competitions->pluck('field')->filter()->unique()->values();
    $categories = ['lembaga' => 'BEM & MPM', 'himaju' => 'HIMAJU', 'ukm' => 'UKM'];
    $chip = 'rounded-full border px-4 py-2 text-sm font-semibold transition aria-pressed:border-brand-teal aria-pressed:bg-brand-teal aria-pressed:text-white border-brand-ink/10 bg-white text-brand-ink/75 hover:border-brand-teal hover:text-brand-teal';
@endphp

{{-- Hero: swipeable slides (video, image, or animated defaults). --}}
<section class="mx-auto max-w-7xl px-4 pt-4 sm:px-6 lg:px-8 lg:pt-6">
<div class="relative overflow-hidden rounded-[28px] bg-brand-deep shadow-2xl shadow-brand-deep/25" data-slider aria-roledescription="carousel" aria-label="Sorotan BRUDEL">
    <div class="flex snap-x snap-mandatory overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden" data-slider-track>
    @forelse($slides as $slide)
        <article class="relative flex min-h-[520px] w-full shrink-0 snap-start items-end lg:min-h-[600px]" aria-roledescription="slide" aria-label="{{ $loop->iteration }} dari {{ $loop->count }}">
            @if($slide->isVideo())<video class="absolute inset-0 size-full object-cover" src="{{ $slide->mediaUrl() }}" muted loop playsinline preload="metadata" aria-hidden="true"></video>
            @else<img class="absolute inset-0 size-full object-cover" src="{{ $slide->mediaUrl() }}" alt="">@endif
            <div class="absolute inset-0 bg-gradient-to-t from-brand-deep/95 via-brand-deep/40 to-transparent" aria-hidden="true"></div>
            <div class="relative w-full max-w-3xl p-7 pb-32 sm:p-12 sm:pb-36">
                <span class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1.5 text-[11px] font-bold tracking-[0.16em] text-white/90 backdrop-blur"><span class="size-2 animate-pulse rounded-full bg-brand-lime"></span> BRUDEL · POLTEKKES KEMENKES MANADO</span>
                <h1 class="mt-5 text-4xl font-bold leading-[1.05] tracking-tight text-white sm:text-5xl lg:text-6xl">{{ $slide->title }}</h1>
                @if($slide->caption)<p class="mt-4 max-w-xl text-base leading-relaxed text-white/80 sm:text-lg">{{ $slide->caption }}</p>@endif
                <div class="mt-7 flex flex-wrap items-center gap-3">
                    @if($slide->link_url)<a class="group inline-flex items-center gap-2 rounded-full bg-brand-lime px-6 py-3.5 text-sm font-bold text-brand-ink shadow-lg shadow-brand-lime/30 transition hover:-translate-y-0.5 hover:shadow-xl" href="{{ $slide->link_url }}">{{ $slide->link_label ?: 'Selengkapnya' }} <x-icon name="arrow" class="transition group-hover:translate-x-1"/></a>@endif
                    <a class="inline-flex items-center gap-2 rounded-full px-5 py-3.5 text-sm font-semibold text-white ring-1 ring-white/30 transition hover:bg-white/10" href="/login">Masuk ke BRUDEL <span aria-hidden="true">↗</span></a>
                </div>
            </div>
        </article>
    @empty
        @foreach($defaults as $slide)
        <article class="relative flex min-h-[520px] w-full shrink-0 snap-start items-center overflow-hidden bg-gradient-to-br {{ $slide['gradient'] }} lg:min-h-[600px]" aria-roledescription="slide" aria-label="{{ $loop->iteration }} dari {{ $loop->count }}">
            <div class="pointer-events-none absolute inset-0" aria-hidden="true">
                <span class="absolute -right-24 -top-24 size-96 animate-float rounded-full bg-white/10 blur-2xl"></span>
                <span class="absolute -bottom-32 left-1/3 size-80 animate-float-slow rounded-full bg-brand-lime/20 blur-3xl"></span>
                <span class="absolute right-[12%] top-1/2 hidden size-72 -translate-y-1/2 rounded-full border border-white/15 md:block"></span>
                <span class="absolute right-[16%] top-1/2 hidden size-52 -translate-y-1/2 animate-spin-slow rounded-full border-2 border-dashed border-white/25 md:block"></span>
                <span class="absolute right-[20%] top-1/2 hidden size-32 -translate-y-1/2 animate-float items-center justify-center rounded-[32px] bg-white/15 text-white backdrop-blur md:flex [&_.icon]:size-14!"><x-icon :name="$slide['icon']"/></span>
                <img src="/brand/logo-mark.svg" alt="" class="absolute bottom-8 right-8 hidden w-20 opacity-90 drop-shadow-xl lg:block">
            </div>
            <div class="relative w-full max-w-3xl p-7 pb-32 sm:p-12 sm:pb-36">
                <span class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1.5 text-[11px] font-bold tracking-[0.16em] text-white/90 backdrop-blur"><span class="size-2 animate-pulse rounded-full bg-brand-lime"></span> {{ $slide['eyebrow'] }}</span>
                <h1 class="mt-5 text-4xl font-bold leading-[1.05] tracking-tight text-white sm:text-5xl lg:text-6xl">{{ $slide['title'] }}</h1>
                <p class="mt-4 max-w-xl text-base leading-relaxed text-white/80 sm:text-lg">{{ $slide['caption'] }}</p>
                <div class="mt-7 flex flex-wrap items-center gap-3">
                    <a class="group inline-flex items-center gap-2 rounded-full bg-brand-lime px-6 py-3.5 text-sm font-bold text-brand-ink shadow-lg shadow-black/20 transition hover:-translate-y-0.5 hover:shadow-xl" href="{{ $slide['url'] }}">{{ $slide['label'] }} <x-icon name="arrow" class="transition group-hover:translate-x-1"/></a>
                    <a class="inline-flex items-center gap-2 rounded-full px-5 py-3.5 text-sm font-semibold text-white ring-1 ring-white/30 transition hover:bg-white/10" href="/live">Live score <span aria-hidden="true">↗</span></a>
                </div>
            </div>
        </article>
        @endforeach
    @endforelse
    </div>
    <div class="absolute inset-x-0 bottom-14 flex items-center justify-center gap-4 sm:justify-start sm:px-12">
        <button type="button" class="flex size-10 items-center justify-center rounded-full bg-white/15 text-xl text-white backdrop-blur transition hover:bg-white/30" data-slider-prev aria-label="Slide sebelumnya">‹</button>
        <div class="flex items-center gap-2 [&>button]:h-2.5 [&>button]:w-2.5 [&>button]:rounded-full [&>button]:bg-white/40 [&>button]:transition-all [&>button[aria-current=true]]:w-8 [&>button[aria-current=true]]:bg-brand-lime" data-slider-dots></div>
        <button type="button" class="flex size-10 items-center justify-center rounded-full bg-white/15 text-xl text-white backdrop-blur transition hover:bg-white/30" data-slider-next aria-label="Slide berikutnya">›</button>
    </div>
</div>
</section>

{{-- Counters --}}
<section class="relative z-10 mx-auto -mt-10 max-w-6xl px-4 sm:px-6 lg:px-8" aria-label="BRUDEL dalam angka">
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        @foreach([[$stats['clubs'], 'Organisasi & UKM aktif', 'users', 'bg-brand-teal/10 text-brand-teal'], [$stats['open'], 'Lomba membuka pendaftaran', 'trophy', 'bg-amber-100 text-amber-700'], [$stats['awards'], 'Prestasi terverifikasi', 'star', 'bg-violet-100 text-violet-700']] as [$count, $label, $icon, $tone])
        <div class="flex items-center gap-4 rounded-2xl border border-white bg-white/90 p-5 shadow-xl shadow-brand-ink/5 backdrop-blur transition hover:-translate-y-1 hover:shadow-2xl" data-reveal>
            <span class="flex size-12 shrink-0 items-center justify-center rounded-xl {{ $tone }}"><x-icon :name="$icon"/></span>
            <div><strong class="block text-3xl font-extrabold tracking-tight text-brand-ink tabular-nums" data-count="{{ $count }}">{{ $count }}</strong><span class="text-sm text-brand-ink/60">{{ $label }}</span></div>
        </div>
        @endforeach
    </div>
</section>

{{-- What BRUDEL offers --}}
<section class="mx-auto mt-20 max-w-7xl px-4 sm:px-6 lg:px-8" aria-labelledby="fitur-judul">
    <div class="max-w-2xl" data-reveal>
        <p class="text-xs font-bold tracking-[0.2em] text-brand-teal">SATU WADAH, BERAGAM POTENSI</p>
        <h2 id="fitur-judul" class="mt-3 text-3xl font-bold tracking-tight text-brand-ink sm:text-4xl">Semua kegiatan mahasiswa, dalam satu genggaman.</h2>
    </div>
    <div class="mt-10 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach([
            ['#info-lomba', 'trophy', 'Info & pendaftaran lomba', 'Daftar individu atau tim, unggah persyaratan, dan pantau statusnya.', 'from-amber-50 to-white', 'text-amber-600'],
            ['#organisasi', 'users', 'UKM, HIMAJU, BEM & MPM', 'Kenali organisasi, ikuti rekrutmen, dan aktif di ruang UKM.', 'from-teal-50 to-white', 'text-brand-teal'],
            ['#cara-bergabung', 'star', 'Rekognisi prestasi', 'Prestasi terverifikasi dan sertifikat resmi tersimpan rapi.', 'from-violet-50 to-white', 'text-violet-600'],
            ['/live', 'clock', 'Live score', 'Ikuti skor sementara dan hasil resmi pertandingan kampus.', 'from-sky-50 to-white', 'text-sky-600'],
        ] as [$href, $icon, $title, $text, $bg, $color])
        <a href="{{ $href }}" class="group relative overflow-hidden rounded-3xl border border-brand-ink/5 bg-gradient-to-br {{ $bg }} p-6 transition duration-300 hover:-translate-y-1.5 hover:shadow-2xl hover:shadow-brand-ink/10" data-reveal>
            <span class="flex size-12 items-center justify-center rounded-2xl bg-white shadow-sm {{ $color }} transition group-hover:scale-110 group-hover:rotate-6"><x-icon :name="$icon"/></span>
            <h3 class="mt-5 text-lg font-bold text-brand-ink">{{ $title }}</h3>
            <p class="mt-2 text-sm leading-relaxed text-brand-ink/60">{{ $text }}</p>
            <span class="mt-5 inline-flex items-center gap-1 text-sm font-semibold {{ $color }}">Jelajahi <x-icon name="arrow" class="transition group-hover:translate-x-1"/></span>
        </a>
        @endforeach
    </div>
</section>

{{-- Competitions with field filter --}}
<section class="mx-auto mt-24 max-w-7xl scroll-mt-24 px-4 sm:px-6 lg:px-8" id="info-lomba" data-filter-scope>
    <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between" data-reveal>
        <div>
            <p class="text-xs font-bold tracking-[0.2em] text-brand-teal">KOMPETISI</p>
            <h2 class="mt-3 text-3xl font-bold tracking-tight text-brand-ink sm:text-4xl">Info lomba mahasiswa</h2>
            <p class="mt-2 text-brand-ink/60">Kompetisi yang sedang atau akan membuka pendaftaran.</p>
        </div>
        @if($fields->count() > 1)
        <div class="flex flex-wrap gap-2" role="group" aria-label="Saring bidang lomba">
            <button type="button" class="{{ $chip }}" data-filter-chip="semua" aria-pressed="true">Semua</button>
            @foreach($fields as $field)<button type="button" class="{{ $chip }}" data-filter-chip="{{ \Illuminate\Support\Str::slug($field) }}" aria-pressed="false">{{ $field }}</button>@endforeach
        </div>
        @endif
    </div>
    <div class="mt-8 grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-3">
    @forelse($competitions as $competition)
        @php($daysLeft = (int) ceil(now()->diffInHours($competition->closes_at, false) / 24))
        <article class="group flex flex-col rounded-3xl border border-brand-ink/5 bg-white p-6 shadow-sm transition duration-300 hover:-translate-y-1.5 hover:shadow-2xl hover:shadow-brand-ink/10" data-filter-item="{{ \Illuminate\Support\Str::slug($competition->field) }}" data-reveal>
            <div class="flex items-start justify-between gap-3">
                <span class="rounded-full bg-brand-teal/10 px-3 py-1 text-xs font-bold text-brand-teal">{{ $competition->field }} · {{ $competition->level }}</span>
                @if($competition->opens_at->isFuture())<span class="shrink-0 rounded-full bg-sky-100 px-3 py-1 text-xs font-bold text-sky-700">Segera dibuka</span>
                @else<span class="shrink-0 rounded-full px-3 py-1 text-xs font-bold {{ $daysLeft <= 3 ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700' }}">{{ $daysLeft <= 0 ? 'Tutup hari ini' : 'Sisa '.$daysLeft.' hari' }}</span>@endif
            </div>
            <h3 class="mt-4 text-xl font-bold leading-snug text-brand-ink transition group-hover:text-brand-teal">{{ $competition->name }}</h3>
            <p class="mt-1 text-sm text-brand-ink/55">{{ $competition->event->name }}</p>
            <ul class="mt-5 space-y-2.5 text-sm text-brand-ink/75 [&_.icon]:size-4! [&_.icon]:text-brand-teal">
                <li class="flex items-center gap-2.5"><x-icon name="calendar"/> Pendaftaran {{ $competition->opens_at->isFuture() ? 'dibuka '.\App\Services\Wita::show($competition->opens_at, 'd M Y') : 'ditutup '.\App\Services\Wita::show($competition->closes_at, 'd M Y, H:i').' WITA' }}</li>
                <li class="flex items-center gap-2.5"><x-icon name="users"/> {{ $competition->format === 'team' ? 'Tim '.$competition->min_members.'–'.$competition->max_members.' orang' : 'Individu' }} · {{ $competition->feeLabel() }}</li>
                <li class="flex items-center gap-2.5"><x-icon name="flag"/> {{ \App\Services\Wita::show($competition->starts_at, 'd M Y') }} · {{ $competition->location }}</li>
            </ul>
            <a class="mt-6 inline-flex items-center justify-center gap-2 rounded-2xl bg-brand-ink px-5 py-3 text-sm font-semibold text-white transition hover:bg-brand-teal" href="/lomba/{{ $competition->id }}">Lihat & daftar <x-icon name="arrow" class="transition group-hover:translate-x-1"/></a>
        </article>
    @empty
        <div class="rounded-3xl border border-dashed border-brand-ink/15 bg-white p-10 text-center text-brand-ink/60 md:col-span-2 lg:col-span-3">Belum ada lomba yang dibuka.</div>
    @endforelse
    </div>
    <p class="mt-6 hidden rounded-2xl bg-white p-6 text-center text-sm text-brand-ink/60" data-filter-empty>Tidak ada lomba di bidang ini.</p>
</section>

{{-- Month calendar: competitions, public UKM activities, tanggal merah --}}
<div class="mx-auto mt-24 max-w-7xl px-4 sm:px-6 lg:px-8" data-reveal>
    <x-event-calendar :calendar="$calendar"/>
</div>

{{-- Organizations: category filter + search --}}
@if($organizations->isNotEmpty())
<section class="mt-24 scroll-mt-24 bg-white py-20" id="organisasi" data-filter-scope>
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between" data-reveal>
            <div>
                <p class="text-xs font-bold tracking-[0.2em] text-brand-teal">ORGANISASI KEMAHASISWAAN</p>
                <h2 class="mt-3 text-3xl font-bold tracking-tight text-brand-ink sm:text-4xl">Temukan komunitasmu</h2>
                <p class="mt-2 text-brand-ink/60">{{ $organizations->count() }} organisasi: lembaga kemahasiswaan, himpunan jurusan, dan unit kegiatan.</p>
            </div>
            <label class="relative block w-full lg:w-80">
                <span class="sr-only">Cari organisasi</span>
                <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-brand-ink/40 [&_.icon]:size-4!"><x-icon name="search"/></span>
                <input type="search" placeholder="Cari organisasi…" class="w-full rounded-full border border-brand-ink/10 bg-brand-paper py-3 pl-11 pr-4 text-sm outline-none transition focus:border-brand-teal focus:bg-white focus:ring-4 focus:ring-brand-teal/10" data-filter-search>
            </label>
        </div>
        <div class="mt-6 flex flex-wrap gap-2" role="group" aria-label="Saring jenis organisasi">
            <button type="button" class="{{ $chip }}" data-filter-chip="semua" aria-pressed="true">Semua <span class="ml-1 opacity-60">{{ $organizations->count() }}</span></button>
            @foreach($categories as $key => $label)<button type="button" class="{{ $chip }}" data-filter-chip="{{ $key }}" aria-pressed="false">{{ $label }} <span class="ml-1 opacity-60">{{ $organizations->where('category', $key)->count() }}</span></button>@endforeach
        </div>
        <ul class="mt-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @foreach($organizations as $club)
            <li class="min-w-0" data-filter-item="{{ $club->category }}" data-filter-text="{{ mb_strtolower($club->name.' '.$club->field) }}" data-reveal>
                <a href="/ukm/{{ $club->id }}" class="group flex h-full items-center gap-4 rounded-2xl border border-brand-ink/5 bg-brand-paper p-4 transition duration-300 hover:-translate-y-1 hover:border-brand-teal/30 hover:bg-white hover:shadow-xl hover:shadow-brand-ink/10">
                    @if($club->banner_path)<img src="/banner/{{ $club->id }}?v={{ $club->updated_at?->timestamp }}" alt="" loading="lazy" class="size-14 shrink-0 rounded-xl object-cover">
                    @else<span class="flex size-14 shrink-0 items-center justify-center rounded-xl bg-white text-brand-teal shadow-sm transition group-hover:bg-brand-teal group-hover:text-white"><x-icon :name="$club->symbol"/></span>@endif
                    <span class="min-w-0"><strong class="block truncate text-sm font-bold text-brand-ink">{{ $club->name }}</strong><small class="text-xs text-brand-ink/55">{{ $club->categoryShort() }}{{ $club->field ? ' · '.$club->field : '' }}</small></span>
                </a>
            </li>
            @endforeach
        </ul>
        <p class="mt-6 hidden rounded-2xl bg-brand-paper p-6 text-center text-sm text-brand-ink/60" data-filter-empty>Tidak ada organisasi yang cocok.</p>
    </div>
</section>
@endif

{{-- Activities timeline + open recruitment --}}
<section class="mx-auto mt-24 grid max-w-7xl grid-cols-1 gap-12 px-4 sm:px-6 lg:grid-cols-2 lg:px-8">
    <div class="min-w-0 scroll-mt-24" id="info-kegiatan">
        <div data-reveal>
            <p class="text-xs font-bold tracking-[0.2em] text-brand-teal">JADWAL TERBUKA</p>
            <h2 class="mt-3 text-3xl font-bold tracking-tight text-brand-ink">Info kegiatan mahasiswa</h2>
            <p class="mt-2 text-brand-ink/60">Jadwal latihan dan pertandingan persahabatan UKM yang terbuka.</p>
        </div>
        <ol class="mt-8 space-y-4 border-l-2 border-brand-teal/15 pl-6">
        @forelse($activities as $activity)
            <li class="relative" data-reveal>
                <span class="absolute -left-[33px] top-5 size-4 rounded-full border-4 border-brand-paper bg-brand-teal" aria-hidden="true"></span>
                <article class="flex gap-4 rounded-2xl bg-white p-4 shadow-sm transition hover:shadow-lg">
                    <span class="flex w-16 shrink-0 flex-col items-center justify-center rounded-xl bg-brand-teal/10 py-2 text-brand-teal"><strong class="text-2xl font-extrabold leading-none">{{ \App\Services\Wita::show($activity->starts_at, 'd') }}</strong><small class="text-xs font-bold uppercase">{{ \App\Services\Wita::show($activity->starts_at, 'M') }}</small></span>
                    <div class="min-w-0">
                        <p class="text-xs font-semibold text-brand-ink/50">{{ $activity->club->name }} · {{ $activity->typeLabel() }}</p>
                        <h3 class="mt-0.5 font-bold text-brand-ink">{{ $activity->title }}</h3>
                        <p class="mt-1 text-sm text-brand-ink/60">{{ \App\Services\Wita::show($activity->starts_at, 'D, H:i') }} WITA · {{ $activity->location }}</p>
                    </div>
                </article>
            </li>
        @empty
            <li class="rounded-2xl bg-white p-6 text-sm text-brand-ink/60">Belum ada kegiatan terbuka yang dijadwalkan.</li>
        @endforelse
        </ol>
    </div>
    <div class="min-w-0 scroll-mt-24" id="ukm-baru">
        <div data-reveal>
            <p class="text-xs font-bold tracking-[0.2em] text-brand-teal">REKRUTMEN</p>
            <h2 class="mt-3 text-3xl font-bold tracking-tight text-brand-ink">UKM buka pendaftaran anggota</h2>
            <p class="mt-2 text-brand-ink/60">UKM yang sedang menerima anggota. Pendaftaran UKM gratis.</p>
        </div>
        <div class="mt-8 space-y-4">
        @forelse($recruitments as $recruitment)
            <a href="/ukm/{{ $recruitment->club_id }}" class="group flex items-center gap-4 rounded-2xl bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg" data-reveal>
                @if($recruitment->club->banner_path)<img class="size-16 shrink-0 rounded-xl object-cover" src="/banner/{{ $recruitment->club_id }}?v={{ $recruitment->club->updated_at?->timestamp }}" alt="" loading="lazy">
                @else<span class="flex size-16 shrink-0 items-center justify-center rounded-xl bg-brand-lime/25 text-brand-ink"><x-icon :name="$recruitment->club->symbol"/></span>@endif
                <div class="min-w-0 flex-1">
                    <h3 class="truncate font-bold text-brand-ink group-hover:text-brand-teal">{{ $recruitment->club->name }}</h3>
                    <p class="truncate text-sm text-brand-ink/60">{{ $recruitment->name }} · ditutup {{ \App\Services\Wita::show($recruitment->closes_at, 'd M Y') }}</p>
                </div>
                <span class="shrink-0 rounded-full bg-brand-ink px-3 py-1.5 text-xs font-semibold text-white transition group-hover:bg-brand-teal">Gabung</span>
            </a>
        @empty
            <div class="rounded-2xl bg-white p-6 text-sm text-brand-ink/60">Belum ada UKM yang membuka pendaftaran.</div>
        @endforelse
        </div>
    </div>
</section>

{{-- How to join --}}
<section class="mx-auto mt-24 max-w-7xl scroll-mt-24 px-4 sm:px-6 lg:px-8" id="cara-bergabung">
    <div class="rounded-[32px] bg-brand-ink p-8 text-white sm:p-12">
        <div class="max-w-2xl" data-reveal>
            <p class="text-xs font-bold tracking-[0.2em] text-brand-lime">CARA BERGABUNG</p>
            <h2 class="mt-3 text-3xl font-bold tracking-tight text-white sm:text-4xl">Tiga langkah menuju prestasimu.</h2>
        </div>
        <ol class="mt-10 grid grid-cols-1 gap-5 md:grid-cols-3">
            @foreach([
                ['Masuk dengan NIM', 'Akun mahasiswa sudah disiapkan dari data resmi kampus. Masuk memakai NIM dan kata sandi awal dari kemahasiswaan.'],
                ['Lengkapi akunmu', 'Isi email dan nomor HP yang aktif, lalu buat kata sandi baru milikmu sendiri.'],
                ['Gabung & berkompetisi', 'Ikuti UKM pilihanmu, daftar lomba, dan kumpulkan prestasi yang diakui kampus.'],
            ] as $index => [$title, $text])
            <li class="group rounded-3xl bg-white/5 p-6 ring-1 ring-white/10 transition hover:bg-white/10" data-reveal>
                <span class="flex size-12 items-center justify-center rounded-2xl bg-brand-lime text-xl font-extrabold text-brand-ink transition group-hover:scale-110">{{ $index + 1 }}</span>
                <h3 class="mt-5 text-lg font-bold text-white">{{ $title }}</h3>
                <p class="mt-2 text-sm leading-relaxed text-white/70">{{ $text }}</p>
            </li>
            @endforeach
        </ol>
    </div>
</section>

{{-- FAQ --}}
<section class="mx-auto mt-24 max-w-3xl px-4 sm:px-6" aria-labelledby="faq-judul">
    <h2 id="faq-judul" class="text-center text-3xl font-bold tracking-tight text-brand-ink" data-reveal>Pertanyaan umum</h2>
    <div class="mt-8 space-y-3">
        @foreach([
            ['Siapa yang bisa masuk ke BRUDEL?', 'Mahasiswa aktif Poltekkes Kemenkes Manado yang terdaftar di data resmi kampus, serta pengurus organisasi, pembina, juri, dan kemahasiswaan sesuai penugasan.'],
            ['Saya lupa kata sandi, bagaimana?', 'Gunakan menu Lupa kata sandi di halaman masuk. Tautan pemulihan dikirim ke email yang terdaftar di akunmu.'],
            ['Apakah bergabung dengan UKM berbayar?', 'Tidak. Pendaftaran anggota UKM di BRUDEL gratis. Biaya hanya berlaku untuk lomba tertentu dan tercantum di halaman lombanya.'],
            ['Bagaimana prestasi saya diakui?', 'Juara lomba di BRUDEL tercatat otomatis. Prestasi dari luar kampus dapat diajukan dengan bukti, lalu diverifikasi kemahasiswaan sebelum masuk laporan resmi.'],
        ] as [$question, $answer])
        <details class="group rounded-2xl border border-brand-ink/5 bg-white p-5 shadow-sm open:shadow-lg" data-reveal>
            <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-semibold text-brand-ink [&::-webkit-details-marker]:hidden">{{ $question }}<span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-brand-teal/10 text-lg text-brand-teal transition group-open:rotate-45" aria-hidden="true">+</span></summary>
            <p class="mt-3 text-sm leading-relaxed text-brand-ink/65">{{ $answer }}</p>
        </details>
        @endforeach
    </div>
</section>

{{-- Call to action --}}
<section class="mx-auto mt-24 max-w-7xl px-4 sm:px-6 lg:px-8">
    <div class="relative overflow-hidden rounded-[32px] bg-gradient-to-br from-brand-teal via-brand-bright to-brand-lime p-10 text-center sm:p-14" data-reveal>
        <span class="pointer-events-none absolute -left-16 -top-16 size-64 animate-float rounded-full bg-white/20 blur-2xl" aria-hidden="true"></span>
        <h2 class="relative text-3xl font-bold tracking-tight text-white sm:text-4xl">Siap memulai?</h2>
        <p class="relative mx-auto mt-3 max-w-xl text-white/85">Akun mahasiswa berasal dari data resmi kampus. Masuk sekarang dan mulai perjalanan prestasimu.</p>
        <div class="relative mt-8 flex flex-wrap justify-center gap-3">
            <a class="rounded-full bg-brand-ink px-7 py-3.5 text-sm font-bold text-white shadow-xl transition hover:-translate-y-0.5" href="/login">Masuk</a>
            <a class="rounded-full bg-white/90 px-7 py-3.5 text-sm font-bold text-brand-ink transition hover:-translate-y-0.5 hover:bg-white" href="#cara-bergabung">Cara bergabung</a>
        </div>
    </div>
</section>
</x-landing-page>
