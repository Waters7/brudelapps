<x-guest :title="$mode === 'activation' ? 'Buat kata sandi' : 'Atur ulang kata sandi'"
    :heading="$mode === 'activation' ? 'Buat kata sandi pertama.' : 'Atur ulang kata sandi.'"
    intro="Minimal 10 karakter berisi huruf dan angka. Jangan gunakan kata sandi dari layanan lain.">
<form method="post" action="{{ ($mode === 'activation' ? '/aktivasi/' : '/reset-sandi/').$token }}" data-loader>@csrf
    <label>Email terdaftar<input type="email" name="email" required autocomplete="username" value="{{ old('email', $email) }}"></label>
    <label>Kata sandi baru<input type="password" name="password" required minlength="10" autocomplete="new-password"></label>
    <label>Ulangi kata sandi<input type="password" name="password_confirmation" required minlength="10" autocomplete="new-password"></label>
    <button class="button primary full">{{ $mode === 'activation' ? 'Aktifkan akun' : 'Simpan kata sandi' }} <x-icon name="arrow"/></button>
</form>
<div class="login-links"><a class="text-link" href="/login">← Kembali ke halaman masuk</a></div>
</x-guest>
