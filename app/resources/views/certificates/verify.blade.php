<x-public title="Verifikasi Sertifikat">
<section class="panel narrow verify-card">
    <span class="eyebrow">VERIFIKASI SERTIFIKAT BRUDEL</span>
    @if(!$certificate)
        <h1>Tidak ditemukan.</h1><p>Tautan verifikasi tidak dikenal. Pastikan tautan disalin lengkap atau hubungi kemahasiswaan.</p>
    @else
        <div class="verify-status {{ $certificate->status }}"><x-icon :name="$certificate->status === 'valid' ? 'shield' : 'flag'"/><strong>{{ $certificate->status === 'valid' ? 'Sertifikat sah' : 'Sertifikat telah dicabut' }}</strong></div>
        <dl>
            <dt>Nomor</dt><dd>{{ $certificate->number }}</dd>
            <dt>Penerima</dt><dd>{{ $certificate->user->name }}</dd>
            <dt>Kegiatan</dt><dd>{{ $certificate->title }}</dd>
            <dt>Penghargaan</dt><dd>{{ $certificate->award_type }}</dd>
            <dt>Tanggal terbit</dt><dd>{{ $certificate->issued_on->locale('id')->translatedFormat('d F Y') }}</dd>
            <dt>Versi berlaku</dt><dd>{{ $certificate->current_version }}</dd>
            @if($certificate->status === 'revoked')<dt>Dicabut</dt><dd>{{ \App\Services\Wita::show($certificate->revoked_at, 'd F Y') }}</dd>@endif
        </dl>
        <p class="form-note">Halaman ini hanya menampilkan informasi minimum untuk memeriksa keabsahan. Dokumen PDF tidak dibagikan secara publik.</p>
    @endif
</section>
</x-public>
