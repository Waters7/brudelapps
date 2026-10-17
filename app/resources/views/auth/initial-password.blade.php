@php($placeholder = str_ends_with((string) $user->email, '.invalid'))
<x-guest title="Lengkapi akun" heading="Lengkapi akun Anda."
    intro="Sebelum memakai BRUDEL, pastikan email dan nomor HP masih aktif agar informasi UKM, lomba, dan pemulihan kata sandi sampai kepada Anda.{{ $user->must_change_password ? ' Kata sandi awal hanya untuk masuk pertama kali; ganti sekarang.' : '' }}">
<p class="form-note">Masuk sebagai <strong>{{ $user->name }}</strong>{{ $user->nim ? ' · NIM '.$user->nim : ($user->username ? ' · '.$user->username : '') }}.</p>
<form method="post" action="/sandi-baru" data-loader>@csrf
    <label>Email aktif<input type="email" name="email" required maxlength="190" autocomplete="email" value="{{ old('email', $placeholder ? '' : $user->email) }}" placeholder="nama@email.com"></label>
    <label>Nomor HP aktif (WhatsApp)<input type="tel" name="phone" required maxlength="20" autocomplete="tel" inputmode="tel" value="{{ old('phone', $user->phone) }}" placeholder="081234567890"></label>
    @if($user->must_change_password)
    <label>Kata sandi baru<input type="password" name="password" required minlength="10" autocomplete="new-password"></label>
    <label>Ulangi kata sandi<input type="password" name="password_confirmation" required minlength="10" autocomplete="new-password"></label>
    <p class="form-note">Minimal 10 karakter berisi huruf dan angka. Jangan memakai tanggal lahir.</p>
    @endif
    <button class="button primary full">Simpan dan lanjutkan <x-icon name="arrow"/></button>
</form>
<form method="post" action="/logout" class="login-links">@csrf<button class="text-link plain">Bukan Anda? Keluar</button></form>
</x-guest>
