<x-layout title="Perjadin Delegasi">
<div class="page-heading"><div><p class="eyebrow">KEMAHASISWAAN DIREKTORAT · INTEGRASI PANGI</p><h1>Perjadin delegasi terpilih.</h1><p>Mahasiswa terpilih yang disetujui pimpinan untuk dikirim mengikuti lomba di luar kampus. Data dari BRUDEL disiapkan untuk surat perjalanan dinas dan dokumen pendaftaran lomba di modul mahasiswa perjadin Pangi.</p></div>
<a class="button outline" href="/kelola/perjadin/ekspor">CSV peserta ↓</a></div>

<div class="alert {{ $connected ? 'success' : 'warning' }}"><strong>Status integrasi Pangi:</strong> {{ $connected ? 'alamat dan token Pangi sudah diisi; pengiriman otomatis belum diaktifkan.' : 'belum terhubung. Menu dan data sudah siap; surat dibuat di Pangi setelah integrasi diaktifkan.' }}</div>

<div class="kpi-grid">
    <div class="kpi"><span>Delegasi terpilih</span><strong>{{ $delegations->count() }}</strong></div>
    <div class="kpi"><span>Data siap untuk surat</span><strong>{{ $delegations->filter(fn ($d) => collect($readiness[$d->id])->every(fn ($c) => $c['ok'] || ! $c['required']))->count() }}</strong></div>
    <div class="kpi"><span>Mahasiswa dikirim</span><strong>{{ $delegations->sum(fn ($d) => $d->members->where('role', 'peserta')->count()) }}</strong></div>
    <div class="kpi"><span>Sudah kembali (hasil dicatat)</span><strong>{{ $delegations->where('status', 'completed')->count() }}</strong></div>
</div>

<section class="panel table-panel"><div class="table-wrap"><table><thead><tr><th>Delegasi</th><th>Tujuan & waktu (WITA)</th><th>Rombongan</th><th>Kesiapan data surat</th><th></th></tr></thead><tbody>
@forelse($delegations as $delegation)
@php($checks = collect($readiness[$delegation->id]))
@php($missing = $checks->filter(fn ($c) => ! $c['ok']))
<tr>
    <td><strong>{{ $delegation->title }}</strong><small>{{ $delegation->destination_event }} · {{ config('brudel.levels')[$delegation->level] ?? '' }}</small><x-status :value="$delegation->status"/></td>
    <td>{{ $delegation->location }}<small>{{ \App\Services\Wita::show($delegation->starts_at, 'd M Y') }} – {{ $delegation->ends_at ? \App\Services\Wita::show($delegation->ends_at, 'd M Y') : 'tanggal kembali belum diisi' }}</small>@if($delegation->letter_number)<small>Surat tugas {{ $delegation->letter_number }}</small>@endif</td>
    <td>{{ $delegation->members->where('role', 'peserta')->count() }} mahasiswa<small>{{ $delegation->members->where('role', 'pendamping')->count() }} pendamping</small></td>
    <td>@if($missing->where('required', true)->isEmpty())<span class="badge status-accepted">Lengkap</span> @endif @foreach($missing as $check)<span class="badge {{ $check['required'] ? 'status-rejected' : 'status-submitted' }}">{{ $check['required'] ? 'Kurang' : 'Disarankan' }}: {{ $check['label'] }}</span> @endforeach</td>
    <td><a class="button outline small" href="/kelola/perjadin/{{ $delegation->id }}">Data surat →</a></td>
</tr>
@empty<tr><td colspan="5" class="empty">Belum ada delegasi terpilih. Delegasi diusulkan dari menu Delegasi dan muncul di sini setelah disetujui pimpinan.</td></tr>@endforelse
</tbody></table></div></section>
</x-layout>
