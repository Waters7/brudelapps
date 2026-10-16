<x-layout title="Kelola UKM">
<div class="page-heading"><div><p class="eyebrow">RUANG PENGURUS</p><h1>Organisasi yang Anda kelola.</h1><p>Pengurus hanya melihat BEM, MPM, HIMAJU, atau UKM sesuai penugasan aktif. Kemahasiswaan melihat seluruhnya.</p></div></div>
<div class="catalog-grid">
@forelse($clubs as $club)
<a class="panel club-manage-card" href="/kelola/ukm/{{ $club->id }}">
    <span class="large-icon {{ $club->tone() }}"><x-icon :name="$club->symbol"/></span>
    <span class="badge status-draft">{{ $club->categoryShort() }}</span>
    <h2>{{ $club->name }}</h2>
    <p>{{ $club->active_members }} anggota aktif · {{ $club->open_recruitments }} rekrutmen dibuka</p>
    @unless($club->active)<span class="badge status-rejected">Nonaktif</span>@endunless
    <span class="text-link">Buka dashboard UKM →</span>
</a>
@empty<div class="panel empty">Belum ada UKM dalam lingkup Anda.</div>@endforelse
</div>
@if(auth()->user()->isStudentAffairs())
<section class="panel">
    <h2>Tambah organisasi baru</h2>
    <form class="form-grid" method="post" action="/kelola/ukm">@csrf
        <label>Nama<input name="name" required maxlength="120"></label>
        <label>Kategori<select name="category" required>@foreach(config('brudel.club_categories') as $value => $label)<option value="{{ $value }}" @selected($value === 'ukm')>{{ $label }}</option>@endforeach</select></label>
        <label>Jurusan (khusus HIMAJU)<select name="department_id"><option value="">—</option>@foreach($departments as $department)<option value="{{ $department->id }}">{{ $department->name }}</option>@endforeach</select></label>
        <label>Bidang (wajib untuk UKM)<select name="field"><option value="">— Tanpa bidang (BEM/MPM/HIMAJU)</option>@foreach($master->fields() as $field)<option>{{ $field }}</option>@endforeach</select></label>
        <label>Pembina<input name="mentor" required maxlength="120"></label>
        <label>Kontak resmi<input name="contact" required maxlength="190"></label>
        <label>Ikon<select name="symbol">@foreach(config('brudel.club_symbols') as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
        <label class="span-2">Deskripsi<textarea name="description" required maxlength="3000" rows="3"></textarea></label>
        <div class="span-2"><button class="button primary">Buat organisasi</button></div>
    </form>
</section>
@endif
</x-layout>
