<x-layout :title="'Kelola '.$club->name">
<a class="back-link" href="/kelola/ukm">← Kelola UKM</a>
<div class="page-heading"><div><p class="eyebrow">DASHBOARD {{ strtoupper($club->categoryShort()) }}@if($club->field) · {{ strtoupper($club->field) }}@endif</p><h1>{{ $club->name }}</h1><p>Keanggotaan dipisahkan dari jabatan pengurus. Pergantian ketua tidak menghapus riwayat anggota.</p></div>
<div class="inline-actions"><a class="button primary" href="/ruang/{{ $club->id }}">Ruang organisasi</a><a class="button outline" href="/ukm/{{ $club->id }}">Lihat profil publik</a><a class="button outline" href="/kelola/ukm/{{ $club->id }}/ekspor">CSV anggota ↓</a><a class="button outline" href="/tinjauan">Tinjau pendaftar</a></div></div>

<div class="kpi-grid">
    @foreach(config('brudel.membership_statuses') as $key => $label)<div class="kpi"><span>{{ $label }}</span><strong>{{ $counts[$key] ?? 0 }}</strong></div>@endforeach
    <div class="kpi"><span>Menunggu tinjauan</span><strong>{{ $club->recruitments->sum('pending_count') }}</strong></div>
</div>

<div class="detail-columns">
<section class="panel">
    <h2>Profil organisasi</h2>
    <form method="post" action="/kelola/ukm/{{ $club->id }}">@csrf
        @if(auth()->user()->isStudentAffairs())<label>Nama<input name="name" required maxlength="120" value="{{ $club->name }}"></label>
        <div class="form-grid">
            <label>Kategori<select name="category" required>@foreach(config('brudel.club_categories') as $value => $label)<option value="{{ $value }}" @selected($club->category === $value)>{{ $label }}</option>@endforeach</select></label>
            <label>Jurusan (khusus HIMAJU)<select name="department_id"><option value="">—</option>@foreach($departments as $department)<option value="{{ $department->id }}" @selected($club->department_id === $department->id)>{{ $department->name }}</option>@endforeach</select></label>
        </div>@endif
        <div class="form-grid">
            <label>Bidang<select name="field" @required($club->isUkm())><option value="">— Tanpa bidang</option>@foreach($master->fields($club->field) as $field)<option @selected($club->field === $field)>{{ $field }}</option>@endforeach</select></label>
            <label>Pembina<input name="mentor" required maxlength="120" value="{{ $club->mentor }}"></label>
            <label class="span-2">Kontak resmi<input name="contact" required maxlength="190" value="{{ $club->contact }}"></label>
        </div>
        <label>Deskripsi<textarea name="description" required maxlength="3000" rows="3">{{ $club->description }}</textarea></label>
        <label>Kegiatan rutin<textarea name="activities" maxlength="3000" rows="3" placeholder="Jadwal latihan, kegiatan tahunan, dsb.">{{ $club->activities }}</textarea></label>
        @if(auth()->user()->isStudentAffairs())<label class="checkbox-label"><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" @checked($club->active)><span>Aktif menerima anggota</span></label>@endif
        <button class="button primary">Simpan profil</button>
    </form>
</section>

<section class="panel">
    <h2>Banner kartu Jelajahi UKM</h2>
    <p class="muted">Ukuran 1200 × 480 px (rasio 5:2), JPG/PNG/WebP, maksimal 3 MB. Gambar dengan ukuran lain dipotong otomatis di tengah. Hindari teks penting di pojok kiri atas karena tertutup label kategori.</p>
    @if($club->banner_path)<img class="banner-preview" src="/banner/{{ $club->id }}?v={{ $club->updated_at?->timestamp }}" alt="Banner saat ini">@else<div class="card-banner banner-preview {{ $club->tone() }}"><x-icon :name="$club->symbol"/><span class="badge">Belum ada banner</span></div>@endif
    <form class="inline-form" method="post" action="/kelola/ukm/{{ $club->id }}/banner" enctype="multipart/form-data">@csrf
        <input type="file" name="banner" accept="image/jpeg,image/png,image/webp" required aria-label="Berkas banner">
        <button class="button primary">Unggah banner</button>
    </form>
    <div class="inline-actions"><a class="text-link" href="/templates/banner-ukm-1200x480.png" download>Unduh template ukuran (PNG) ↓</a>@if($club->banner_path)<form method="post" action="/kelola/ukm/{{ $club->id }}/banner/hapus">@csrf<button class="button outline small">Hapus banner</button></form>@endif</div>
