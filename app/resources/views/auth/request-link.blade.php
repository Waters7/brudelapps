<x-guest :title="$mode === 'activation' ? 'Aktivasi akun' : 'Lupa kata sandi'"
    :heading="$mode === 'activation' ? 'Aktifkan akun BRUDEL.' : 'Pulihkan kata sandi.'"
    :intro="$mode === 'activation' ? 'Masukkan email yang terdaftar pada data resmi kampus. Kami kirim tautan untuk membuat kata sandi.' : 'Masukkan NIM, NIP, email, atau username akun Anda. Kami kirim tautan pemulihan ke email akun, berlaku 60 menit.'">
<form method="post" action="{{ $mode === 'activation' ? '/aktivasi' : '/lupa-sandi' }}" data-loader>@csrf
    @if($mode === 'activation')
    <label>Email terdaftar<input type="email" name="email" required autocomplete="email" value="{{ old('email') }}" placeholder="nama@email.kampus"></label>
    @else
    <label>NIM/NIP, email, atau username<input name="email" required maxlength="190" autocomplete="username" value="{{ old('email') }}" placeholder="NIM/NIP, email, atau username"></label>
    @endif
    <x-turnstile/>
    <button class="button primary full">Kirim tautan <x-icon name="arrow"/></button>
</form>
<div class="login-links"><a class="text-link" href="/login">← Kembali ke halaman masuk</a></div>
<p class="form-note">Demi keamanan, kami tidak menampilkan apakah akun terdaftar. Jika tidak menerima email, hubungi Kemahasiswaan Jurusan atau Direktorat untuk mendapat sandi sementara.</p>
</x-guest>
