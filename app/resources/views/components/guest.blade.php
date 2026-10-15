@props(['title' => 'Masuk', 'heading' => 'Selamat datang kembali.', 'intro' => null])
<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#00B9AD"><title>{{ $title }} · BRUDEL</title><link rel="icon" href="/brand/favicon.ico"><link rel="manifest" href="/site.webmanifest">@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="login-page"><main class="login-layout">
<section class="login-story"><img class="login-logo" src="/brand/logo-mark.svg" alt="Logo BRUDEL"><span class="eyebrow">POLTEKKES KEMENKES MANADO</span><h1>Potensi hebat.<br>Perjalanan baru.<br><em>Mulai di sini.</em></h1><p>Bakat, Rekognisi, Unjuk Diri,<br>dan Eksplorasi Lomba.</p><div class="login-slogan">Sportif Berkompetisi, Unggul Berprestasi.</div></section>
<section class="login-form">
    <a class="brand login-brand" href="/login"><img src="/brand/logo-mark.svg" alt=""><strong>BRUDEL<span class="brand-dot">.</span></strong></a>
    <h2>{{ $heading }}</h2>
    @if($intro)<p>{{ $intro }}</p>@endif
    @if(session('success'))<div class="alert success" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert error" role="alert">{{ $errors->first() }}</div>@endif
    {{ $slot }}
</section>
</main></body></html>
