<x-layout title="Pengaturan Kampus">
<div class="page-heading"><div><p class="eyebrow">ADMINISTRASI KAMPUS</p><h1>Pengaturan kampus.</h1><p>Asumsi bisnis yang belum ditetapkan disimpan sebagai pengaturan yang dapat diubah.</p></div></div>

<div class="detail-columns">
<section class="panel">
    <h2>Kebijakan</h2>
    <form method="post" action="/kelola/pengaturan">@csrf
        <label>Definisi tingkat regional<textarea name="regional_definition" rows="3" required maxlength="1000">{{ old('regional_definition', $settings['regional_definition']) }}</textarea></label>
        <label>Kebijakan pembatalan & pengembalian dana<textarea name="refund_policy" rows="4" required maxlength="3000">{{ old('refund_policy', $settings['refund_policy']) }}</textarea></label>
        <label>Awalan nomor sertifikat<input name="certificate_prefix" required maxlength="30" value="{{ old('certificate_prefix', $settings['certificate_prefix']) }}"></label>
        <button class="button primary">Simpan kebijakan</button>
    </form>
    <hr>
    <h3>Kebijakan lain dari konfigurasi lingkungan</h3>
    <dl><dt>Mahasiswa boleh mengikuti beberapa UKM</dt><dd>{{ config('brudel.multiple_clubs') ? 'Ya' : 'Tidak' }} <small class="muted">(BRUDEL_MULTIPLE_CLUBS)</small></dd><dt>Biaya pendaftaran UKM</dt><dd>Gratis — biaya lomba tidak berlaku untuk UKM</dd><dt>Penyimpanan dokumen</dt><dd>{{ config('brudel.document_disk') }} (privat)</dd></dl>
</section>

<section class="panel">
    <h2>Rekening resmi pembayaran</h2>
    <p>Hanya rekening resmi kampus yang tercantum pada dokumen resmi. BRUDEL tidak memproses pembayaran otomatis; bukti transfer diverifikasi manual.</p>
    @forelse($accounts as $account)
        <div class="list-row"><div><strong>{{ $account->bank_name }} · {{ $account->account_number }}</strong><small>a.n. {{ $account->account_holder }}{{ $account->notes ? ' · '.$account->notes : '' }}</small></div><form method="post" action="/kelola/rekening/{{ $account->id }}">@csrf<button class="button {{ $account->active ? 'outline' : 'primary' }} small">{{ $account->active ? 'Nonaktifkan' : 'Aktifkan' }}</button></form></div>
    @empty<div class="alert warning">Belum ada rekening resmi. Lomba berbayar belum dapat menerima pendaftaran.</div>@endforelse
    <form class="form-grid" method="post" action="/kelola/rekening">@csrf
        <label>Bank<input name="bank_name" required maxlength="80"></label>
        <label>Nomor rekening<input name="account_number" required maxlength="40" inputmode="numeric"></label>
        <label class="span-2">Atas nama<input name="account_holder" required maxlength="120"></label>
        <label class="span-2">Catatan (opsional)<input name="notes" maxlength="500"></label>
        <div class="span-2"><button class="button outline">Tambah rekening</button></div>
    </form>
</section>
</div>

<section class="panel">
    <h2>Program studi</h2>
    <div class="table-wrap"><table class="compact"><thead><tr><th>Jurusan</th><th>Program studi</th><th>Kode</th><th>Mahasiswa</th><th>Ubah</th></tr></thead><tbody>
    @foreach($programs as $program)
    <tr><td>{{ $program->department }}</td><td>{{ $program->name }}</td><td>{{ $program->code ?? '—' }}</td><td>{{ $program->users_count }}</td>
    <td><details><summary class="text-link">Ubah</summary><form class="inline-form" method="post" action="/kelola/prodi/{{ $program->id }}">@csrf<input name="department" value="{{ $program->department }}" required><input name="name" value="{{ $program->name }}" required><input name="code" value="{{ $program->code }}" placeholder="Kode"><button class="button outline small">Simpan</button></form></details></td></tr>
    @endforeach
    </tbody></table></div>
    <form class="inline-form" method="post" action="/kelola/prodi">@csrf<input name="department" required placeholder="Jurusan"><input name="name" required placeholder="Nama program studi"><input name="code" placeholder="Kode (untuk impor)"><button class="button primary small">Tambah prodi</button></form>
</section>
</x-layout>
