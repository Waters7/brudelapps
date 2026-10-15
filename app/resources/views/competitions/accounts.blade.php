<x-layout :title="'Akun lomba '.$competition->name">
<a class="back-link" href="/kelola/event/{{ $competition->event_id }}">← {{ $competition->event->name }}</a>
<div class="page-heading"><div><p class="eyebrow">AKUN LOMBA OTOMATIS</p><h1>{{ $competition->name }}</h1><p>Akun dibuat otomatis untuk lomba ini. Bagikan secara pribadi kepada pengelola, operator skor/live score, dan juri. Hanya pengaju lomba dan kemahasiswaan yang dapat melihat halaman ini; setiap kunjungan tercatat di audit.</p></div>
<button class="button outline" type="button" onclick="window.print()">Cetak kartu akun</button></div>

@if($competition->event->status !== 'approved')<div class="alert warning">Akun dibuat setelah event disetujui kemahasiswaan.</div>@endif

<div class="account-cards">
@forelse($accounts as $account)
<article class="panel account-card" x-data="{ show: false, copied: false }">
    <div class="section-heading"><div><span class="eyebrow">{{ $account->roleLabel() }}</span><h2>{{ $account->user->name }}</h2></div>
        <span class="badge {{ $account->user->active ? 'status-active' : 'status-inactive' }}">{{ $account->user->active ? 'Aktif' : 'Nonaktif' }}</span></div>
    <dl>
        <dt>Email login</dt><dd><code>{{ $account->user->email }}</code></dd>
        <dt>Kata sandi</dt><dd><code x-text="show ? @js($account->secret) : '••••••••••••'">••••••••••••</code>
            <button class="text-link plain" type="button" @click="show = !show" x-text="show ? 'Sembunyikan' : 'Tampilkan'">Tampilkan</button>
            <button class="text-link plain" type="button" @click="navigator.clipboard.writeText(@js($account->secret)); copied = true; setTimeout(() => copied = false, 1500)" x-text="copied ? 'Tersalin' : 'Salin'">Salin</button></dd>
        <dt>Hak akses</dt><dd>{{ ['pengelola_lomba' => 'Tinjau pendaftaran, verifikasi pembayaran, bagan, rubrik, hasil, sertifikat lomba ini', 'operator' => 'Skor sementara, live score, pencatatan pemenang', 'penilai' => 'Mengisi nilai rubrik (tercatat di log penilaian)'][$account->role] ?? '' }}</dd>
        <dt>Berlaku sampai</dt><dd>{{ \App\Services\Wita::show($competition->staffUntil(), 'd M Y') }} WITA{{ $account->rotated_at ? ' · sandi diganti '.\App\Services\Wita::show($account->rotated_at, 'd M H:i') : '' }}</dd>
    </dl>
    <form method="post" action="/kelola/lomba/{{ $competition->id }}/akun/{{ $account->id }}/reset" onsubmit="return confirm('Ganti kata sandi akun ini? Sesi yang sedang masuk akan diakhiri.')">@csrf<button class="button outline small">Ganti kata sandi</button></form>
</article>
@empty<div class="panel empty">Belum ada akun. Akun dibuat ketika event disetujui.</div>@endforelse
</div>
<p class="form-note">Jumlah juri diatur pada formulir nomor lomba (1–{{ config('brudel.max_judges') }} untuk lomba rubrik). Akun yang tidak diperlukan lagi dinonaktifkan, tidak dihapus, agar log penilaian tetap utuh.</p>
</x-layout>
