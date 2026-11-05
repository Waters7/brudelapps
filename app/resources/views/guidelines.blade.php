<x-layout title="SOP & Pedoman">
<div class="page-heading"><div><p class="eyebrow">KEMAHASISWAAN</p><h1>SOP dan pedoman kegiatan non-akademik.</h1><p>Dokumen resmi acuan kegiatan UKM, HIMAJU, BEM, dan MPM yang diterbitkan Kemahasiswaan Direktorat.</p></div></div>
@if(auth()->user()->isStudentAffairs())
<section class="panel"><h2>Terbitkan dokumen</h2>
    <form class="form-grid" method="post" action="/pedoman" enctype="multipart/form-data">@csrf
        <label class="span-2">Judul<input name="title" required maxlength="150" value="{{ old('title') }}" placeholder="Mis. SOP Perizinan Kegiatan Non-Akademik"></label>
        <label class="span-2">Keterangan (opsional)<input name="description" maxlength="500" value="{{ old('description') }}"></label>
        <label class="span-2">Dokumen (PDF ≤ 5 MB)<input type="file" name="document" required accept="application/pdf,.pdf"></label>
        <div class="span-2"><button class="button primary">Terbitkan dokumen</button></div>
    </form>
</section>
@endif
<div class="catalog-grid">
@forelse($guidelines as $guideline)
<article class="panel">
    <span class="large-icon"><x-icon name="book"/></span>
    <h2>{{ $guideline->title }}</h2>
    @if($guideline->description)<p>{{ $guideline->description }}</p>@endif
    <p class="muted">Diterbitkan {{ \App\Services\Wita::show($guideline->created_at, 'd M Y') }}</p>
    <a class="button primary full" href="/pedoman/{{ $guideline->id }}/unduh">Unduh PDF ↓</a>
    @if(auth()->user()->isStudentAffairs())<form method="post" action="/pedoman/{{ $guideline->id }}/hapus" onsubmit="return confirm('Hapus dokumen ini?')">@csrf<button class="text-link plain danger">Hapus dokumen</button></form>@endif
</article>
@empty
<div class="panel empty"><x-icon name="book"/><h2>Dokumen sedang disiapkan.</h2><p>SOP dan pedoman kegiatan non-akademik tampil di sini setelah diterbitkan Kemahasiswaan.</p></div>
@endforelse
</div>
</x-layout>
