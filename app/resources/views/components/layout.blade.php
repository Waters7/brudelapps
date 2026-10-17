@props(['title'=>'Dashboard'])
@php
    $me = auth()->user();
    $roles = $me->activeRoles();
    $has = fn (string ...$names) => $roles->intersect($names)->isNotEmpty();
    $staff = $roles->isNotEmpty();
    $leadershipOnly = $roles->all() === ['pimpinan'] && ! $me->nim;
    $managesCompetition = $has('kemahasiswaan', 'admin_event', 'pengelola_lomba');
    $coach = $has('kemahasiswaan', 'pembina', 'pengurus', 'jurusan');
    $unread = $me->unreadNotifications()->count();
    $roleLabel = $staff ? ($me->activeAssignments()->first()?->roleLabel() ?? 'Pengelola') : 'Mahasiswa aktif';
    $sections = $leadershipOnly ? [
        'PIMPINAN' => [
            ['/', 'grid', 'Dashboard pimpinan'],
            ['/laporan', 'grid', 'Laporan & IKU'],
            ['/delegasi', 'globe', 'Delegasi'],
            ['/live', 'clock', 'Live Score'],
        ],
    ] : [
        'MENU UTAMA' => array_filter([
            ['/', 'grid', 'Dashboard'],
            ['/ukm', 'users', 'Jelajahi UKM'],
            ['/lomba', 'trophy', 'Jelajahi Lomba'],
            ['/kegiatan', 'calendar', 'Jelajah Kegiatan'],
            $me->nim ? ['/pendaftaran', 'file', 'Pendaftaran Saya'] : null,
            $me->nim ? ['/ukm-saya', 'book', 'UKM Saya'] : null,
            $me->nim ? ['/sertifikat', 'file', 'Sertifikat Saya'] : null,
            $me->nim ? ['/prestasi', 'trophy', 'Prestasi Saya'] : null,
            ['/live', 'clock', 'Live Score'],
            ['/delegasi', 'globe', 'Delegasi'],
        ]),
        'PENGELOLAAN' => array_filter([
            $me->isReviewer() ? ['/tinjauan', 'check', 'Tinjau Pengajuan'] : null,
            $has('kemahasiswaan', 'pengurus', 'jurusan') ? ['/kelola/ukm', 'users', 'Kelola UKM'] : null,
            $has('kemahasiswaan', 'admin_event', 'pengurus', 'pengelola_lomba') ? ['/kelola/event', 'calendar', 'Kelola Event & Lomba'] : null,
            $has('kemahasiswaan', 'jurusan') ? ['/jurusan', 'book', 'Jurusan'] : null,
            $has('kemahasiswaan', 'jurusan') ? ['/kegiatan/izin', 'calendar', 'Izin Kegiatan'] : null,
            $has('jurusan') && ! $has('kemahasiswaan') ? ['/kelola/mahasiswa', 'user', 'Mahasiswa Jurusan'] : null,
            $managesCompetition ? ['/pembayaran', 'file', 'Verifikasi Pembayaran'] : null,
            $has('penilai') ? ['/penilaian', 'check', 'Penilaian Juri'] : null,
            $has('operator', 'admin_event', 'pengelola_lomba', 'kemahasiswaan') ? ['/operator', 'clock', 'Operator Skor & Live'] : null,
            $managesCompetition ? ['/kelola/sertifikat', 'file', 'Kelola Sertifikat'] : null,
            $has('kemahasiswaan', 'jurusan') ? ['/kelola/prestasi', 'trophy', 'Verifikasi Prestasi'] : null,
            $has('kemahasiswaan', 'pimpinan', 'admin_event', 'pengelola_lomba', 'pengurus', 'jurusan') ? ['/laporan', 'grid', 'Laporan'] : null,
        ]),
        'ADMINISTRASI KAMPUS' => $has('kemahasiswaan') ? [
            ['/kelola/mahasiswa', 'user', 'Mahasiswa & Impor'],
            ['/kelola/penugasan', 'users', 'Penugasan Peran'],
            ['/kelola/perjadin', 'globe', 'Perjadin Delegasi (Pangi)'],
            ['/kelola/banner-depan', 'spark', 'Banner Halaman Depan'],
        ] : [],
        'PENGATURAN' => $has('kemahasiswaan') ? [
            ['/kelola/pengguna', 'user', 'Pengguna Login'],
            ['/kelola/audit', 'file', 'Audit Log'],
            ['/kelola/pengaturan', 'spark', 'Pengaturan Kampus'],
            ['/kelola/sistem', 'shield', 'Pengaturan Sistem'],
        ] : [],
        'MASTER DATA' => $has('kemahasiswaan') ? [
            ['/kelola/master', 'grid', 'Ringkasan Master Data', null, true],
            ...collect(\App\Services\Campus\MasterData::TYPES)->map(fn ($spec, $type) => ['/kelola/master/'.$type, $spec['icon'], $spec['label']])->values()->all(),
            ['/kelola/pengaturan#prodi', 'book', 'Jurusan & Prodi'],
        ] : [],
    ];
    $sections['LAINNYA'] = [
        ['/notifikasi', 'bell', 'Notifikasi', $unread ? (string) min($unread, 99) : null],
        ['/bantuan', 'chat', $has('kemahasiswaan') ? 'Kotak Bantuan' : 'Bantuan'],
        ['/rekomendasi', 'spark', 'Status Fitur'],
        ['/profil', 'user', 'Profil & Kata Sandi'],
    ];