</section>

<section class="panel">
    <h2>Kepengurusan per periode</h2>
    <p class="muted">{{ $canLead ? 'Anda dapat menambah atau mengakhiri pengurus. Pengurus dipilih dari anggota aktif.' : 'Perubahan pengurus dilakukan Ketua, Wakil Ketua, Sekretaris, atau kemahasiswaan.' }}</p>
    @forelse($officers as $officer)
        <div class="list-row"><div><strong>{{ $officer->user->name }} · {{ $officer->position }}</strong><small>{{ $officer->period ?? 'Periode tidak dicatat' }} · {{ \App\Services\Wita::show($officer->starts_at, 'd M Y') }} – {{ \App\Services\Wita::show($officer->ends_at, 'd M Y') }} · {{ $officer->isCurrent() ? 'Berlaku' : 'Tidak berlaku' }}</small></div>
        @if($canLead && $officer->ends_at->isFuture() && $officer->user_id !== auth()->id())<form class="inline-form" method="post" action="/kelola/penugasan/{{ $officer->id }}/akhiri">@csrf<input name="reason" required minlength="5" placeholder="Alasan"><button class="button outline small">Akhiri</button></form>@endif</div>
    @empty<p class="muted">Belum ada pengurus tercatat.</p>@endforelse
    @if($canLead)
    <form class="form-grid" method="post" action="/kelola/penugasan">@csrf
        <input type="hidden" name="scope_id" value="{{ $club->id }}"><input type="hidden" name="role" value="pengurus">
        <label class="span-2">Anggota aktif<select name="user" required><option value="">Pilih anggota…</option>@foreach($candidates as $candidate)<option value="{{ $candidate->user->nim ?? $candidate->user->email }}">{{ $candidate->user->name }} · {{ $candidate->user->nim }}{{ $candidate->division ? ' · '.$candidate->division : '' }}</option>@endforeach</select></label>
        <label>Jabatan pengurus<select name="position">@foreach($master->officerPositions() as $position)<option @selected($position === 'Anggota pengurus')>{{ $position }}</option>@endforeach</select></label>
        <label>Periode<input name="period" maxlength="30" placeholder="2026/2027"></label>
        <label>Mulai (WITA)<input type="datetime-local" name="starts_at" required value="{{ now()->timezone('Asia/Makassar')->format('Y-m-d\TH:i') }}"></label>
        <label>Berakhir (WITA)<input type="datetime-local" name="ends_at" required value="{{ now()->timezone('Asia/Makassar')->addYear()->format('Y-m-d\TH:i') }}"></label>
        <div class="span-2"><button class="button outline">Tambah pengurus</button>@if($candidates->isEmpty()) <span class="muted">Belum ada anggota aktif untuk dipilih.</span>@endif</div>
    </form>
    @endif
    @if($canAssignCoach)
    <details class="collapsible"><summary><strong>+ Tambah pelatih/pembina</strong></summary>
    <form class="form-grid" method="post" action="/kelola/penugasan">@csrf
        <input type="hidden" name="scope_id" value="{{ $club->id }}"><input type="hidden" name="role" value="pembina">
        <label class="span-2">Email pelatih/pembina<input name="user" required maxlength="190"></label>
        <label>Mulai (WITA)<input type="datetime-local" name="starts_at" required value="{{ now()->timezone('Asia/Makassar')->format('Y-m-d\TH:i') }}"></label>
        <label>Berakhir (WITA)<input type="datetime-local" name="ends_at" required value="{{ now()->timezone('Asia/Makassar')->addYear()->format('Y-m-d\TH:i') }}"></label>
        <div class="span-2"><button class="button outline">Tambah pelatih/pembina</button></div>
    </form>
    </details>
    @endif
