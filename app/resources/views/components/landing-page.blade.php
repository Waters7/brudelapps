@props(['title' => 'BRUDEL'])
@php
    $nav = [['#info-lomba', 'Lomba'], ['#organisasi', 'Organisasi'], ['#info-kegiatan', 'Kegiatan'], ['#cara-bergabung', 'Cara bergabung'], ['/live', 'Live score']];
@endphp
<!DOCTYPE html><html lang="id" class="scroll-smooth"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#00B9AD"><meta name="description" content="BRUDEL — Bakat, Rekognisi, Unjuk Diri, dan Eksplorasi Lomba Poltekkes Kemenkes Manado."><title>{{ $title }} · BRUDEL</title><link rel="icon" href="/brand/favicon.ico"><link rel="apple-touch-icon" href="/brand/apple-touch-icon.png"><link rel="manifest" href="/site.webmanifest">@vite(['resources/css/app.css','resources/js/app.js'])@livewireStyles</head>
<body class="landing-page bg-brand-paper text-brand-ink antialiased">
<a class="skip-link" href="#main">Lewati ke konten</a>

<header data-landing-header class="sticky top-0 z-40 border-b border-transparent bg-white/80 backdrop-blur-md transition-shadow duration-300">
    <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:h-20 lg:px-8">
        <a href="/" class="flex items-center gap-2.5">
            <img src="/brand/logo-mark.svg" alt="Logo BRUDEL" class="h-10 w-9 lg:h-12 lg:w-11">
            <span class="leading-none"><strong class="block text-2xl font-extrabold tracking-tight lg:text-[28px]">BRUDEL<span class="text-brand-bright">.</span></strong><small class="hidden text-[7px] font-bold tracking-[0.12em] text-brand-ink/70 sm:block">POLTEKKES KEMENKES MANADO</small></span>
        </a>
        <nav aria-label="Navigasi halaman depan" class="hidden items-center gap-1 lg:flex">
            @foreach($nav as [$href, $label])
            <a href="{{ $href }}" class="rounded-full px-4 py-2 text-sm font-semibold text-brand-ink/75 transition hover:bg-brand-teal/10 hover:text-brand-teal">{{ $label }}</a>
            @endforeach
        </nav>
        <div class="flex items-center gap-2">
            @auth
            <a href="/" class="inline-flex items-center gap-2 rounded-full bg-brand-ink px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-brand-ink/20 transition hover:-translate-y-0.5 hover:bg-brand-teal"><x-icon name="user" class="size-4!"/> Kembali ke BRUDEL</a>
            @else
            <a href="/login" aria-label="Masuk ke BRUDEL" class="inline-flex items-center gap-2 rounded-full bg-brand-ink px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-brand-ink/20 transition hover:-translate-y-0.5 hover:bg-brand-teal"><x-icon name="user" class="size-4!"/> Masuk</a>
            @endauth
            <details class="group relative lg:hidden" data-landing-menu>
                <summary class="flex size-11 cursor-pointer list-none items-center justify-center rounded-full border border-brand-ink/10 bg-white text-brand-ink transition hover:border-brand-teal [&::-webkit-details-marker]:hidden" aria-label="Buka menu halaman"><x-icon name="menu" class="group-open:hidden"/><x-icon name="close" class="hidden group-open:block"/></summary>
                <nav aria-label="Menu halaman depan" class="absolute right-0 top-14 w-60 rounded-2xl border border-brand-ink/10 bg-white p-2 shadow-2xl shadow-brand-ink/15">
                    @foreach($nav as [$href, $label])
                    <a href="{{ $href }}" class="block rounded-xl px-4 py-3 text-sm font-semibold text-brand-ink/80 hover:bg-brand-teal/10 hover:text-brand-teal">{{ $label }}</a>
                    @endforeach
                </nav>
            </details>
        </div>
    </div>
</header>

<main id="main">
<div id="offline-banner" hidden class="alert warning">Anda sedang offline. Data di halaman ini mungkin tidak terbaru.</div>
{{ $slot }}
</main>

<footer class="mt-24 bg-brand-deep text-white/80">
    <div class="mx-auto grid max-w-7xl gap-10 px-4 py-14 sm:px-6 md:grid-cols-[1.4fr_1fr_1fr] lg:px-8">
        <div>
            <a href="/" class="flex items-center gap-2.5"><img src="/brand/logo-mark.svg" alt="" class="h-11 w-10"><strong class="text-2xl font-extrabold tracking-tight text-white">BRUDEL<span class="text-brand-lime">.</span></strong></a>
            <p class="mt-4 max-w-sm text-sm leading-relaxed text-white/70">Bakat, Rekognisi, Unjuk Diri, dan Eksplorasi Lomba. Ruang tumbuh dan berprestasi mahasiswa Poltekkes Kemenkes Manado.</p>
        </div>
        <div>
            <p class="text-xs font-bold tracking-[0.18em] text-brand-lime">JELAJAHI</p>
            <ul class="mt-4 space-y-2 text-sm">@foreach($nav as [$href, $label])<li><a href="{{ $href }}" class="text-white/75 transition hover:text-white">{{ $label }}</a></li>@endforeach</ul>
        </div>
        <div>
            <p class="text-xs font-bold tracking-[0.18em] text-brand-lime">AKUN</p>
            <ul class="mt-4 space-y-2 text-sm">
                <li><a href="/login" class="text-white/75 transition hover:text-white">Masuk</a></li>
                <li><a href="/aktivasi" class="text-white/75 transition hover:text-white">Aktivasi akun</a></li>
                <li><a href="/lupa-sandi" class="text-white/75 transition hover:text-white">Lupa kata sandi</a></li>
            </ul>
        </div>
    </div>
    <div class="border-t border-white/10">
        <div class="mx-auto flex max-w-7xl flex-col gap-2 px-4 py-5 text-xs text-white/60 sm:flex-row sm:justify-between sm:px-6 lg:px-8"><span>© {{ date('Y') }} BRUDEL · Poltekkes Kemenkes Manado</span><span>Sportif Berkompetisi, Unggul Berprestasi.</span></div>
    </div>
</footer>
@livewireScripts</body></html>
