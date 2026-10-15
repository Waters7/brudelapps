@props(['title'=>'Dashboard'])
@php
    $me = auth()->user();
    $roles = $me->activeRoles();
    $has = fn (string ...$names) => $roles->intersect($names)->isNotEmpty();
    $staff = $roles->isNotEmpty();
    $unread = $me->unreadNotifications()->count();
    $sections = [
        'MENU UTAMA' => array_filter([
            ['/', 'grid', 'Dashboard'],
            ['/ukm', 'users', 'Jelajahi UKM'],
            ['/lomba', 'trophy', 'Jelajahi Lomba'],
            $me->nim ? ['/pendaftaran', 'file', 'Pendaftaran Saya'] : null,
            $me->nim ? ['/ukm-saya', 'book', 'UKM Saya'] : null,
            $me->nim ? ['/sertifikat', 'file', 'Sertifikat Saya'] : null,
            $me->nim ? ['/prestasi', 'trophy', 'Prestasi Saya'] : null,
            ['/live', 'clock', 'Live Score'],
            ['/delegasi', 'globe', 'Delegasi'],
        ]),
        'PENGELOLAAN' => array_filter([
            $me->isReviewer() ? ['/tinjauan', 'check', 'Tinjau Pengajuan'] : null,
            $has('kemahasiswaan', 'pengurus') ? ['/kelola/ukm', 'users', 'Kelola UKM'] : null,
            $has('kemahasiswaan', 'admin_event', 'pengurus') ? ['/kelola/event', 'calendar', 'Kelola Event & Lomba'] : null,
            $has('kemahasiswaan', 'prodi') ? ['/prodi', 'book', 'Persetujuan Prodi'] : null,
            $has('kemahasiswaan', 'admin_event') ? ['/pembayaran', 'file', 'Verifikasi Pembayaran'] : null,
            $has('penilai') ? ['/penilaian', 'check', 'Penilaian Juri'] : null,
            $has('operator', 'admin_event', 'kemahasiswaan') ? ['/operator', 'clock', 'Operator Live Score'] : null,
            $has('kemahasiswaan', 'admin_event') ? ['/kelola/sertifikat', 'file', 'Kelola Sertifikat'] : null,
            $has('kemahasiswaan') ? ['/kelola/prestasi', 'trophy', 'Verifikasi Prestasi'] : null,
            $has('kemahasiswaan', 'pimpinan', 'admin_event', 'pengurus') ? ['/laporan', 'grid', 'Laporan'] : null,
        ]),
        'ADMINISTRASI KAMPUS' => $has('kemahasiswaan') ? [
            ['/kelola/mahasiswa', 'user', 'Mahasiswa & Impor'],
            ['/kelola/penugasan', 'users', 'Penugasan Peran'],
            ['/kelola/pengaturan', 'spark', 'Pengaturan Kampus'],
        ] : [],
        'LAINNYA' => [
            ['/notifikasi', 'spark', 'Notifikasi', $unread ? (string) min($unread, 99) : null],
            ['/rekomendasi', 'spark', 'Status Fitur'],
            ['/profil', 'user', 'Profil Saya'],
        ],
    ];
@endphp
<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#00B9AD"><meta name="description" content="BRUDEL — Bakat, Rekognisi, Unjuk Diri, dan Eksplorasi Lomba Poltekkes Kemenkes Manado."><title>{{ $title }} · BRUDEL</title><link rel="icon" href="/brand/favicon.ico"><link rel="apple-touch-icon" href="/brand/apple-touch-icon.png"><link rel="manifest" href="/site.webmanifest">@vite(['resources/css/app.css','resources/js/app.js'])@livewireStyles</head>
<body><a class="skip-link" href="#main">Lewati ke konten</a>
<div class="app-shell" x-data="{ menu: false }">
<div class="mobile-shade" x-show="menu" x-cloak @click="menu=false"></div>
<aside class="sidebar" :class="menu && 'is-open'">
    <a class="brand" href="/"><img src="/brand/logo-mark.svg" alt="Logo BRUDEL"><span><strong>BRUDEL<span class="brand-dot">.</span></strong><small>POLTEKKES KEMENKES MANADO</small></span></a>
    <div class="workspace-label"><span class="dot"></span> {{ $staff ? 'Ruang pengelola' : 'Ruang mahasiswa' }}</div>
    <nav aria-label="Navigasi utama">
        @foreach($sections as $caption => $items)
            @continue(empty($items))
            <p class="nav-caption {{ $loop->first ? '' : 'second' }}">{{ $caption }}</p>
            @foreach($items as $item)
                @php($path = ltrim($item[0], '/'))
                @php($active = $path === '' ? request()->is('/') : request()->is($path, $path.'/*'))
                <a href="{{ $item[0] }}" class="nav-item {{ $active ? 'active' : '' }}" @if($active) aria-current="page" @endif><x-icon :name="$item[1]"/><span>{{ $item[2] }}</span>@if($item[3] ?? null)<span class="nav-count">{{ $item[3] }}</span>@endif</a>
            @endforeach
        @endforeach
    </nav>
    <div class="sidebar-bottom">
        <div class="sidebar-account"><span class="avatar">{{ mb_substr($me->name,0,1) }}</span><div><strong>{{ explode(' ',$me->name)[0] }}</strong><small>{{ $staff ? (config('brudel.roles')[$roles->first()]['label'] ?? 'Pengelola') : 'Mahasiswa aktif' }}</small></div><form action="/logout" method="post">@csrf<button class="icon-button" aria-label="Keluar akun"><x-icon name="logout"/></button></form></div>
    </div>
</aside>
<div class="main-shell"><header class="topbar"><div class="crumb"><button class="icon-button mobile-toggle" @click="menu=!menu" aria-label="Buka navigasi"><x-icon name="menu"/></button><span>{{ $staff ? 'Ruang Pengelola' : 'Ruang Mahasiswa' }}</span><span class="crumb-divider">/</span><strong>{{ $title }}</strong></div><div class="topbar-right"><span class="term">Zona waktu WITA</span>@if(config('brudel.demo'))<span class="demo-tag">MODE DEMO</span>@endif<a class="bell" href="/notifikasi" aria-label="Notifikasi{{ $unread ? ', '.$unread.' belum dibaca' : '' }}"><x-icon name="bell"/>@if($unread)<span>{{ min($unread, 99) }}</span>@endif</a><a class="avatar small" href="/profil" aria-label="Profil saya">{{ mb_substr($me->name,0,1) }}</a></div></header>
<main id="main" class="content"><div id="offline-banner" hidden class="alert warning">Anda sedang offline. Data pribadi tidak tersedia offline. Sambungkan kembali sebelum mengirim formulir.</div>
@if(session('success'))<div class="alert success" role="status"><x-icon name="check"/>{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert error" role="alert"><strong>Periksa kembali isian Anda.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
{{ $slot }}
<footer class="footer"><span>© {{ date('Y') }} BRUDEL · Poltekkes Kemenkes Manado</span><span>Sportif Berkompetisi, Unggul Berprestasi.</span></footer>
</main></div></div>@livewireScripts</body></html>
