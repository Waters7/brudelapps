<x-layout title="Backup & Restore">
@php($human = fn (int $bytes) => \App\Services\Campus\StorageMonitor::human($bytes))
<div class="page-heading"><div><p class="eyebrow">PENGELOLAAN SISTEM</p><h1>Backup &amp; restore database.</h1><p>Cadangan memuat seluruh tabel database BRUDEL kecuali sesi, cache, dan antrean. Berkas unggahan tidak termasuk. Cadangan otomatis dibuat setiap hari pukul 01.30 WITA dan 7 terakhir disimpan.</p></div></div>

<div class="alert warning">Berkas cadangan berisi data pribadi dan sandi terenkripsi. Simpan unduhan di tempat aman. Cadangan hanya dapat dipulihkan ke instalasi BRUDEL ini, dengan mesin dan versi database yang sama.</div>

<div class="detail-columns">
<section class="panel">
    <h2>Buat cadangan</h2>
    <p class="muted">Sisa disk server {{ $human($disk['free']) }} ({{ $disk['percent'] }}% terpakai).</p>
    <form method="post" action="/kelola/sistem/backup">@csrf<button class="button primary">Buat cadangan sekarang</button></form>
</section>
<section class="panel">
    <h2>Unggah cadangan</h2>
    <form method="post" action="/kelola/sistem/backup/unggah" enctype="multipart/form-data">@csrf
        <label>Berkas .json.gz dari BRUDEL ini<input type="file" name="backup" required accept=".gz,application/gzip"></label>
        <button class="button outline">Unggah dan periksa</button>
    </form>
</section>
</div>

<section class="section-block"><h2>Cadangan tersimpan <span class="count-pill">{{ $backups->count() }}</span></h2>
<div class="panel table-panel"><div class="table-wrap"><table><thead><tr><th>Berkas</th><th>Jenis</th><th>Dibuat</th><th>Ukuran</th><th>Tindakan</th></tr></thead><tbody>
@forelse($backups as $backup)
<tr><td>{{ $backup['name'] }}<small>{{ $backup['tables'] }} tabel</small></td><td>{{ $backup['kind'] }}</td><td>{{ $backup['created_at'] ? \App\Services\Wita::show(\Illuminate\Support\Carbon::parse($backup['created_at']), 'd M Y H:i') : '—' }}</td><td>{{ $human($backup['size']) }}</td>
<td><div class="inline-actions"><a class="button outline small" href="/kelola/sistem/backup/{{ $backup['name'] }}/unduh">Unduh</a>
<details><summary class="text-link">Pulihkan</summary>
    <form method="post" action="/kelola/sistem/backup/{{ $backup['name'] }}/pulihkan">@csrf
        <p class="form-note">Seluruh data saat ini diganti isi cadangan ini. Kondisi sekarang dicadangkan dulu secara otomatis.</p>
        <label>Ketik PULIHKAN<input name="confirmation" required autocomplete="off"></label>
        <label>Kata sandi Anda<input type="password" name="password" required autocomplete="current-password"></label>
        <button class="button outline small danger">Pulihkan database</button>
    </form>
</details>
<form method="post" action="/kelola/sistem/backup/{{ $backup['name'] }}/hapus">@csrf<button class="text-link plain">Hapus</button></form></div></td></tr>
@empty<tr><td colspan="5" class="empty">Belum ada cadangan.</td></tr>@endforelse
</tbody></table></div></div>
</section>
</x-layout>
