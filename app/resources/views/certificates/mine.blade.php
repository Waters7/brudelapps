<x-layout title="Sertifikat Saya">
<div class="page-heading"><div><p class="eyebrow">REKOGNISI</p><h1>Sertifikat saya.</h1><p>PDF resmi atas namamu. Hanya kamu dan pengelola lomba terkait yang dapat mengunduhnya.</p></div></div>
<div class="catalog-grid">
@forelse($certificates as $certificate)
<article class="panel certificate-card {{ $certificate->status }}">
    <span class="eyebrow">{{ $certificate->number }}</span>
    <h2>{{ $certificate->award_type }}</h2>
    <p>{{ $certificate->title }}</p>
    <p class="muted">Terbit {{ $certificate->issued_on->locale('id')->translatedFormat('d F Y') }} · versi {{ $certificate->current_version }}</p>
    <x-status :value="$certificate->status"/>
    @if($certificate->status === 'revoked')<div class="alert error">Dicabut: {{ $certificate->revoked_reason }}</div>@endif
    <div class="inline-actions">
        @if($certificate->status === 'valid')<a class="button primary" href="/sertifikat/{{ $certificate->id }}/unduh"><x-icon name="download"/> Unduh PDF</a>@endif
        <a class="text-link" href="/verifikasi/{{ $certificate->verification_token }}" target="_blank" rel="noopener">Tautan verifikasi ↗</a>
    </div>
</article>
@empty<div class="panel empty"><x-icon name="file"/><h3>Belum ada sertifikat.</h3><p>Sertifikat diterbitkan pengelola setelah tahapan lomba selesai.</p></div>@endforelse
</div>
</x-layout>
