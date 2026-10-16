<x-layout title="Pengaturan Kampus">
<div class="page-heading"><div><p class="eyebrow">ADMINISTRASI KAMPUS</p><h1>Pengaturan kampus.</h1><p>Asumsi bisnis yang belum ditetapkan disimpan sebagai pengaturan yang dapat diubah.</p></div></div>

<div class="detail-columns">
<section class="panel">
    <h2>Kebijakan</h2>
    <form method="post" action="/kelola/pengaturan">@csrf
        <label>Definisi tingkat regional<textarea name="regional_definition" rows="3" required maxlength="1000">{{ old('regional_definition', $settings['regional_definition']) }}</textarea></label>
        <label>Kebijakan pembatalan & pengembalian dana<textarea name="refund_policy" rows="4" required maxlength="3000">{{ old('refund_policy', $settings['refund_policy']) }}</textarea></label>
        <label>Awalan nomor sertifikat<input name="certificate_prefix" required maxlength="30" value="{{ old('certificate_prefix', $settings['certificate_prefix']) }}"></label>
        @php($openDays = array_map('intval', explode(',', (string) $settings['operating_days'])))
        <fieldset class="plain-fieldset"><legend>Jam operasional kampus (WITA)</legend>
            <p class="form-note">Kegiatan UKM di luar hari dan jam ini, atau yang meminjam ruangan, memerlukan izin kemahasiswaan.</p>
            <div class="inline-actions">@foreach([1 => 'Sen', 2 => 'Sel', 3 => 'Rab', 4 => 'Kam', 5 => 'Jum', 6 => 'Sab', 7 => 'Min'] as $day => $label)<label class="checkbox-label"><input type="checkbox" name="operating_days[]" value="{{ $day }}" @checked(in_array($day, $openDays, true))><span>{{ $label }}</span></label>@endforeach</div>
            <div class="form-grid"><label>Buka<input type="time" name="operating_open" required value="{{ $settings['operating_open'] }}"></label><label>Tutup<input type="time" name="operating_close" required value="{{ $settings['operating_close'] }}"></label></div>
        </fieldset>
        <button class="button primary">Simpan kebijakan</button>
    </form>
    <hr>
    <h3>Kebijakan lain dari konfigurasi lingkungan</h3>
    <dl><dt>Aturan keanggotaan</dt><dd>HIMAJU wajib dan otomatis sesuai jurusan; BEM/MPM ditetapkan dari hasil pemilihan; maksimal satu UKM {{ strtolower(implode(', ', $master->singleChoiceFields())) }}; UKM lain bebas.</dd><dt>Biaya pendaftaran UKM</dt><dd>Gratis — biaya lomba tidak berlaku untuk UKM</dd><dt>Penyimpanan dokumen</dt><dd>{{ config('brudel.document_disk') }} (privat)</dd></dl>
</section>

<section class="panel" id="rekening">
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

<section class="panel" id="prodi">
    <h2>Jurusan & program studi</h2>
    <p>Poltekkes Kemenkes Manado memiliki 7 jurusan: {{ $departments->pluck('name')->join(', ') }}. Setiap program studi wajib berada di salah satu jurusan; persetujuan lomba dan laporan dihitung per jurusan.</p>
    <div class="table-wrap"><table class="compact"><thead><tr><th>Jurusan</th><th>Program studi</th><th>Kode</th><th>Mahasiswa</th><th>Ubah</th></tr></thead><tbody>
    @foreach($programs as $program)
    <tr><td>{!! $program->department ? e($program->department->name) : '<span class="badge status-rejected">Belum dipetakan</span>' !!}</td><td>{{ $program->name }}</td><td>{{ $program->code ?? '—' }}</td><td>{{ $program->users_count }}</td>
    <td><details><summary class="text-link">Ubah</summary><form class="inline-form" method="post" action="/kelola/prodi/{{ $program->id }}">@csrf<select name="department_id" required>@foreach($departments as $department)<option value="{{ $department->id }}" @selected($program->department_id === $department->id)>{{ $department->name }}</option>@endforeach</select><input name="name" value="{{ $program->name }}" required><input name="code" value="{{ $program->code }}" placeholder="Kode"><button class="button outline small">Simpan</button></form></details></td></tr>
    @endforeach
    </tbody></table></div>
    <form class="inline-form" method="post" action="/kelola/prodi">@csrf<select name="department_id" required><option value="">Pilih jurusan</option>@foreach($departments as $department)<option value="{{ $department->id }}">{{ $department->name }}</option>@endforeach</select><input name="name" required placeholder="Nama program studi"><input name="code" placeholder="Kode (untuk impor)"><button class="button primary small">Tambah prodi</button></form>
</section>
</x-layout>