@endphp
<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#00B9AD"><meta name="description" content="BRUDEL — Bakat, Rekognisi, Unjuk Diri, dan Eksplorasi Lomba Poltekkes Kemenkes Manado."><title>{{ $title }} · BRUDEL</title><link rel="icon" href="/brand/favicon.ico"><link rel="apple-touch-icon" href="/brand/apple-touch-icon.png"><link rel="manifest" href="/site.webmanifest">@vite(['resources/css/app.css','resources/js/app.js'])@livewireStyles</head>
<body><a class="skip-link" href="#main">Lewati ke konten</a>
<div class="app-shell" x-data="{ menu: false }">
<div class="mobile-shade" x-show="menu" x-cloak @click="menu=false"></div>
<aside class="sidebar" :class="menu && 'is-open'">
    <a class="brand" href="/"><img src="/brand/logo-mark.svg" alt="Logo BRUDEL"><span><strong>BRUDEL<span class="brand-dot">.</span></strong><small>POLTEKKES KEMENKES MANADO</small></span></a>
    <div class="workspace-label"><span class="dot"></span> {{ $leadershipOnly ? 'Ruang pimpinan' : ($staff ? 'Ruang pengelola' : 'Ruang mahasiswa') }}</div>
    <nav aria-label="Navigasi utama">
        @foreach($sections as $caption => $items)
            @continue(empty($items))
            <p class="nav-caption {{ $loop->first ? '' : 'second' }}">{{ $caption }}</p>
            @foreach($items as $item)
                @php($path = ltrim(strtok($item[0], '#'), '/'))
                @php($active = $path === '' || ($item[4] ?? false) ? request()->is($path ?: '/') : request()->is($path, $path.'/*'))
                <a href="{{ $item[0] }}" class="nav-item {{ $active ? 'active' : '' }}" @if($active) aria-current="page" @endif><x-icon :name="$item[1]"/><span>{{ $item[2] }}</span>@if($item[3] ?? null)<span class="nav-count">{{ $item[3] }}</span>@endif</a>
            @endforeach
        @endforeach
    </nav>
    <div class="sidebar-bottom">
        <div class="sidebar-account"><x-avatar :user="$me"/><div><strong>{{ explode(' ',$me->name)[0] }}</strong><small>{{ $roleLabel }}</small></div></div>
        <form action="/logout" method="post" class="sidebar-logout">@csrf<button class="button outline full"><x-icon name="logout"/> Keluar</button></form>
    </div>
</aside>
<div class="main-shell"><header class="topbar"><div class="crumb"><button class="icon-button mobile-toggle" @click="menu=!menu" aria-label="Buka navigasi"><x-icon name="menu"/></button><span>{{ $leadershipOnly ? 'Ruang Pimpinan' : ($staff ? 'Ruang Pengelola' : 'Ruang Mahasiswa') }}</span><span class="crumb-divider">/</span><strong>{{ $title }}</strong></div>
<div class="topbar-right"><span class="term">Zona waktu WITA</span>@if(config('brudel.demo'))<span class="demo-tag">MODE DEMO</span>@endif<a class="bell" href="/notifikasi" aria-label="Notifikasi{{ $unread ? ', '.$unread.' belum dibaca' : '' }}"><x-icon name="bell"/>@if($unread)<span>{{ min($unread, 99) }}</span>@endif</a>
    {{-- Native <details>: the menu opens on click even when the page scripts have not loaded. --}}
    <details class="account-menu" data-account-menu>
        <summary class="account-trigger" aria-label="Menu akun {{ $me->name }}"><x-avatar :user="$me"/><span class="account-name">{{ explode(' ', $me->name)[0] }}</span><span aria-hidden="true">▾</span></summary>
        <div class="account-dropdown">
            <div class="account-head"><strong>{{ $me->name }}</strong><small class="account-role">{{ $roleLabel }}</small><small>{{ $me->username ?? $me->nim ?? $me->email }}</small></div>
            <a href="/profil"><x-icon name="user"/> Profil & foto</a>
            <a href="/profil#kontak"><x-icon name="bell"/> Email & nomor HP</a>
            <a href="/profil#ubah-sandi"><x-icon name="shield"/> Ubah kata sandi</a>
            <hr>
            <form action="/logout" method="post">@csrf<button type="submit"><x-icon name="logout"/> Keluar</button></form>
        </div>
    </details>
</div></header>
<main id="main" class="content"><div id="offline-banner" hidden class="alert warning">Anda sedang offline. Data pribadi tidak tersedia offline. Sambungkan kembali sebelum mengirim formulir.</div>
@if(session('success'))<div class="alert success" role="status"><x-icon name="check"/>{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert error" role="alert"><strong>Periksa kembali isian Anda.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
{{ $slot }}
<footer class="footer"><span>© {{ date('Y') }} BRUDEL · Poltekkes Kemenkes Manado</span><span>Sportif Berkompetisi, Unggul Berprestasi.</span></footer>
</main></div></div>
@unless(request()->is('bantuan'))<livewire:support-chat />@endunless
@livewireScripts</body></html>
