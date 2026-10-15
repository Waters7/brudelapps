<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Laporan {{ $activity->title }} · BRUDEL</title>
<style>
body{font:13px/1.55 system-ui,'Segoe UI',Arial,sans-serif;color:#183633;background:#f6f8f7;margin:0}
.sheet{max-width:820px;margin:24px auto;background:#fff;padding:36px 42px;border:1px solid #e6ece9;border-radius:8px}
header{display:flex;align-items:center;gap:14px;border-bottom:3px solid #00b9ad;padding-bottom:14px;margin-bottom:18px}header img{width:48px}
h1{font-size:20px;margin:0}h2{font-size:15px;margin:22px 0 8px;color:#007f78}small,.muted{color:#586e62}
table{width:100%;border-collapse:collapse;margin-top:6px}th,td{border:1px solid #dfe7e3;padding:6px 8px;text-align:left;vertical-align:top}th{background:#f1f5f1;font-size:12px}
dl{display:grid;grid-template-columns:180px 1fr;gap:6px 14px;margin:0}dt{font-weight:600}dd{margin:0}
.actions{max-width:820px;margin:16px auto 0;display:flex;gap:10px}.actions button,.actions a{background:#007f78;color:#fff;border:0;border-radius:6px;padding:10px 16px;font:inherit;text-decoration:none;cursor:pointer}
.sign{display:flex;justify-content:flex-end;margin-top:40px}.sign div{text-align:center;width:240px}.sign .line{border-bottom:1px solid #183633;height:60px}
@media print{body{background:#fff}.sheet{border:0;margin:0;padding:0}.actions{display:none}}
</style></head>
<body>
<div class="actions"><button onclick="window.print()">Cetak / Simpan PDF</button><a href="/kegiatan/{{ $activity->id }}/laporan.csv">Unduh CSV kehadiran</a></div>
<main class="sheet">
<header><img src="/brand/logo-mark.svg" alt="Logo BRUDEL"><div><strong>BRUDEL · Poltekkes Kemenkes Manado</strong><br><small>Laporan kegiatan UKM {{ $activity->club->name }}</small></div></header>
<h1>{{ $activity->typeLabel() }}: {{ $activity->title }}</h1>
<dl style="margin-top:12px">
    <dt>Waktu</dt><dd>{{ \App\Services\Wita::show($activity->starts_at, 'l, d F Y · H:i') }}–{{ \App\Services\Wita::show($activity->ends_at, 'H:i') }} WITA</dd>
    <dt>Lokasi</dt><dd>{{ $activity->location }}</dd>
    <dt>Penanggung jawab</dt><dd>{{ $activity->creator->name }}</dd>
    <dt>Status</dt><dd>{{ config('brudel.status_labels')[$activity->status] ?? $activity->status }}{{ $activity->reported_at ? ' · dilaporkan '.\App\Services\Wita::show($activity->reported_at, 'd M Y H:i') : '' }}</dd>
    <dt>Kehadiran</dt><dd>{{ $summary['present']->count() }} hadir · {{ $summary['absent']->count() }} tidak hadir · {{ $summary['members'] }} anggota aktif</dd>
    @if($activity->type === 'persahabatan')<dt>Lawan & skor</dt><dd>{{ $activity->club->name }} {{ $activity->score_home ?? '–' }} : {{ $activity->score_away ?? '–' }} {{ $activity->opponent ?? '(belum diisi)' }}</dd>@endif
    @if($activity->type === 'meeting')<dt>Notulen</dt><dd>{{ $activity->minutes_name ?? 'Belum diunggah (wajib)' }}</dd>@endif
</dl>
@if($activity->description)<h2>Keterangan</h2><p style="white-space:pre-line">{{ $activity->description }}</p>@endif
@if($activity->notes)<h2>{{ $activity->type === 'persahabatan' ? 'Catatan penting untuk selanjutnya' : 'Catatan' }}</h2><p style="white-space:pre-line">{{ $activity->notes }}</p>@endif
<h2>Daftar hadir</h2>
<table><thead><tr><th>No</th><th>Nama</th><th>NIM</th><th>Prodi</th><th>Waktu</th><th>Cara</th></tr></thead><tbody>
@forelse($summary['present'] as $i => $attendance)<tr><td>{{ $i + 1 }}</td><td>{{ $attendance->user->name }}</td><td>{{ $attendance->user->nim }}</td><td>{{ $attendance->user->program?->name }}</td><td>{{ \App\Services\Wita::show($attendance->checked_in_at, 'H:i') }}</td><td>{{ $attendance->method === 'code' ? 'Kode' : 'Manual' }}</td></tr>
@empty<tr><td colspan="6" class="muted">Belum ada kehadiran.</td></tr>@endforelse
</tbody></table>
<h2>Tidak hadir</h2>
<table><thead><tr><th>No</th><th>Nama</th><th>NIM</th><th>Prodi</th></tr></thead><tbody>
@forelse($summary['absent'] as $i => $member)<tr><td>{{ $i + 1 }}</td><td>{{ $member->name }}</td><td>{{ $member->nim }}</td><td>{{ $member->program?->name }}</td></tr>
@empty<tr><td colspan="4" class="muted">Semua anggota aktif hadir.</td></tr>@endforelse
</tbody></table>
<div class="sign"><div>Manado, {{ \App\Services\Wita::show(now(), 'd F Y') }}<br>Penanggung jawab<div class="line"></div>{{ $activity->creator->name }}</div></div>
<p class="muted" style="margin-top:24px">Dibuat dari BRUDEL {{ \App\Services\Wita::show(now(), 'd/m/Y H:i') }} WITA.</p>
</main>
</body></html>
