@props(['title' => 'Masuk', 'heading' => 'Selamat datang kembali.', 'intro' => null])
<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#00B9AD"><title>{{ $title }} · BRUDEL</title><link rel="icon" href="/brand/favicon.ico"><link rel="manifest" href="/site.webmanifest">@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="min-h-screen bg-brand-paper text-brand-ink antialiased">
<main class="grid min-h-screen grid-cols-1 lg:grid-cols-[1.05fr_1fr]">
    {{-- Brand story (and, on the sign-in page, public announcements): after the form on phones, beside it on wide screens. --}}
    <section class="relative order-last overflow-hidden bg-gradient-to-br from-brand-deep via-[#0b5a52] to-brand-teal px-6 py-12 text-white sm:px-10 lg:order-first lg:flex lg:min-h-screen lg:flex-col lg:justify-between lg:px-14 lg:py-14">
        <div class="pointer-events-none absolute inset-0" aria-hidden="true">
            <span class="absolute -right-24 -top-24 size-96 animate-float rounded-full bg-brand-bright/25 blur-3xl"></span>
            <span class="absolute -bottom-32 -left-20 size-96 animate-float-slow rounded-full bg-brand-lime/20 blur-3xl"></span>
            <span class="absolute -bottom-40 -right-40 size-[520px] rounded-full border border-white/10"></span>
            <span class="absolute -bottom-24 -right-24 size-[380px] animate-spin-slow rounded-full border-2 border-dashed border-white/10"></span>
        </div>
        <a href="/" class="relative hidden items-center gap-3 lg:flex"><img src="/brand/logo-mark.svg" alt="Logo BRUDEL" class="h-14 w-12 drop-shadow-lg"><span class="leading-none"><strong class="block text-3xl font-extrabold tracking-tight">BRUDEL<span class="text-brand-lime">.</span></strong><small class="text-[8px] font-bold tracking-[0.18em] text-white/70">POLTEKKES KEMENKES MANADO</small></span></a>
        <div class="relative lg:my-10">
            <p class="text-xs font-bold tracking-[0.22em] text-brand-lime">RUANG TUMBUH, RUANG BERPRESTASI</p>
            <h1 class="mt-4 text-3xl font-bold leading-[1.1] tracking-tight text-white sm:text-4xl lg:text-5xl">Potensi hebat.<br>Perjalanan baru.<br><span class="text-brand-lime">Mulai di sini.</span></h1>
            <p class="mt-5 max-w-md text-base leading-relaxed text-white/75">Bakat, Rekognisi, Unjuk Diri, dan Eksplorasi Lomba: info lomba, UKM, kegiatan, dan prestasimu dalam satu tempat.</p>
            @isset($story)
            <div class="mt-8">{{ $story }}</div>
            @else
            <ul class="mt-8 grid max-w-md grid-cols-1 gap-3 sm:grid-cols-3">
                @foreach([['trophy', 'Daftar lomba'], ['users', 'Gabung UKM'], ['star', 'Kumpulkan prestasi']] as [$icon, $label])
                <li class="flex items-center gap-2 rounded-2xl bg-white/10 px-3 py-3 text-sm font-semibold backdrop-blur [&_.icon]:text-brand-lime"><x-icon :name="$icon"/> {{ $label }}</li>
                @endforeach
            </ul>
            @endisset
        </div>
        <p class="relative mt-10 text-[11px] font-semibold tracking-[0.16em] text-white/60 lg:mt-0">SPORTIF BERKOMPETISI, UNGGUL BERPRESTASI.</p>
    </section>

    <section class="flex items-center justify-center px-5 py-10 sm:px-10">
        <div class="auth-card w-full max-w-md">
            <a href="/" class="flex items-center gap-2.5 lg:hidden"><img src="/brand/logo-mark.svg" alt="" class="h-11 w-10"><strong class="text-2xl font-extrabold tracking-tight">BRUDEL<span class="text-brand-bright">.</span></strong></a>
            <div class="mt-8 lg:mt-0">
                <h2 class="text-3xl font-bold tracking-tight text-brand-ink">{{ $heading }}</h2>
                @if($intro)<p class="mt-3 text-sm leading-relaxed text-brand-ink/60">{{ $intro }}</p>@endif
            </div>
            <div class="mt-6">
                @if(session('success'))<div class="alert success" role="status">{{ session('success') }}</div>@endif
                @if($errors->any())<div class="alert error" role="alert">{{ $errors->first() }}</div>@endif
                {{ $slot }}
            </div>
        </div>
    </section>
</main>
<div class="brand-loader" id="brand-loader" role="status" aria-live="polite" hidden>
    <div class="brand-loader-mark"><span class="brand-loader-ring" aria-hidden="true"></span><img src="/brand/logo-mark.svg" alt=""></div>
    <strong>BRUDEL<span class="brand-dot">.</span></strong>
    <p>Menyiapkan ruangmu<span class="brand-loader-dots" aria-hidden="true"><i>.</i><i>.</i><i>.</i></span></p>
</div>
</body></html>
