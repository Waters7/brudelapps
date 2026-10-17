@props(['title' => 'BRUDEL'])
<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#00B9AD"><meta name="robots" content="noindex"><title>{{ $title }} · BRUDEL</title><link rel="icon" href="/brand/favicon.ico"><link rel="manifest" href="/site.webmanifest">@vite(['resources/css/app.css','resources/js/app.js'])@livewireStyles</head>
<body class="public-page">
<header class="public-header"><a class="brand" href="/"><img src="/brand/logo-mark.svg" alt="Logo BRUDEL"><span><strong>BRUDEL<span class="brand-dot">.</span></strong><small>POLTEKKES KEMENKES MANADO</small></span></a>@auth<a class="button outline" href="/"><x-icon name="user"/> Kembali ke BRUDEL</a>@else<a class="button outline" href="/login" aria-label="Masuk ke BRUDEL"><x-icon name="user"/> Masuk</a>@endauth</header>
<main id="main" class="content public-content"><div id="offline-banner" hidden class="alert warning">Anda sedang offline. Data di halaman ini mungkin tidak terbaru.</div>{{ $slot }}
<footer class="footer"><span>© {{ date('Y') }} BRUDEL · Poltekkes Kemenkes Manado</span><span>Sportif Berkompetisi, Unggul Berprestasi.</span></footer></main>
@livewireScripts</body></html>
