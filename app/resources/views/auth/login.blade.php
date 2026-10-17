<x-guest title="Masuk" heading="Selamat datang kembali." intro="Masuk dengan akun kampus untuk melanjutkan perjalananmu.">
<form method="post" action="/login" data-loader>@csrf
    <label>Email, NIM, atau username<input type="text" name="email" required autocomplete="username" value="{{ old('email') }}" placeholder="Email, NIM, atau username"></label>
    <label>Kata sandi<input type="password" name="password" required autocomplete="current-password" placeholder="Masukkan kata sandi"></label>
    <x-turnstile/>
    <button class="button primary full">Masuk ke BRUDEL <x-icon name="arrow"/></button>
</form>
@if(filled(config('services.google.client_id')) && filled(config('services.google.client_secret')))
<div class="divider-label"><span>atau</span></div>
<a class="button outline full google-button" href="/auth/google" data-loader><svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="#4285F4" d="M22.5 12.3c0-.8-.1-1.5-.2-2.3H12v4.4h5.9a5 5 0 0 1-2.2 3.3v2.7h3.6c2.1-1.9 3.2-4.8 3.2-8.1Z"/><path fill="#34A853" d="M12 23c3 0 5.5-1 7.3-2.7l-3.6-2.7c-1 .7-2.2 1.1-3.7 1.1-2.9 0-5.3-1.9-6.2-4.5H2.1v2.8A11 11 0 0 0 12 23Z"/><path fill="#FBBC05" d="M5.8 14.2a6.6 6.6 0 0 1 0-4.3V7.1H2.1a11 11 0 0 0 0 9.9l3.7-2.8Z"/><path fill="#EA4335" d="M12 5.4c1.6 0 3.1.6 4.2 1.7l3.2-3.2A11 11 0 0 0 2.1 7.1l3.7 2.8C6.7 7.3 9.1 5.4 12 5.4Z"/></svg> Masuk dengan Google</a>
<p class="form-note">Google hanya untuk email yang sudah terdaftar pada data resmi kampus.</p>
@endif
<div class="login-links"><a class="text-link" href="/aktivasi">Aktivasi akun baru</a><a class="text-link" href="/lupa-sandi">Lupa kata sandi?</a></div>
<p class="form-note">Akun mahasiswa berasal dari data resmi kampus. Aktivasi dilakukan melalui email yang terdaftar di kemahasiswaan.</p>
@if(config('brudel.demo'))
<div class="demo-login"><span class="demo-tag">LINGKUNGAN DEMONSTRASI</span><p>Semua akun demo memakai kata sandi <code>BrudelDemo!2026</code>:</p>
<dl>
    <dt>Mahasiswa</dt><dd>mahasiswa@demo.brudel.test · anggota@demo.brudel.test</dd>
    <dt>Ketua UKM Olahraga · Pelatih</dt><dd>pengurus@demo.brudel.test · pembina@demo.brudel.test</dd>
    <dt>Kemahasiswaan Direktorat (superadmin) · Admin event</dt><dd>admin@demo.brudel.test · event@demo.brudel.test</dd>
    <dt>Kemahasiswaan Jurusan Keperawatan · Pimpinan</dt><dd>jurusan@demo.brudel.test · pimpinan@demo.brudel.test</dd>
    <dt>Juri · Operator</dt><dd>juri@demo.brudel.test · operator@demo.brudel.test</dd>
</dl><small>Semua nama, kegiatan dan nilai pada demo bersifat ilustratif.</small></div>
@endif
</x-guest>
