<x-layout title="Izin Kegiatan">
<div class="page-heading"><div><p class="eyebrow">KEMAHASISWAAN</p><h1>Izin kegiatan organisasi.</h1><p>Kegiatan yang meminjam ruangan atau berlangsung di luar jam operasional kampus. HIMAJU diputuskan Kemahasiswaan Jurusan; BEM, MPM, dan UKM diputuskan Kemahasiswaan Direktorat.</p></div></div>

<section class="section-block">
<div class="section-heading"><h2>Menunggu keputusan <span class="count-pill">{{ $pending->count() }}</span></h2></div>
<div class="panel table-panel"><div class="table-wrap"><table><thead><tr><th>Kegiatan</th><th>Waktu (WITA)</th><th>Alasan izin</th><th>Keputusan</th></tr></thead><tbody>
@forelse($pending as $activity)
<tr><td><a class="text-link" href="/kegiatan/{{ $activity->id }}">{{ $activity->title }}</a><small>{{ $activity->club->name }} · {{ $activity->typeLabel() }} · diajukan {{ $activity->creator->name }}</small></td>
<td>{{ \App\Services\Wita::show($activity->starts_at, 'd M Y H:i') }}–{{ \App\Services\Wita::show($activity->ends_at, 'H:i') }}<small>{{ $activity->location }}</small></td>
<td>{{ $activity->permit_reasons }}@if($activity->room)<small>Ruangan: {{ $activity->room }}</small>@endif</td>
<td><form class="inline-form" method="post" action="/kegiatan/{{ $activity->id }}/izin">@csrf<input name="note" maxlength="1000" placeholder="Catatan (wajib bila menolak)"><button class="button primary small" name="decision" value="approved">Setujui</button><button class="button outline small danger" name="decision" value="rejected">Tolak</button></form></td></tr>
@empty<tr><td colspan="4" class="empty">Tidak ada izin yang menunggu keputusan Anda.</td></tr>@endforelse
</tbody></table></div></div>
</section>

@if($decided->isNotEmpty())
<section class="section-block">
<div class="section-heading"><h2>Keputusan terakhir</h2></div>
<div class="panel"><ul class="plain-list">@foreach($decided as $activity)<li><a class="text-link" href="/kegiatan/{{ $activity->id }}">{{ $activity->title }}</a> <span class="muted">· {{ $activity->club->name }} · {{ $activity->permit_status === 'approved' ? 'Disetujui' : 'Ditolak' }} oleh {{ $activity->decider?->name }}{{ $activity->permit_note ? ' · '.$activity->permit_note : '' }}</span></li>@endforeach</ul></div>
</section>
@endif
</x-layout>
