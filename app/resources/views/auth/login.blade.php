<x-guest title="Masuk" heading="Selamat datang kembali." intro="Masuk dengan NIM, email, atau username untuk melanjutkan perjalananmu.">
@if($announcements->isNotEmpty())
<x-slot:story>
    <h2 class="flex items-center gap-2 text-sm font-bold tracking-[0.16em] text-white/80 [&_.icon]:text-brand-lime"><x-icon name="megaphone"/> PENGUMUMAN KEMAHASISWAAN</h2>
    <ul class="mt-4 max-w-xl space-y-3">
        @foreach($announcements as $announcement)
        <li class="rounded-2xl bg-white/10 p-4 ring-1 ring-white/15 backdrop-blur transition hover:bg-white/15">
            <p class="text-[11px] font-semibold text-brand-lime">{{ \App\Services\Wita::show($announcement->created_at, 'd M Y') }} · Kemahasiswaan Direktorat</p>
            <h3 class="mt-1 text-base font-bold text-white">{{ $announcement->title }}</h3>
            <p class="mt-1 whitespace-pre-line text-sm leading-relaxed text-white/75">{{ \Illuminate\Support\Str::limit($announcement->body, 240) }}</p>
        </li>
        @endforeach
    </ul>
</x-slot:story>
@endif

<form method="post" action="/login" data-loader class="space-y-1">@csrf
    <label>NIM, email, atau username<input type="text" name="email" required autocomplete="username" value="{{ old('email') }}" placeholder="NIM, email, atau username" autofocus></label>
    <label>Kata sandi
        <span class="relative mt-2 block [&_input]:mt-0!">
            <input type="password" name="password" id="password" required autocomplete="current-password" placeholder="Masukkan kata sandi" class="pr-12!">
            <button type="button" class="absolute right-2 top-1/2 flex size-9 -translate-y-1/2 items-center justify-center rounded-lg text-brand-ink/45 transition hover:bg-brand-teal/10 hover:text-brand-teal aria-pressed:text-brand-teal" data-password-toggle aria-pressed="false" aria-label="Tampilkan kata sandi">
                <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
        </span>
    </label>
    <p class="hidden rounded-xl bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-800" data-capslock-for="password" role="status">Caps Lock aktif. Periksa huruf besar/kecil kata sandi.</p>
    <x-turnstile/>
    <div class="flex justify-end pt-1"><a class="text-sm font-semibold text-brand-teal hover:underline" href="/lupa-sandi">Lupa kata sandi?</a></div>
    <button class="group mt-3 flex w-full items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-brand-teal to-brand-bright px-6 py-4 text-base font-bold text-white shadow-lg shadow-brand-teal/30 transition hover:-translate-y-0.5 hover:shadow-xl">Masuk ke BRUDEL <x-icon name="arrow" class="transition group-hover:translate-x-1"/></button>
</form>
@if(filled(config('services.google.client_id')) && filled(config('services.google.client_secret')))
<div class="divider-label"><span>atau</span></div>
<a class="flex w-full items-center justify-center gap-3 rounded-2xl border border-brand-ink/10 bg-white px-6 py-3.5 text-sm font-semibold text-brand-ink transition hover:border-brand-teal hover:shadow-md" href="/auth/google" data-loader><svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="#4285F4" d="M22.5 12.3c0-.8-.1-1.5-.2-2.3H12v4.4h5.9a5 5 0 0 1-2.2 3.3v2.7h3.6c2.1-1.9 3.2-4.8 3.2-8.1Z"/><path fill="#34A853" d="M12 23c3 0 5.5-1 7.3-2.7l-3.6-2.7c-1 .7-2.2 1.1-3.7 1.1-2.9 0-5.3-1.9-6.2-4.5H2.1v2.8A11 11 0 0 0 12 23Z"/><path fill="#FBBC05" d="M5.8 14.2a6.6 6.6 0 0 1 0-4.3V7.1H2.1a11 11 0 0 0 0 9.9l3.7-2.8Z"/><path fill="#EA4335" d="M12 5.4c1.6 0 3.1.6 4.2 1.7l3.2-3.2A11 11 0 0 0 2.1 7.1l3.7 2.8C6.7 7.3 9.1 5.4 12 5.4Z"/></svg> Masuk dengan Google</a>
<p class="form-note">Google hanya untuk email yang sudah terdaftar di BRUDEL.</p>
@endif
<div class="mt-6 rounded-2xl bg-brand-teal/5 p-4 text-xs leading-relaxed text-brand-ink/65 ring-1 ring-brand-teal/10">
    <strong class="block text-sm text-brand-ink">Pertama kali masuk?</strong>
    Gunakan NIM dan kata sandi awal dari kemahasiswaan. Setelah masuk, lengkapi email dan nomor HP aktif, lalu buat kata sandi barumu sendiri.
</div>
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
