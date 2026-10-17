<x-layout title="Pengumuman UKM">
<div class="page-heading"><div><p class="eyebrow">KEMAHASISWAAN DIREKTORAT</p><h1>Pengumuman untuk organisasi.</h1><p>Satu pengumuman untuk banyak organisasi sekaligus. Pengumuman tampil di ruang setiap organisasi sasaran dan di dashboard anggotanya dengan label Kemahasiswaan Direktorat, terpisah dari pengumuman yang dibuat ketua atau pengurus UKM. Anggota aktif dan pembina organisasi sasaran mendapat notifikasi.</p></div></div>

<section class="panel">
    <h2>Buat pengumuman</h2>
    <form class="form-grid" method="post" action="/kelola/pengumuman">@csrf
        <label class="span-2">Judul<input name="title" required maxlength="150" value="{{ old('title') }}" placeholder="Mis. Pendataan kegiatan semester genap"></label>
        <label class="span-2">Isi pengumuman<textarea name="body" required maxlength="5000" rows="6">{{ old('body') }}</textarea></label>
        <label>Sasaran<select name="audience" required>@foreach(\App\Models\Broadcast::AUDIENCES as $key => $label)<option value="{{ $key }}" @selected(old('audience', 'semua') === $key)>{{ $label }}</option>@endforeach</select></label>
        <label>Tampil sampai (opsional)<input type="date" name="expires_at" value="{{ old('expires_at') }}"></label>
        <label class="checkbox-label span-2"><input type="hidden" name="is_public" value="0"><input type="checkbox" name="is_public" value="1" @checked(old('is_public'))><span>Tampilkan juga di halaman login (dapat dibaca siapa saja, termasuk yang belum masuk)</span></label>
        <details class="span-2 collapsible" @if(old('audience') === 'pilihan') open @endif>
            <summary><strong>Pilih organisasi</strong> <span class="muted">· hanya untuk sasaran "Organisasi tertentu"</span></summary>
            @foreach(['lembaga' => 'BEM & MPM', 'himaju' => 'HIMAJU', 'ukm' => 'UKM'] as $category => $categoryLabel)
            @if($clubs->has($category))
            <p class="eyebrow" style="margin-top:14px">{{ $categoryLabel }}</p>
            <div class="chip-row">@foreach($clubs[$category] as $club)<label class="chip"><input type="checkbox" name="clubs[]" value="{{ $club->id }}" @checked(in_array($club->id, old('clubs', []))) style="margin:0"> {{ $club->name }}</label>@endforeach</div>
            @endif
            @endforeach
        </details>
        <div class="span-2"><button class="button primary"><x-icon name="megaphone"/> Terbitkan & beri tahu anggota</button></div>
    </form>
</section>

<section class="section-block">
<h2>Pengumuman terbit <span class="count-pill">{{ $broadcasts->total() }}</span></h2>
<div class="space-y-4">
@forelse($broadcasts as $broadcast)
    <div>
        <x-broadcast-card :broadcast="$broadcast"/>
        <div class="mt-2 flex flex-wrap items-center gap-3 text-xs text-brand-ink/60">
            <span>{{ $broadcast->clubs_count }} organisasi sasaran</span>
            @if($broadcast->is_public)<span class="badge status-accepted">Tampil di halaman login</span>@endif
            @if($broadcast->expires_at && $broadcast->expires_at->isPast())<span class="badge status-draft">Sudah tidak tampil</span>@endif
            <form method="post" action="/kelola/pengumuman/{{ $broadcast->id }}/hapus" onsubmit="return confirm('Hapus pengumuman ini dari semua organisasi?')">@csrf<button class="text-link plain">Hapus</button></form>
        </div>
    </div>
@empty
    <div class="panel empty"><x-icon name="megaphone"/><p>Belum ada pengumuman.</p></div>
@endforelse
</div>
{{ $broadcasts->links() }}
</section>
</x-layout>
