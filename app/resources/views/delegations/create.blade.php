<x-layout title="Usulan Delegasi">
<a class="back-link" href="/delegasi">← Delegasi</a>
<div class="page-heading"><div><p class="eyebrow">USULAN DELEGASI</p><h1>{{ $competition ? 'Dari seleksi '.$competition->name : 'Usulan delegasi' }}</h1><p>Usulan tersimpan sebagai draf, lalu diajukan ke pimpinan. Pimpinan memutuskan; surat tugas diunggah setelah disetujui.</p></div></div>
@if($competition && !$competition->resultsPublished() && in_array($competition->assessment_type, ['bracket', 'rubric']))<div class="alert warning">Hasil seleksi belum disahkan. Pastikan usulan mengikuti hasil resmi.</div>@endif
<section class="panel">
<form class="form-grid" method="post" action="/delegasi">@csrf
    @if($competition)<input type="hidden" name="competition_id" value="{{ $competition->id }}">@endif
    <label class="span-2">Judul usulan<input name="title" required maxlength="190" value="{{ old('title', $competition ? 'Delegasi '.$competition->name : '') }}"></label>
    <label>Kegiatan tujuan<input name="destination_event" required maxlength="190" value="{{ old('destination_event') }}" placeholder="Nama lomba tingkat nasional"></label>
    <label>Penyelenggara tujuan<input name="organizer" required maxlength="190" value="{{ old('organizer', $competition?->event->external_organizer) }}"></label>
    <label>Tingkat<select name="level">@foreach(config('brudel.levels') as $key => $label)<option value="{{ $key }}" @selected((int) old('level', 5) === $key)>{{ $label }}</option>@endforeach</select></label>
    <label>Bidang<select name="field">@foreach(config('brudel.fields') as $field)<option @selected(old('field', $competition?->field) === $field)>{{ $field }}</option>@endforeach</select></label>
    <label>Berangkat/mulai (WITA)<input type="datetime-local" name="starts_at" required value="{{ old('starts_at') }}"></label>
    <label>Selesai (WITA)<input type="datetime-local" name="ends_at" value="{{ old('ends_at') }}"></label>
    <label class="span-2">Lokasi<input name="location" required maxlength="190" value="{{ old('location') }}"></label>
    <fieldset class="span-2"><legend>Peserta delegasi</legend>
        @forelse($candidates as $registration)
            @foreach($registration->participants->where('status', 'confirmed') as $p)
                <label class="checkbox-label"><input type="checkbox" name="participants[]" value="{{ $p->user_id }}" @checked(in_array($p->user_id, old('participants', [])))><span>{{ $p->user->name }} · {{ $registration->displayName() }} @php($rank = $competition?->results->firstWhere('registration_id', $registration->id))@if($rank)<strong>(peringkat {{ $rank->rank }})</strong>@endif</span></label>
            @endforeach
        @empty
            <p class="muted">Tanpa nomor seleksi BRUDEL (khusus kemahasiswaan): masukkan NIM peserta.</p>
            <label>NIM peserta (pisahkan koma)<input name="participant_nims" required maxlength="1000" value="{{ old('participant_nims') }}"></label>
        @endforelse
    </fieldset>
    <label class="span-2">Pendamping/pelatih (email atau NIM, pisahkan koma)<input name="companions" maxlength="1000" value="{{ old('companions') }}"></label>
    <label class="span-2">Alasan usulan<textarea name="reason" required minlength="10" maxlength="3000" rows="4">{{ old('reason') }}</textarea></label>
    <div class="span-2"><button class="button primary">Simpan draf usulan</button></div>
</form>
</section>
</x-layout>
