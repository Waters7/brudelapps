<x-layout :title="'Perjadin '.$delegation->title">
<a class="back-link" href="/kelola/perjadin">← Perjadin delegasi</a>
<div class="page-heading"><div><p class="eyebrow">DATA SURAT PERJADIN · {{ strtoupper(config('brudel.levels')[$delegation->level] ?? '') }}</p><h1>{{ $delegation->title }}</h1><p>{{ $delegation->destination_event }} · {{ $delegation->organizer }} · {{ $delegation->location }}</p></div>
<div class="inline-actions"><a class="button outline" href="/delegasi/{{ $delegation->id }}">Buka delegasi</a><a class="button primary" href="/kelola/perjadin/{{ $delegation->id }}/data.json">Unduh data untuk Pangi (JSON) ↓</a></div></div>

<div class="alert {{ $ready ? 'success' : 'warning' }}">{{ $ready ? 'Data wajib lengkap; surat siap dibuat di Pangi setelah integrasi aktif.' : 'Data belum lengkap. Lengkapi melalui halaman delegasi sebelum surat dibuat.' }}</div>

<div class="detail-columns">
<section class="panel">
    <h2>Kesiapan data</h2>
    <ul class="plain-list">@foreach($readiness as $check)<li><span class="badge {{ $check['ok'] ? 'status-accepted' : ($check['required'] ? 'status-rejected' : 'status-submitted') }}">{{ $check['ok'] ? 'Ada' : ($check['required'] ? 'Wajib' : 'Disarankan') }}</span> {{ $check['label'] }}</li>@endforeach</ul>
    <hr>
    <h2>Dasar dan kegiatan</h2>
    <dl>
        <dt>Keputusan pimpinan</dt><dd>{{ $delegation->decider ? $delegation->decider->name.' · '.\App\Services\Wita::show($delegation->decided_at, 'd M Y') : 'Belum diputuskan' }}</dd>
        <dt>Nomor surat tugas BRUDEL</dt><dd>{{ $delegation->letter_number ?? 'Belum diunggah' }}</dd>
        <dt>Kegiatan</dt><dd>{{ $delegation->destination_event }} ({{ $delegation->field }})</dd>
        <dt>Penyelenggara</dt><dd>{{ $delegation->organizer }}</dd>
        <dt>Tujuan</dt><dd>{{ $delegation->location }}</dd>
        <dt>Berangkat – kembali (WITA)</dt><dd>{{ \App\Services\Wita::show($delegation->starts_at, 'd M Y H:i') }} – {{ $delegation->ends_at ? \App\Services\Wita::show($delegation->ends_at, 'd M Y H:i') : 'belum diisi' }}</dd>
        @if($delegation->competition)<dt>Hasil seleksi</dt><dd>{{ $delegation->competition->event->name }} · {{ $delegation->competition->name }}</dd>@endif
        <dt>Alasan pengiriman</dt><dd class="prewrap">{{ $delegation->reason }}</dd>
    </dl>
</section>
<section class="panel">
    <h2>Surat yang akan dibuat di Pangi</h2>
    <p class="muted">Tombol aktif setelah integrasi modul mahasiswa perjadin Pangi tersambung.</p>
    <ul class="plain-list">@foreach(\App\Services\Delegation\PerjadinService::LETTERS as $key => $label)<li class="list-row"><span>{{ $label }}</span><button class="button outline small" type="button" disabled title="Menunggu integrasi Pangi">Buat di Pangi</button></li>@endforeach</ul>
    <hr>
    <h2>Dokumen pendaftaran lomba</h2>
    <p class="muted">Daftar dari Master Data → Dokumen pendaftaran lomba; kemahasiswaan direktorat dapat menambah atau mengubahnya.</p>
    <ul class="plain-list">@forelse($master->active('registration_document') as $document)<li class="list-row"><span>{{ $document->label }}@if($document->meta('per_student'))<small class="muted"> · per mahasiswa</small>@endif</span><span class="badge status-draft">Disiapkan</span></li>@empty<li class="muted">Belum ada dokumen di master data.</li>@endforelse</ul>
</section>
</div>

<section class="panel table-panel"><div class="table-wrap"><table><thead><tr><th>Peran</th><th>Nama</th><th>NIM</th><th>Prodi</th><th>Jurusan</th><th>Email</th></tr></thead><tbody>
@foreach($delegation->members->sortBy(fn ($m) => $m->role === 'peserta' ? 0 : 1) as $member)
<tr><td>{{ $member->role === 'peserta' ? 'Peserta' : 'Pendamping' }}</td><td><div class="person"><x-avatar :user="$member->user"/><span>{{ $member->user->name }}</span></div></td><td>{{ $member->user->nim ?? '—' }}</td><td>{{ $member->user->program?->name ?? '—' }}</td><td>{{ $member->user->program?->department?->name ?? '—' }}</td><td>{{ $member->user->email }}</td></tr>
@endforeach
</tbody></table></div></section>
</x-layout>