</section>
</div>

@if($club->category === 'himaju')
<div class="alert success">Anggota HIMAJU otomatis: seluruh mahasiswa aktif Jurusan {{ $club->department?->name }} menjadi anggota tanpa rekrutmen. Pengurus dapat mengatur divisi dan status Nonaktif/Alumni.</div>
@elseif(! $club->isUkm())
<section class="panel">
    <h2>Tetapkan anggota terpilih</h2>
    <p class="muted">Anggota {{ $club->name }} berasal dari hasil pemilihan, bukan rekrutmen terbuka. Setiap penetapan dicatat beserta dasarnya.</p>
    <form class="form-grid" method="post" action="/kelola/ukm/{{ $club->id }}/anggota">@csrf
        <label>NIM/email mahasiswa<input name="user" required maxlength="190"></label>
        <label>Jabatan/komisi<input name="division" maxlength="80" placeholder="Mis. Menteri Kesehatan, Komisi A"></label>
        <label class="span-2">Dasar penetapan<input name="basis" required minlength="5" maxlength="500" placeholder="Mis. Hasil Pemilihan Raya Mahasiswa 2026"></label>
        <div class="span-2"><button class="button primary">Tetapkan sebagai anggota</button></div>
    </form>
</section>
@else
<section class="section-block">
<div class="section-heading"><h2>Periode rekrutmen</h2></div>
@foreach($club->recruitments as $r)
<details class="panel collapsible" @if($loop->first) open @endif>
    <summary><strong>{{ $r->name }}</strong> <span class="badge {{ $r->isOpen() ? 'status-accepted' : 'status-draft' }}">{{ $r->isOpen() ? 'Dibuka' : 'Di luar periode' }}</span> <span class="muted">{{ $r->accepted_count }}/{{ $r->quota }} diterima · {{ $r->pending_count }} menunggu</span></summary>
    @include('clubs._recruitment-form', ['club' => $club, 'recruitment' => $r])
</details>
@endforeach
<details class="panel collapsible" @if($club->recruitments->isEmpty()) open @endif><summary><strong>+ Buka periode rekrutmen baru</strong></summary>@include('clubs._recruitment-form', ['club' => $club, 'recruitment' => null])</details>
</section>
@endif

<section class="section-block">
<div class="section-heading"><h2>Anggota <span class="count-pill">{{ $members->count() }}</span></h2>
<form method="get" class="inline-form"><select name="status" onchange="this.form.submit()"><option value="">Semua status</option>@foreach(config('brudel.membership_statuses') as $key => $label)<option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>@endforeach</select></form></div>
<div class="panel table-panel"><div class="table-wrap"><table><thead><tr><th>Anggota</th><th>Bergabung</th><th>Status & divisi</th></tr></thead><tbody>
@forelse($members as $m)
<tr><td><strong>{{ $m->user->name }}</strong><small>{{ $m->user->nim }} · {{ $m->user->program?->name }}</small><x-history :items="$m->histories"/></td><td>{{ \App\Services\Wita::show($m->joined_at, 'd M Y') }}@if($m->left_at)<small>Berakhir {{ \App\Services\Wita::show($m->left_at, 'd M Y') }}</small>@endif</td>
<td><form class="inline-form" method="post" action="/kelola/anggota/{{ $m->id }}">@csrf
    <select name="status">@foreach(config('brudel.membership_statuses') as $key => $label)@continue($club->category === 'himaju' && $key === 'left' && $m->status !== 'left')<option value="{{ $key }}" @selected($m->status === $key)>{{ $label }}</option>@endforeach</select>
    <input name="division" value="{{ $m->division }}" maxlength="80" placeholder="Divisi">
    <input name="reason" maxlength="500" placeholder="Alasan bila status berubah">
    <button class="button outline small">Simpan</button></form></td></tr>
@empty<tr><td colspan="3" class="empty">Belum ada anggota.</td></tr>@endforelse
</tbody></table></div></div>
</section>
</x-layout>
