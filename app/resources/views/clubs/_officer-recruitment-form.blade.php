@php($saved = $recruitment ?? null)
<form class="form-grid" method="post" action="{{ $action }}">@csrf
    <label class="span-2">Judul<input name="title" required minlength="5" maxlength="150" value="{{ old('title', $saved?->title) }}" placeholder="Mis. Rekrutmen Pengurus {{ $club->categoryShort() }} 2027"></label>
    <label>Periode kepengurusan<input name="period" required maxlength="30" value="{{ old('period', $saved?->period) }}" placeholder="Mis. 2026/2027"></label>
    <label class="span-2">Keterangan (opsional)<textarea name="description" rows="2" maxlength="2000">{{ old('description', $saved?->description) }}</textarea></label>
    <label class="span-2">Kriteria, satu per baris<textarea name="criteria" required rows="5" maxlength="3000" placeholder="Mahasiswa aktif minimal semester 3&#10;Pernah menjadi panitia kegiatan organisasi&#10;Bersedia aktif selama masa jabatan">{{ old('criteria', $saved ? implode("\n", $saved->criteria) : '') }}</textarea></label>
    <fieldset class="span-2"><legend>Jabatan yang dibuka</legend>
        @foreach($positions as $position)<label class="checkbox-label"><input type="checkbox" name="positions[]" value="{{ $position }}" @checked(in_array($position, old('positions', $saved?->positions ?? []), true))><span>{{ $position }}</span></label>@endforeach
    </fieldset>
    <label>Pendaftaran dibuka (WITA)<input type="datetime-local" name="opens_at" required value="{{ old('opens_at', \App\Services\Wita::input($saved?->opens_at ?? now())) }}"></label>
    <label>Pendaftaran ditutup (WITA)<input type="datetime-local" name="closes_at" required value="{{ old('closes_at', \App\Services\Wita::input($saved?->closes_at ?? now()->addWeeks(2))) }}"></label>
    <label>Masa jabatan mulai (WITA)<input type="datetime-local" name="term_starts_at" required value="{{ old('term_starts_at', \App\Services\Wita::input($saved?->term_starts_at ?? now()->addMonth())) }}"></label>
    <label>Masa jabatan berakhir (WITA)<input type="datetime-local" name="term_ends_at" required value="{{ old('term_ends_at', \App\Services\Wita::input($saved?->term_ends_at ?? now()->addMonth()->addYear())) }}"></label>
    <div class="span-2"><button class="button primary">Simpan rekrutmen</button><p class="form-note">Kriteria dan jabatan terkunci setelah ada kandidat.</p></div>
</form>
