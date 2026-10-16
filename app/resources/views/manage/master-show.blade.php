<x-layout :title="$spec['label']">
<a class="back-link" href="/kelola/master">← Master data</a>
<div class="page-heading"><div><p class="eyebrow">MASTER DATA</p><h1>{{ $spec['label'] }}</h1><p>{{ $spec['description'] }}</p></div></div>

@php
    $metaInputs = function (?\App\Models\MasterOption $option) use ($spec, $master) {
        return collect($spec['meta'])->map(fn ($field, $key) => ['key' => $key, 'field' => $field, 'value' => $option?->meta($key)]);
    };
@endphp

<section class="panel">
    <h2>Tambah pilihan</h2>
    <form class="form-grid" method="post" action="/kelola/master/{{ $type }}">@csrf
        <label class="span-2">Nama<input name="label" required maxlength="120"></label>
        @include('manage._master-meta', ['inputs' => $metaInputs(null)])
        <div class="span-2"><button class="button primary">Tambah</button></div>
    </form>
</section>

<section class="section-block">
<div class="section-heading"><h2>Daftar pilihan <span class="count-pill">{{ $options->count() }}</span></h2></div>
@forelse($options as $option)
<details class="panel collapsible master-row {{ $option->active ? '' : 'is-off' }}">
    <summary>
        <span class="master-position">{{ $option->position }}</span>
        <strong>{{ $option->label }}</strong>
        <span class="badge {{ $option->active ? 'status-accepted' : 'status-draft' }}">{{ $option->active ? 'Aktif' : 'Nonaktif' }}</span>
        @foreach($spec['meta'] as $key => $field)
            @php($value = $option->meta($key))
            @if($field['type'] === 'bool' && $value)<span class="chip">{{ \Illuminate\Support\Str::before($field['label'], ' (') }}</span>
            @elseif($field['type'] === 'select' && $value)<span class="chip">{{ $field['options'][$value] ?? $value }}</span>
            @elseif(in_array($field['type'], ['number', 'field'], true) && $value !== null)<span class="chip">{{ $field['label'] }}: {{ $value }}</span>@endif
        @endforeach
        <span class="muted">{{ $usage[$option->id] ? 'Dipakai '.$usage[$option->id].' data' : 'Belum dipakai' }} · kode {{ $option->code }}</span>
    </summary>
    <form class="form-grid" method="post" action="/kelola/master/{{ $type }}/{{ $option->id }}">@csrf
        <label class="span-2">Nama<input name="label" required maxlength="120" value="{{ $option->label }}"></label>
        <label>Urutan<input type="number" name="position" min="1" max="999" value="{{ $option->position }}"></label>
        <label class="checkbox-label"><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" @checked($option->active)><span>Aktif (muncul sebagai pilihan)</span></label>
        @include('manage._master-meta', ['inputs' => $metaInputs($option)])
        @if(in_array($type, ['field', 'officer_position'], true))<p class="form-note span-2">Mengganti nama juga memperbarui data yang sudah memakai pilihan ini.</p>@endif
        <div class="span-2 inline-actions"><button class="button primary small">Simpan</button></div>
    </form>
    @if(! $usage[$option->id])<form method="post" action="/kelola/master/{{ $type }}/{{ $option->id }}/hapus" onsubmit="return confirm('Hapus pilihan ini?')">@csrf<button class="text-link plain danger">Hapus pilihan</button></form>@endif
</details>
@empty
<div class="panel empty"><p>Belum ada pilihan. Tambahkan yang pertama di atas.</p></div>
@endforelse
</section>
</x-layout>
