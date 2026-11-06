<x-layout title="Cara Penggunaan">
<div class="page-heading"><div><p class="eyebrow">PANDUAN PENGGUNA</p><h1>Cara penggunaan BRUDEL.</h1><p>Langkah singkat sesuai peran Anda. Aturan resmi kegiatan ada di <a class="text-link" href="/pedoman">SOP & Pedoman</a>.</p></div></div>
@foreach($sections as $key => $section)
<details class="panel collapsible" id="{{ $key }}" @if($loop->first) open @endif><summary><strong>{{ $section['title'] }}</strong></summary>
    <ul class="plain-list">@foreach($section['steps'] as $step)<li><strong>{{ $loop->iteration }}.</strong> {{ $step }}</li>@endforeach</ul>
</details>
@endforeach
<p class="muted">Masih ada kendala? Gunakan tombol Bantuan di kanan bawah untuk menghubungi kemahasiswaan.</p>
</x-layout>
