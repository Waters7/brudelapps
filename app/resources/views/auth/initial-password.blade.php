<x-guest title="Buat kata sandi baru" heading="Buat kata sandi baru."
    intro="Kata sandi awal (tanggal lahir) hanya untuk masuk pertama kali. Ganti sekarang dengan kata sandi minimal 10 karakter berisi huruf dan angka; tanggal lahir mudah ditebak orang lain.">
<p class="form-note">Masuk sebagai <strong>{{ auth()->user()->name }}</strong>{{ auth()->user()->nim ? ' · NIM '.auth()->user()->nim : '' }}.</p>
<form method="post" action="/sandi-baru" data-loader>@csrf
    <label>Kata sandi baru<input type="password" name="password" required minlength="10" autocomplete="new-password"></label>
    <label>Ulangi kata sandi<input type="password" name="password_confirmation" required minlength="10" autocomplete="new-password"></label>
    <button class="button primary full">Simpan dan lanjutkan <x-icon name="arrow"/></button>
</form>
<form method="post" action="/logout" class="login-links">@csrf<button class="text-link plain">Bukan Anda? Keluar</button></form>
</x-guest>
