<x-layout title="Kelola Sertifikat">
<div class="page-heading"><div><p class="eyebrow">SERTIFIKAT RESMI</p><h1>Terbitkan sertifikat per penerima.</h1><p>Unggah PDF resmi yang sudah disahkan. Anggota tim mendapat sertifikat atas nama masing-masing. Sertifikat hanya terbit setelah hasil disahkan (lomba berpenilaian) atau lomba terlaksana.</p></div><a class="button outline" href="/laporan/ekspor/sertifikat">CSV sertifikat ↓</a></div>
<form class="filters" method="get"><label>Nomor lomba<select name="lomba" onchange="this.form.submit()"><option value="">Pilih nomor lomba</option>@foreach($competitions as $c)<option value="{{ $c->id }}" @selected($selected?->id === $c->id)>{{ $c->name }} · {{ $c->event->name }}</option>@endforeach</select></label></form>

@if($selected)
<section class="panel">
    <h2>Terbitkan untuk {{ $selected->name }}</h2>
    @if(in_array($selected->assessment_type, ['bracket', 'rubric']) && !$selected->resultsPublished())<div class="alert warning">Hasil lomba belum disahkan. Penerbitan akan ditolak.</div>@endif
    <form class="form-grid" method="post" action="/kelola/sertifikat" enctype="multipart/form-data">@csrf
        <input type="hidden" name="competition_id" value="{{ $selected->id }}">
        <label>Penerima<select name="user_id" required><option value="">Pilih peserta</option>@foreach($selected->registrations as $r)@foreach($r->participants->where('status', 'confirmed') as $p)<option value="{{ $p->user_id }}">{{ $p->user->name }} · {{ $r->displayName() }}</option>@endforeach @endforeach</select></label>
        <label>Nomor sertifikat resmi<input name="number" required maxlength="80" placeholder="{{ \App\Models\Setting::value('certificate_prefix') }}/{{ now()->year }}/…"></label>
        <label>Jenis penghargaan<input name="award_type" required maxlength="80" list="award-types" placeholder="Juara 1 / Peserta"><datalist id="award-types"><option>Juara 1</option><option>Juara 2</option><option>Juara 3</option><option>Harapan 1</option><option>Peserta</option><option>Penampil Terbaik</option></datalist></label>
        <label>Tanggal terbit<input type="date" name="issued_on" required value="{{ now()->timezone('Asia/Makassar')->toDateString() }}"></label>
        <label class="span-2">PDF sertifikat resmi (≤ 5 MB)<input type="file" name="file" accept="application/pdf,.pdf" required></label>
        <div class="span-2"><button class="button primary">Terbitkan sertifikat</button></div>
    </form>
</section>
@endif

<section class="section-block"><h2>Sertifikat {{ $selected ? 'nomor lomba ini' : 'dalam lingkup Anda' }}</h2>
<div class="panel table-panel"><div class="table-wrap"><table><thead><tr><th>Nomor & penerima</th><th>Penghargaan</th><th>Status</th><th>Versi</th><th>Tindakan</th></tr></thead><tbody>
@forelse($certificates as $certificate)
<tr><td><strong>{{ $certificate->number }}</strong><small>{{ $certificate->user->name }} · {{ $certificate->title }}</small></td><td>{{ $certificate->award_type }}<small>{{ $certificate->issued_on->format('d/m/Y') }}</small></td><td><x-status :value="$certificate->status"/>@if($certificate->revoked_reason)<small>{{ $certificate->revoked_reason }}</small>@endif</td>
<td>@foreach($certificate->versions as $version)<a class="text-link" href="/sertifikat/{{ $certificate->id }}/unduh?versi={{ $version->version }}">v{{ $version->version }}</a>@if($version->reason)<small>{{ $version->reason }}</small>@endif @endforeach</td>
<td>@if($certificate->status === 'valid')
    <details><summary class="text-link">Ganti / cabut</summary>
        <form class="inline-form" method="post" action="/kelola/sertifikat/{{ $certificate->id }}/ganti" enctype="multipart/form-data">@csrf<input type="file" name="file" accept="application/pdf,.pdf" required><input name="reason" required minlength="5" placeholder="Alasan penggantian"><button class="button outline small">Ganti PDF</button></form>
        <form class="inline-form" method="post" action="/kelola/sertifikat/{{ $certificate->id }}/cabut" onsubmit="return confirm('Cabut sertifikat ini?')">@csrf<input name="reason" required minlength="5" placeholder="Alasan pencabutan"><button class="button outline small danger">Cabut</button></form>
    </details>@endif
    <a class="text-link" href="/verifikasi/{{ $certificate->verification_token }}" target="_blank" rel="noopener">Verifikasi ↗</a></td></tr>
@empty<tr><td colspan="5" class="empty">Belum ada sertifikat.</td></tr>@endforelse
</tbody></table></div></div>
</section>
</x-layout>
