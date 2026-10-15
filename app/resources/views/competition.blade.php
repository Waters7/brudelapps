<x-layout :title="$competition->name">
<a class="back-link" href="/lomba">← Jelajahi Lomba</a>
<div class="detail-header"><span class="large-icon {{ strtolower($competition->field) }}"><x-icon name="trophy"/></span><div><span class="eyebrow">{{ strtoupper($competition->field) }} · {{ strtoupper($competition->level) }} · {{ $competition->event->pathway === 'delegation' ? 'SELEKSI DELEGASI' : 'LOMBA INTERNAL' }}</span><h1>{{ $competition->name }}</h1><p>{{ $competition->event->name }}</p></div></div>
<div class="detail-columns">
<section class="panel"><h2>Tentang kompetisi</h2><p class="prewrap">{{ $competition->description }}</p><hr>
<dl>
    <dt>Penyelenggara</dt><dd>{{ $competition->event->organizer }}@if($competition->event->external_organizer) · {{ $competition->event->external_organizer }}@endif</dd>
    <dt>Format peserta</dt><dd>{{ $competition->format==='team'?'Tim · '.$competition->min_members.'–'.$competition->max_members.' anggota':'Individu' }}</dd>
    <dt>Jadwal & lokasi</dt><dd>{{ \App\Services\Wita::show($competition->starts_at) }} WITA{{ $competition->ends_at ? ' – '.\App\Services\Wita::show($competition->ends_at) : '' }} · {{ $competition->location }}</dd>
    <dt>Persyaratan</dt><dd class="prewrap">{{ $competition->requirements }}</dd>
    <dt>Batas pendaftaran</dt><dd>{{ \App\Services\Wita::show($competition->closes_at) }} WITA</dd>
    <dt>Kuota</dt><dd>{{ $competition->quota }} {{ $competition->format==='team'?'tim':'peserta' }} · {{ $holding }} pendaftaran terkirim</dd>
    <dt>Biaya</dt><dd>{{ $competition->feeLabel() }}@if($competition->fee_notes)<br><small class="muted">{{ $competition->fee_notes }}</small>@endif</dd>
    <dt>Format penilaian</dt><dd>{{ ['none' => 'Ditentukan penyelenggara', 'bracket' => 'Sistem gugur (bagan)', 'rubric' => 'Rubrik penilaian juri'][$competition->assessment_type] }}</dd>
    @if($competition->requires_program_approval)<dt>Persetujuan jurusan</dt><dd>Wajib. Setiap jurusan anggota tim meninjau sebelum admin event.</dd>@endif
    @if($competition->external_info)<dt>Informasi penyelenggara eksternal</dt><dd class="prewrap">{{ $competition->external_info }}</dd>@endif
</dl>
@if($competition->live_score)<a class="button outline" href="/live/{{ $competition->id }}"><x-icon name="clock"/> Live score {{ $competition->live_visibility === 'public' ? '(publik)' : '(akun BRUDEL)' }}</a>@endif
</section>
<section class="panel form-panel"><span class="eyebrow">LANGKAH PERTAMAMU</span><h2>Siapkan pendaftaran</h2>
<p>Simpan draf terlebih dahulu. Lengkapi {{ $competition->format==='team'?'anggota tim dan ':'' }}persyaratan sebelum mengirim.</p>
@if($competition->isPaid())
    <div class="info-strip"><x-icon name="file"/><span>Tagihan: <strong>{{ $competition->feeLabel() }}</strong>. Nominal final tampil sebelum pendaftaran dikirim dan tersimpan sebagai tagihan.</span></div>
    @if($accounts->isEmpty())<div class="alert warning">Rekening resmi kampus belum dikonfigurasi. Draf dapat disimpan, tetapi pendaftaran berbayar belum dapat dikirim.</div>@endif
    <p class="form-note">{{ $refundPolicy }}</p>
@endif
@if(!auth()->user()->nim)
    <div class="alert warning">Pendaftaran lomba hanya untuk akun mahasiswa.</div>
@elseif($competition->isOpen() && (!$draft || in_array($draft->status, ['draft', 'revision', 'cancelled'])))
<form action="/lomba/{{ $competition->id }}/draft" method="post">@csrf
    @if($competition->format==='team')<label>Nama tim<input name="team_name" required minlength="3" maxlength="80" value="{{ old('team_name', $draft?->team_name) }}" placeholder="Nama yang mewakili semangat timmu"></label>@endif
    <label>Motivasi mengikuti lomba<textarea name="motivation" required minlength="20" maxlength="2000" rows="5" placeholder="Ceritakan kesiapan dan motivasimu (minimal 20 karakter).">{{ old('motivation', $draft?->motivation) }}</textarea></label>
    <button class="button primary full">{{ $draft && $draft->status !== 'cancelled' ? 'Perbarui draf' : 'Simpan draf' }} <x-icon name="arrow"/></button>
    <p class="form-note">Draf belum mengambil kuota. Kuota diperiksa saat pendaftaran dikirim. Keanggotaan UKM tidak otomatis mendaftarkanmu ke lomba.</p>
</form>
@elseif($draft)
    <div class="alert success">Pendaftaranmu berstatus <x-status :value="$draft->status"/>. <a class="text-link" href="/pendaftaran#registration-{{ $draft->id }}">Lihat detail →</a></div>
@else
    <div class="alert warning">Pendaftaran tidak sedang dibuka.</div>
@endif
</section>
</div>
</x-layout>
