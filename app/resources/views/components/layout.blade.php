@props(['title'=>'Dashboard'])
<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#00B9AD"><meta name="description" content="BRUDEL — Bakat, Rekognisi, Unjuk Diri, dan Eksplorasi Lomba Poltekkes Kemenkes Manado."><title>{{ $title }} · BRUDEL</title><link rel="icon" href="/brand/favicon.ico"><link rel="apple-touch-icon" href="/brand/apple-touch-icon.png"><link rel="manifest" href="/site.webmanifest">@vite(['resources/css/app.css','resources/js/app.js'])@livewireStyles</head>
<body><a class="skip-link" href="#main">Lewati ke konten</a>
<div class="app-shell" x-data="{ menu: false }">
<div class="mobile-shade" x-show="menu" x-cloak @click="menu=false"></div>
<aside class="sidebar" :class="menu && 'is-open'">
    <a class="brand" href="/"><img src="/brand/logo-mark.svg" alt="Logo BRUDEL"><span><strong>BRUDEL<span class="brand-dot">.</span></strong><small>POLTEKKES KEMENKES MANADO</small></span></a>
    <div class="workspace-label"><span class="dot"></span> Ruang mahasiswa <span class="small-arrow">⌄</span></div>
    <p class="nav-caption">MENU UTAMA</p>
    <nav aria-label="Navigasi utama">
    @foreach ([['dashboard','/','grid','Dashboard'],['ukm','/ukm','users','Jelajahi UKM'],['lomba','/lomba','trophy','Jelajahi Lomba'],['applications','/pendaftaran','file','Pendaftaran Saya'],['memberships','/ukm-saya','book','UKM Saya']] as [$route,$url,$icon,$label])
    <a href="{{ $url }}" class="nav-item {{ request()->routeIs($route) || ($url!=='/' && request()->is(ltrim($url,'/').'/*')) ? 'active' : '' }}"><x-icon :name="$icon"/><span>{{ $label }}</span>@if($route==='lomba')<span class="nav-new">BARU</span>@endif</a>
    @endforeach
    @if(auth()->user()->isReviewer())<a href="/tinjauan" class="nav-item {{ request()->routeIs('review')?'active':'' }}"><x-icon name="check"/> Tinjau Pengajuan</a>@endif
    <p class="nav-caption second">LAINNYA</p>
    <a href="/rekomendasi" class="nav-item {{ request()->routeIs('recommendations')?'active':'' }}"><x-icon name="spark"/> Rekomendasi Fitur</a>
    <a href="/profil" class="nav-item {{ request()->routeIs('profile')?'active':'' }}"><x-icon name="user"/> Profil Saya</a>
    </nav>
    <div class="sidebar-bottom"><div class="growth-note"><x-icon name="spark"/><strong>Potensimu, langkah awal<br>prestasi besarmu.</strong><p>Mulai perjalananmu bersama BRUDEL.</p><a href="/ukm">Temukan komunitasmu <span>↗</span></a></div>
    <div class="sidebar-account"><span class="avatar">{{ mb_substr(auth()->user()->name,0,1) }}</span><div><strong>{{ explode(' ',auth()->user()->name)[0] }}</strong><small>{{ auth()->user()->isReviewer()?'Pengelola':'Mahasiswa aktif' }}</small></div><form action="/logout" method="post">@csrf<button class="icon-button" aria-label="Keluar akun"><x-icon name="logout"/></button></form></div></div>
</aside>
<div class="main-shell"><header class="topbar"><div class="crumb"><button class="icon-button mobile-toggle" @click="menu=!menu" aria-label="Buka navigasi"><x-icon name="menu"/></button><span>Ruang Mahasiswa</span><span class="crumb-divider">/</span><strong>{{ $title }}</strong></div><div class="topbar-right"><span class="term">Semester Ganjil 2026/2027</span>@if(config('brudel.demo'))<span class="demo-tag">MODE DEMO</span>@endif<a class="avatar small" href="/profil" aria-label="Profil saya">{{ mb_substr(auth()->user()->name,0,1) }}</a></div></header>
<main id="main" class="content"><div id="offline-banner" hidden class="alert warning">Anda sedang offline. Data pribadi tidak tersedia offline. Sambungkan kembali sebelum mengirim formulir.</div>
@if(session('success'))<div class="alert success" role="status"><x-icon name="check"/>{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert error" role="alert"><strong>Periksa kembali isian Anda.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
{{ $slot }}
<footer class="footer"><span>© {{ date('Y') }} BRUDEL · Poltekkes Kemenkes Manado</span><span>Sportif Berkompetisi, Unggul Berprestasi.</span></footer>
</main></div></div>@livewireScripts</body></html>
