<x-layout title="Profil Saya">
<div class="page-heading"><div><p class="eyebrow">IDENTITAS & KEAMANAN</p><h1>Profil saya.</h1><p>Data identitas mengikuti data resmi kampus.</p></div>
<form method="post" action="/logout">@csrf<button class="button outline"><x-icon name="logout"/> Keluar</button></form></div>

<div class="detail-columns">
<section class="panel">
    <div class="profile-top"><x-avatar :user="$user" size="big"/><div><h2>{{ $user->name }}</h2><x-status value="active"/></div></div>
    <dl><dt>NIM</dt><dd>{{ $user->nim ?? 'Akun pegawai' }}</dd>@if($user->username)<dt>Username</dt><dd>{{ $user->username }}</dd>@endif<dt>Email</dt><dd>{{ $user->email }}</dd><dt>Nomor HP</dt><dd>{{ $user->phone ?? '—' }}</dd>
    <dt>Jurusan · program studi</dt><dd>{{ $user->program ? ($user->program->department?->name ?? '—').' · '.$user->program->name : 'Tidak berlaku' }}</dd><dt>Zona waktu</dt><dd>WITA · Asia/Makassar</dd>
    <dt>Peran aktif</dt><dd>@forelse($user->currentAssignments()->with('club', 'event', 'department', 'competition')->get() as $a)<span class="chip">{{ $a->roleLabel() }}{{ $a->position ? ' ('.$a->position.')' : '' }}{{ $a->role === 'jurusan' ? '' : ' · '.$a->scopeLabel() }} · s.d. {{ \App\Services\Wita::show($a->ends_at, 'd M Y') }}</span>@empty Mahasiswa @endforelse</dd>
    @if($googleEnabled)<dt>Google</dt><dd>{{ $user->google_sub ? 'Tertaut — dapat masuk dengan Google' : 'Belum tertaut. Masuk sekali dengan tombol Google di halaman login untuk menautkan.' }}</dd>@endif</dl>
    <hr>
    <h3>Foto profil</h3>
    <form class="inline-form" method="post" action="/profil/foto" enctype="multipart/form-data">@csrf<input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required><button class="button primary small">Unggah foto</button></form>
    <p class="form-note">JPG/PNG/WEBP, maksimal 2 MB, minimal 100×100 piksel. Foto dipotong persegi, metadata dihapus, dan hanya terlihat oleh pengguna BRUDEL yang sudah masuk (mis. pada daftar delegasi).</p>
    @if($user->photo_path)<form method="post" action="/profil/foto/hapus">@csrf<button class="text-link plain">Hapus foto</button></form>@endif
</section>

<section class="panel">
    @unless($managedAccount)
    <h2 id="kontak">Email & nomor HP</h2>
    <form method="post" action="/profil/kontak">@csrf
        <label>Email aktif<input type="email" name="email" required maxlength="190" autocomplete="email" value="{{ old('email', $user->email) }}"></label>
        <label>Nomor HP aktif (WhatsApp)<input type="tel" name="phone" required maxlength="20" autocomplete="tel" inputmode="tel" value="{{ old('phone', $user->phone) }}" placeholder="081234567890"></label>
        <button class="button outline">Simpan kontak</button>
    </form>
    <hr>
    @endunless
    <h2 id="ubah-sandi">Ubah kata sandi</h2>
    @if($managedAccount)
        <div class="alert warning">Ini akun lomba yang dibuat otomatis. Kata sandi diatur oleh pengaju lomba atau kemahasiswaan.</div>
    @else
    <form method="post" action="/profil/sandi">@csrf
        <label>Kata sandi saat ini<input type="password" name="current_password" required autocomplete="current-password"></label>
        <label>Kata sandi baru<input type="password" name="password" required minlength="10" autocomplete="new-password"></label>
        <label>Ulangi kata sandi baru<input type="password" name="password_confirmation" required minlength="10" autocomplete="new-password"></label>
        <button class="button primary">Simpan kata sandi</button>
        <p class="form-note">Minimal 10 karakter berisi huruf dan angka. Setelah diganti, sesi di perangkat lain diakhiri. Lupa kata sandi saat ini? Keluar lalu gunakan <a class="text-link" href="/lupa-sandi">Lupa kata sandi</a>.</p>
    </form>
    @endif
    @if($user->nim)
    <hr>
    <h3>Publikasi prestasi</h3>
    <form method="post" action="/profil">@csrf
        <label class="checkbox-label"><input type="hidden" name="publication_consent" value="0"><input type="checkbox" name="publication_consent" value="1" @checked($user->publication_consent)><span>Saya menyetujui publikasi prestasi terverifikasi pada profil publik BRUDEL.</span></label>
        <p class="form-note">Profil publik hanya menampilkan nama, prodi, dan prestasi terverifikasi. NIM, foto, dokumen persyaratan, dan bukti pembayaran tidak pernah ditampilkan. Persetujuan dapat dicabut kapan saja.</p>
        <button class="button outline">Simpan preferensi</button>
    </form>
    @if($user->publication_consent && $user->public_slug)<p class="form-note">Profil publik: <a class="text-link" href="/mahasiswa/{{ $user->public_slug }}">{{ url('/mahasiswa/'.$user->public_slug) }}</a></p>@endif
    @endif
</section>
</div>
</x-layout>
