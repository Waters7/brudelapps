<x-layout :title="'Nilai '.$competition->name">
<a class="back-link" href="/penilaian">← Penugasan penilaian</a>
<div class="page-heading"><div><p class="eyebrow">LEMBAR NILAI · {{ $competition->event->name }}</p><h1>{{ $competition->name }}</h1><p>Nilai 0–100 per kriteria. Nilai akhir = rata-rata penilai atas Σ(nilai × bobot).</p></div></div>
@if($competition->resultsPublished())<div class="alert success">Hasil sudah disahkan. Nilai terkunci.</div>@endif
@if($competition->criteria->isEmpty())<div class="alert warning">Lembar nilai belum dapat diisi: rubrik belum disusun oleh pengelola lomba. Hubungi pengelola lomba atau admin event; lembar terbuka otomatis setelah rubrik disimpan.</div>@endif
@if($entries->isEmpty())<div class="panel empty">Belum ada peserta yang diterima pada nomor lomba ini. Peserta muncul di sini setelah pendaftarannya diterima pengelola lomba.</div>@endif
@foreach($entries as $entry)
@php($mine = ($scores[$entry->id] ?? collect())->keyBy('rubric_criterion_id'))
@php($conflict = $entry->participants->contains('user_id', auth()->id()))
<article class="panel" id="entry-{{ $entry->id }}">
    <div class="section-heading"><div><span class="eyebrow">{{ $entry->performance_order ? 'Urutan tampil '.$entry->performance_order : 'Peserta' }}</span><h2>{{ $entry->displayName() }}</h2></div><span class="badge {{ $mine->count() === $competition->criteria->count() && $mine->isNotEmpty() ? 'status-accepted' : 'status-submitted' }}">{{ $mine->count() }}/{{ $competition->criteria->count() }} kriteria</span></div>
    @if($conflict)<div class="alert warning">Anda tercatat sebagai anggota peserta ini. Penilaian tidak diizinkan.</div>
    @elseif(!$competition->resultsPublished() && $competition->criteria->isNotEmpty())
    <form method="post" action="/penilaian/{{ $competition->id }}/{{ $entry->id }}">@csrf
        <div class="table-wrap"><table class="compact"><thead><tr><th>Kriteria</th><th>Bobot</th><th>Nilai (0–100)</th><th>Catatan</th></tr></thead><tbody>
        @foreach($competition->criteria as $criterion)
            <tr><td>{{ $criterion->name }}@if($criterion->description)<small>{{ $criterion->description }}</small>@endif</td><td>{{ rtrim(rtrim(number_format($criterion->weight, 2, ',', ''), '0'), ',') }}%</td>
            <td><input type="number" step="0.01" min="0" max="100" name="scores[{{ $criterion->id }}][score]" value="{{ $mine[$criterion->id]->score ?? '' }}" aria-label="Nilai {{ $criterion->name }}"></td>
            <td><input name="scores[{{ $criterion->id }}][note]" maxlength="500" value="{{ $mine[$criterion->id]->note ?? '' }}"></td></tr>
        @endforeach
        </tbody></table></div>
        <button class="button primary">Simpan nilai</button>
    </form>
    @endif
</article>
@endforeach
</x-layout>
