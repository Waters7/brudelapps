<x-layout title="Jelajah Kegiatan">
<div class="page-heading"><div><p class="eyebrow">JELAJAH KEGIATAN UKM</p><h1>Latihan, pertandingan, dan pertemuan.</h1><p>Kegiatan yang dibuat pelatih/pembina dan Ketua UKM. Hadir? Isi kode kehadiran yang ditampilkan pelatih atau Ketua UKM.</p></div></div>

@if(auth()->user()->nim)
<section class="panel checkin-panel">
    <form class="checkin-form" method="post" action="/kegiatan/hadir">@csrf
        <label>Kode kehadiran<input name="code" required minlength="6" maxlength="6" autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="Contoh: K7M2QA" inputmode="text"></label>
        <button class="button primary">Catat kehadiran</button>
    </form>
    <p class="form-note">Kode berlaku terbatas dan hanya untuk anggota aktif UKM penyelenggara.</p>
</section>
@endif

<form class="filters" method="get">
    <label>UKM<select name="ukm"><option value="">Semua UKM</option>@foreach($clubs as $club)<option value="{{ $club->id }}" @selected(request('ukm') == $club->id)>{{ $club->name }}</option>@endforeach</select></label>
    <label>Jenis<select name="jenis"><option value="">Semua jenis</option>@foreach($master->activityTypes() as $key => $label)<option value="{{ $key }}" @selected(request('jenis') === $key)>{{ $label }}</option>@endforeach</select></label>
    <button class="button outline">Terapkan</button>
</form>

<section class="section-block"><h2>Akan datang & berlangsung</h2>
<div class="catalog-grid">
@forelse($upcoming as $activity)
<a class="panel activity-tile type-{{ $activity->type }}" href="/kegiatan/{{ $activity->id }}">
    <span class="eyebrow">{{ $activity->club->name }} · {{ $activity->typeLabel() }}</span>
    <h3>{{ $activity->title }}</h3>
    <p><x-icon name="calendar"/> {{ \App\Services\Wita::show($activity->starts_at, 'D, d M Y · H:i') }} WITA</p>
    <p class="muted">{{ $activity->location }}</p>
    <div class="inline-actions">@if($activity->starts_at->isPast())<span class="badge status-live">Berlangsung</span>@endif @if(!$activity->is_public)<span class="chip">Internal anggota</span>@endif @if($attended->contains($activity->id))<span class="badge status-accepted">Anda hadir</span>@endif</div>
</a>
@empty<div class="panel empty">Belum ada kegiatan terjadwal.</div>@endforelse
</div></section>

@if($coached->isNotEmpty())
<section class="panel">
    <h2>Buat kegiatan UKM</h2>
    @php($days = collect(explode(',', (string) \App\Models\Setting::value('operating_days')))->map(fn ($day) => [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'][(int) $day] ?? null)->filter()->join(', '))
    <p class="muted">Kegiatan langsung terbit, kecuali meminjam ruangan atau di luar jam operasional kampus ({{ $days }}, {{ \App\Models\Setting::value('operating_open') }}–{{ \App\Models\Setting::value('operating_close') }} WITA). Kegiatan seperti itu menunggu izin Kemahasiswaan Jurusan (HIMAJU) atau Kemahasiswaan Direktorat (BEM, MPM, dan UKM).</p>
    <form class="form-grid" method="post" action="/kegiatan" x-data="{ type: '{{ array_key_first($master->activityTypes()) }}', room: false, kinds: @js(collect($master->activityTypes())->map(fn ($label, $code) => $master->activityReport($code))), internal: @js(collect($master->activityTypes())->map(fn ($label, $code) => $master->activityInternalOnly($code))) }">@csrf
        <label>UKM<select name="club_id" required>@foreach($coached as $club)<option value="{{ $club->id }}">{{ $club->name }}</option>@endforeach</select></label>
        <label>Jenis kegiatan<select name="type" x-model="type">@foreach($master->activityTypes() as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></label>
        <label class="span-2">Judul<input name="title" required maxlength="150" placeholder="Latihan rutin Rabu sore"></label>
        <label>Mulai (WITA)<input type="datetime-local" name="starts_at" required></label>
        <label>Selesai (WITA)<input type="datetime-local" name="ends_at" required></label>
        <label class="span-2">Lokasi<input name="location" required maxlength="190" list="location-options" placeholder="Pilih dari daftar atau ketik lokasi lain"></label><datalist id="location-options">@foreach($master->active('location') as $place)<option value="{{ $place->label }}">@endforeach</datalist>
        <label class="checkbox-label span-2"><input type="hidden" name="needs_room" value="0"><input type="checkbox" name="needs_room" value="1" x-model="room"><span>Perlu meminjam ruangan kampus (memerlukan izin)</span></label>
        <label class="span-2" x-show="room" x-cloak>Ruangan yang dipinjam<input name="room" maxlength="150" list="room-options" placeholder="Mis. Aula Gedung A, Lab Komputer 2" x-bind:required="room"></label><datalist id="room-options">@foreach($master->rooms() as $place)<option value="{{ $place->label }}">{{ $place->meta('capacity') ? 'Kapasitas '.$place->meta('capacity') : '' }}</option>@endforeach</datalist>
        <label class="span-2">Keterangan (opsional)<textarea name="description" rows="2" maxlength="3000"></textarea></label>
        <label class="checkbox-label span-2" x-show="! internal[type]"><input type="hidden" name="is_public" value="0"><input type="checkbox" name="is_public" value="1" checked><span>Tampilkan di halaman depan dan Jelajah Kegiatan (jenis internal selalu tertutup)</span></label>
        <div class="span-2"><button class="button primary">Buat kegiatan</button>
            <p class="form-note" x-show="kinds[type] === 'attendance'">Laporan latihan: daftar hadir dan anggota yang tidak datang dibuat otomatis dari kode kehadiran.</p>
            <p class="form-note" x-show="kinds[type] === 'match'">Laporan pertandingan: lawan, skor, dan catatan penting untuk latihan selanjutnya.</p>
            <p class="form-note" x-show="kinds[type] === 'minutes'">Laporan wajib melampirkan file notulen (PDF/DOCX).</p></div>
    </form>
</section>
@endif

@if($past->isNotEmpty())
<section class="section-block"><h2>Kegiatan sebelumnya</h2>
<div class="panel table-panel"><div class="table-wrap"><table><thead><tr><th>Kegiatan</th><th>Waktu WITA</th><th>Hadir</th><th>Status</th></tr></thead><tbody>
@foreach($past as $activity)<tr><td><a class="text-link" href="/kegiatan/{{ $activity->id }}"><strong>{{ $activity->title }}</strong></a><small>{{ $activity->club->name }} · {{ $activity->typeLabel() }}</small></td><td>{{ \App\Services\Wita::show($activity->starts_at, 'd M Y H:i') }}</td><td>{{ $activity->attendances_count }}</td><td><x-status :value="$activity->status"/></td></tr>@endforeach
</tbody></table></div></div></section>
@endif
</x-layout>
