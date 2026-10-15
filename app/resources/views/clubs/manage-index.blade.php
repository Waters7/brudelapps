<x-layout title="Kelola UKM">
<div class="page-heading"><div><p class="eyebrow">RUANG PENGURUS</p><h1>UKM yang Anda kelola.</h1><p>Pengurus hanya melihat UKM sesuai penugasan aktif. Kemahasiswaan melihat seluruh UKM.</p></div></div>
<div class="catalog-grid">
@forelse($clubs as $club)
<a class="panel club-manage-card" href="/kelola/ukm/{{ $club->id }}">
    <span class="large-icon {{ strtolower($club->field) }}"><x-icon :name="$club->symbol"/></span>
    <h2>{{ $club->name }}</h2>
    <p>{{ $club->active_members }} anggota aktif · {{ $club->open_recruitments }} rekrutmen dibuka</p>
    @unless($club->active)<span class="badge status-draft">UKM nonaktif</span>@endunless
    <span class="text-link">Buka dashboard UKM →</span>
</a>
@empty<div class="panel empty">Belum ada UKM dalam lingkup Anda.</div>@endforelse
</div>
@if(auth()->user()->isStudentAffairs())
<section class="panel">
    <h2>Tambah UKM baru</h2>
    <form class="form-grid" method="post" action="/kelola/ukm">@csrf
        <label>Nama UKM<input name="name" required maxlength="120"></label>
        <label>Bidang<select name="field" required>@foreach(config('brudel.fields') as $field)<option>{{ $field }}</option>@endforeach</select></label>
        <label>Pembina<input name="mentor" required maxlength="120"></label>
        <label>Kontak resmi<input name="contact" required maxlength="190"></label>
        <label>Ikon<select name="symbol">@foreach(['spark' => 'Bintang', 'racket' => 'Raket', 'ball' => 'Bola', 'music' => 'Musik', 'book' => 'Buku', 'globe' => 'Globe', 'trophy' => 'Piala', 'users' => 'Komunitas'] as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
        <label class="span-2">Deskripsi<textarea name="description" required maxlength="3000" rows="3"></textarea></label>
        <div class="span-2"><button class="button primary">Buat UKM</button></div>
    </form>
</section>
@endif
</x-layout>
