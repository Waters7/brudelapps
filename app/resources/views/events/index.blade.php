<x-layout title="Kelola Event & Lomba">
<div class="page-heading"><div><p class="eyebrow">EVENT INDUK & NOMOR LOMBA</p><h1>Event dan pengajuan lomba.</h1><p>Ketua UKM mengajukan lomba; kemahasiswaan menyetujui, meminta revisi, atau menolak dengan alasan. Event disetujui dikelola admin event yang ditugaskan.</p></div></div>

@if($managed->isNotEmpty())
<section class="section-block"><h2>Lomba yang Anda kelola</h2>
@foreach($managed as $competition)<a class="panel list-row" href="/kelola/lomba/{{ $competition->id }}"><div><strong>{{ $competition->name }}</strong><small>{{ $competition->event->name }} · {{ \App\Services\Wita::show($competition->starts_at, 'd M Y') }}</small></div><span class="text-link">Operasional lomba →</span></a>@endforeach
</section>
@endif

@if($pending->isNotEmpty())
<section class="section-block"><h2>Menunggu keputusan kemahasiswaan · {{ $pending->count() }}</h2>
@foreach($pending as $event)<a class="panel list-row" href="/kelola/event/{{ $event->id }}"><div><strong>{{ $event->name }}</strong><small>{{ $event->club?->name ?? 'Kemahasiswaan' }} · {{ $event->competitions_count }} nomor lomba · diajukan {{ \App\Services\Wita::show($event->submitted_at, 'd M Y H:i') }}</small></div><span class="text-link">Tinjau →</span></a>@endforeach
</section>
@endif

<section class="section-block"><h2>Semua event dalam lingkup Anda</h2>
<div class="panel table-panel"><div class="table-wrap"><table><thead><tr><th>Event</th><th>Jalur</th><th>Pengusul</th><th>Nomor lomba</th><th>Status</th></tr></thead><tbody>
@forelse($events as $event)
<tr><td><a class="text-link" href="/kelola/event/{{ $event->id }}"><strong>{{ $event->name }}</strong></a><small>{{ $event->organizer }}</small></td><td>{{ $event->pathway === 'delegation' ? 'Seleksi delegasi' : 'Internal' }}</td><td>{{ $event->club?->name ?? 'Kemahasiswaan' }}</td><td>{{ $event->competitions_count }}</td><td><x-status :value="$event->status"/></td></tr>
@empty<tr><td colspan="5" class="empty">Belum ada event.</td></tr>@endforelse
</tbody></table></div></div>
</section>

@if($clubs->isNotEmpty() || auth()->user()->isStudentAffairs())
<section class="panel">
    <h2>{{ auth()->user()->isStudentAffairs() ? 'Buat event baru' : 'Ajukan event/lomba UKM' }}</h2>
    <form class="form-grid" method="post" action="/kelola/event">@csrf
        <label class="span-2">Nama event induk<input name="name" required maxlength="150" placeholder="Pekan Seni Mahasiswa 2026"></label>
        <label>Penyelenggara<input name="organizer" required maxlength="190" placeholder="UKM / Kemahasiswaan"></label>
        <label>Jalur<select name="pathway"><option value="internal">Lomba internal</option><option value="delegation">Seleksi delegasi nasional</option></select></label>
        <label>Bidang utama<select name="field"><option value="">Campuran</option>@foreach(config('brudel.fields') as $field)<option>{{ $field }}</option>@endforeach</select></label>
        <label>UKM pengusul<select name="club_id" {{ auth()->user()->isStudentAffairs() ? '' : 'required' }}>@if(auth()->user()->isStudentAffairs())<option value="">Kemahasiswaan (langsung disetujui)</option>@endif @foreach($clubs as $club)<option value="{{ $club->id }}">{{ $club->name }}</option>@endforeach</select></label>
        <label class="span-2">Penyelenggara eksternal (opsional)<input name="external_organizer" maxlength="190" placeholder="Untuk seleksi delegasi: nama penyelenggara tingkat nasional"></label>
        <label class="span-2">Deskripsi<textarea name="description" maxlength="3000" rows="3"></textarea></label>
        <div class="span-2"><button class="button primary">Buat draf event</button><p class="form-note">Pengajuan UKM tersimpan sebagai draf. Tambahkan nomor lomba, lalu ajukan ke kemahasiswaan.</p></div>
    </form>
</section>
@endif
</x-layout>
