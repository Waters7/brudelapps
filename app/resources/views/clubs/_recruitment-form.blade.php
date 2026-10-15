<form class="form-grid" method="post" action="/kelola/ukm/{{ $club->id }}/rekrutmen{{ $recruitment ? '/'.$recruitment->id : '' }}">@csrf
    <label class="span-2">Nama periode<input name="name" required maxlength="120" value="{{ $recruitment?->name }}" placeholder="Rekrutmen semester ganjil 2026"></label>
    <label>Dibuka (WITA)<input type="datetime-local" name="opens_at" required value="{{ \App\Services\Wita::input($recruitment?->opens_at) }}"></label>
    <label>Ditutup (WITA)<input type="datetime-local" name="closes_at" required value="{{ \App\Services\Wita::input($recruitment?->closes_at) }}"></label>
    <label>Kuota penerimaan<input type="number" name="quota" required min="1" max="5000" value="{{ $recruitment?->quota ?? 30 }}"></label>
    <label class="checkbox-label"><input type="hidden" name="requires_document" value="0"><input type="checkbox" name="requires_document" value="1" @checked($recruitment?->requires_document)><span>Wajib unggah dokumen PDF</span></label>
    <label class="span-2">Syarat<textarea name="requirements" required maxlength="3000" rows="3">{{ $recruitment?->requirements }}</textarea></label>
    <label class="span-2">Seleksi (opsional)<textarea name="selection_notes" maxlength="3000" rows="2" placeholder="Tahap wawancara/audisi bila diperlukan">{{ $recruitment?->selection_notes }}</textarea></label>
    <div class="span-2"><button class="button primary">{{ $recruitment ? 'Simpan perubahan' : 'Buka rekrutmen' }}</button></div>
</form>
