<x-guest title="Masuk" heading="Selamat datang kembali." intro="Masuk dengan akun kampus untuk melanjutkan perjalananmu.">
<form method="post" action="/login">@csrf
    <label>Email terdaftar<input type="email" name="email" required autocomplete="username" value="{{ old('email') }}" placeholder="nama@email.kampus"></label>
    <label>Kata sandi<input type="password" name="password" required autocomplete="current-password" placeholder="Masukkan kata sandi"></label>
    <button class="button primary full">Masuk ke BRUDEL <x-icon name="arrow"/></button>
</form>
<div class="login-links"><a class="text-link" href="/aktivasi">Aktivasi akun baru</a><a class="text-link" href="/lupa-sandi">Lupa kata sandi?</a></div>
<p class="form-note">Akun mahasiswa berasal dari data resmi kampus. Aktivasi dilakukan melalui email yang terdaftar di kemahasiswaan.</p>
@if(config('brudel.demo'))
<div class="demo-login"><span class="demo-tag">LINGKUNGAN DEMONSTRASI</span><p>Semua akun demo memakai kata sandi <code>BrudelDemo!2026</code>:</p>
<dl>
    <dt>Mahasiswa</dt><dd>mahasiswa@demo.brudel.test · anggota@demo.brudel.test</dd>
    <dt>Pengurus (Ketua) Badminton</dt><dd>pengurus@demo.brudel.test</dd>
    <dt>Admin event · Kemahasiswaan</dt><dd>event@demo.brudel.test · admin@demo.brudel.test</dd>
    <dt>Prodi · Penilai · Operator</dt><dd>prodi@demo.brudel.test · juri@demo.brudel.test · operator@demo.brudel.test</dd>
    <dt>Pimpinan · Pembina</dt><dd>pimpinan@demo.brudel.test · pembina@demo.brudel.test</dd>
</dl><small>Semua nama, kegiatan dan nilai pada demo bersifat ilustratif.</small></div>
@endif
</x-guest>
