<x-layout title="Operator Live Score">
<div class="page-heading"><div><p class="eyebrow">PANEL OPERATOR</p><h1>Perbarui skor sementara.</h1><p>Setiap perubahan tercatat. Bila petugas lain menyimpan lebih dulu, simpanan Anda ditolak agar tidak saling menimpa.</p></div>
@if($selected)<a class="button outline" href="/live/{{ $selected->id }}">Lihat papan live</a>@endif</div>
@if($competitions->count() > 1)
<form class="filters" method="get"><label>Nomor lomba<select name="lomba" onchange="this.form.submit()">@foreach($competitions as $c)<option value="{{ $c->id }}" @selected($selected?->id === $c->id)>{{ $c->name }} · {{ $c->event->name }}</option>@endforeach</select></label></form>
@endif
@if(!$selected)<div class="panel empty">Tidak ada nomor lomba dengan live score dalam penugasan Anda.</div>@endif

@foreach($matches as $m)
<article class="panel match-operator">
    <div class="section-heading"><div><span class="eyebrow">Babak {{ $m->round }} · #{{ $m->position }} · versi {{ $m->version }} · {{ \App\Services\Wita::show($m->updated_at, 'H:i:s') }} WITA</span><h2>{{ $m->sideA?->displayName() ?? 'Menunggu' }} vs {{ $m->sideB?->displayName() ?? 'Menunggu' }}</h2></div><x-status :value="$m->status"/></div>
    @if($m->sideA && $m->sideB && $m->status !== 'finished')
    <form class="inline-form" method="post" action="/operator/pertandingan/{{ $m->id }}">@csrf<input type="hidden" name="version" value="{{ $m->version }}">
        <label>Skor {{ $m->sideA->displayName() }}<input type="number" name="score_a" min="0" value="{{ $m->score_a }}"></label>
        <label>Skor {{ $m->sideB->displayName() }}<input type="number" name="score_b" min="0" value="{{ $m->score_b }}"></label>
        <label>Set/babak<input name="period_label" maxlength="40" value="{{ $m->period_label }}" placeholder="Set 2"></label>
        <label>Detail<input name="score_detail" maxlength="190" value="{{ $m->score_detail }}" placeholder="21-18, 15-12"></label>
        <label>Status<select name="status"><option value="live" @selected($m->status === 'live')>Berlangsung</option><option value="scheduled" @selected($m->status === 'scheduled')>Terjadwal/jeda</option></select></label>
        <button class="button primary small">Simpan skor sementara</button>
    </form>
    <p class="form-note">Penetapan pemenang resmi dilakukan dari halaman operasional lomba oleh admin event atau operator.</p>
    <form class="inline-form" method="post" action="/pertandingan/{{ $m->id }}/pemenang">@csrf<input type="hidden" name="version" value="{{ $m->version }}"><input type="hidden" name="score_a" value="{{ $m->score_a }}"><input type="hidden" name="score_b" value="{{ $m->score_b }}"><input type="hidden" name="score_detail" value="{{ $m->score_detail }}">
        <select name="winner" required><option value="">Pemenang resmi</option><option value="{{ $m->sideA->id }}">{{ $m->sideA->displayName() }}</option><option value="{{ $m->sideB->id }}">{{ $m->sideB->displayName() }}</option></select><button class="button outline small" onclick="return confirm('Sahkan pemenang dengan skor tersimpan?')">Sahkan pemenang</button></form>
    @elseif($m->status === 'finished')<p class="muted">Hasil resmi: {{ $m->score_a }}–{{ $m->score_b }} {{ $m->score_detail }}</p>@endif
</article>
@endforeach

@if($entries->isNotEmpty())
<section class="panel"><h2>Status tampil</h2>
@foreach($entries as $entry)
<form class="list-row" method="post" action="/operator/tampil/{{ $entry->id }}">@csrf<div><strong>{{ $entry->displayName() }}</strong><small><x-status :value="$entry->performance_status ?? 'waiting'"/></small></div>
<div class="inline-form"><input type="number" name="order" min="1" max="999" value="{{ $entry->performance_order }}" placeholder="Urutan" aria-label="Urutan tampil"><select name="status"><option value="waiting" @selected($entry->performance_status === 'waiting')>Menunggu</option><option value="performing" @selected($entry->performance_status === 'performing')>Sedang tampil</option><option value="done" @selected($entry->performance_status === 'done')>Selesai</option></select><button class="button outline small">Simpan</button></div></form>
@endforeach
</section>
@endif
</x-layout>
