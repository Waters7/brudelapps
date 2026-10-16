<x-layout title="Banner Halaman Depan">
<div class="page-heading"><div><p class="eyebrow">ADMINISTRASI KAMPUS</p><h1>Banner halaman depan.</h1><p>Slide video atau gambar yang bergulir otomatis di halaman depan publik. Tanpa slide, halaman depan memakai tiga banner animasi bawaan BRUDEL.</p></div>
<a class="button outline" href="/" target="_blank" rel="noopener">Lihat halaman depan ↗</a></div>

<section class="panel">
    <h2>Tambah slide</h2>
    <p class="muted">Video: MP4 atau WebM, durasi pendek (10–30 detik), tanpa suara, maksimal 20 MB (batas unggah server saat ini {{ $uploadLimit }}). Gambar: JPG/PNG/WebP minimal 800 × 320 px, maksimal 3 MB, disimpan 1600 × 640 px. Ukuran ideal keduanya 1600 × 640 px (rasio 5:2); teks slide tampil di sisi kiri bawah.</p>
    <form class="form-grid" method="post" action="/kelola/banner-depan" enctype="multipart/form-data">@csrf
        <label class="span-2">Judul<input name="title" required maxlength="120" placeholder="Mis. Pekan Prestasi Mahasiswa 2026"></label>
        <label class="span-2">Keterangan (opsional)<input name="caption" maxlength="300" placeholder="Satu kalimat ajakan"></label>
        <label>Tautan tombol (opsional)<input name="link_url" maxlength="300" placeholder="/live atau https://…"></label>
        <label>Teks tombol<input name="link_label" maxlength="40" placeholder="Selengkapnya"></label>
        <label>Urutan<input type="number" name="position" min="1" max="99" placeholder="otomatis"></label>
        <label>Video atau gambar<input type="file" name="media" required accept="video/mp4,video/webm,image/jpeg,image/png,image/webp"></label>
        <div class="span-2"><button class="button primary">Tambah slide</button></div>
    </form>
</section>

<div class="catalog-grid">
@forelse($slides as $slide)
<article class="panel slide-admin {{ $slide->active ? '' : 'is-off' }}">
    @if($slide->isVideo())<video class="slide-preview" src="{{ $slide->mediaUrl() }}" muted loop playsinline controls preload="metadata"></video>@else<img class="slide-preview" src="{{ $slide->mediaUrl() }}" alt="">@endif
    <div class="section-heading"><div><span class="badge {{ $slide->active ? 'status-accepted' : 'status-draft' }}">{{ $slide->active ? 'Tampil' : 'Disembunyikan' }}</span> <span class="chip">{{ $slide->isVideo() ? 'Video' : 'Gambar' }}</span><h2>{{ $slide->title }}</h2>@if($slide->caption)<p class="muted">{{ $slide->caption }}</p>@endif</div></div>
    @if($slide->link_url)<p class="muted">Tombol: {{ $slide->link_label ?: 'Selengkapnya' }} → {{ $slide->link_url }}</p>@endif
    <form class="inline-form" method="post" action="/kelola/banner-depan/{{ $slide->id }}">@csrf
        <label>Urutan<input type="number" name="position" min="1" max="99" value="{{ $slide->position }}" required></label>
        <input type="hidden" name="active" value="0"><label class="checkbox-label"><input type="checkbox" name="active" value="1" @checked($slide->active)><span>Tampilkan</span></label>
        <button class="button outline small">Simpan</button>
    </form>
    <form method="post" action="/kelola/banner-depan/{{ $slide->id }}/hapus" onsubmit="return confirm('Hapus slide ini?')">@csrf<button class="text-link plain danger">Hapus slide</button></form>
</article>
@empty
<div class="panel empty"><x-icon name="spark"/><p>Belum ada slide. Halaman depan memakai banner animasi bawaan.</p></div>
@endforelse
</div>
</x-layout>
