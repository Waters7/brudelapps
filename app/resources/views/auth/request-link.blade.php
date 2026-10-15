<x-guest :title="$mode === 'activation' ? 'Aktivasi akun' : 'Lupa kata sandi'"
    :heading="$mode === 'activation' ? 'Aktifkan akun BRUDEL.' : 'Pulihkan kata sandi.'"
    :intro="$mode === 'activation' ? 'Masukkan email yang terdaftar pada data resmi kampus. Kami kirim tautan untuk membuat kata sandi.' : 'Masukkan email akun yang sudah aktif. Kami kirim tautan pemulihan yang berlaku 60 menit.'">
<form method="post" action="{{ $mode === 'activation' ? '/aktivasi' : '/lupa-sandi' }}">@csrf
    <label>Email terdaftar<input type="email" name="email" required autocomplete="email" value="{{ old('email') }}" placeholder="nama@email.kampus"></label>
    <button class="button primary full">Kirim tautan <x-icon name="arrow"/></button>
</form>
<div class="login-links"><a class="text-link" href="/login">← Kembali ke halaman masuk</a></div>
<p class="form-note">Demi keamanan, kami tidak menampilkan apakah email terdaftar. Jika tidak menerima email, hubungi kemahasiswaan melalui kanal resmi kampus.</p>
</x-guest>
