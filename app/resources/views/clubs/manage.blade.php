<x-layout :title="'Kelola '.$club->name">
<a class="back-link" href="/kelola/ukm">← Kelola UKM</a>
<div class="page-heading"><div><p class="eyebrow">DASHBOARD UKM · {{ strtoupper($club->field) }}</p><h1>{{ $club->name }}</h1><p>Keanggotaan dipisahkan dari jabatan pengurus. Pergantian ketua tidak menghapus riwayat anggota.</p></div>
<div class="inline-actions"><a class="button outline" href="/ukm/{{ $club->id }}">Lihat profil publik</a><a class="button outline" href="/kelola/ukm/{{ $club->id }}/ekspor">CSV anggota ↓</a><a class="button outline" href="/tinjauan">Tinjau pendaftar</a></div></div>

<div class="kpi-grid">
    @foreach(config('brudel.membership_statuses') as $key => $label)<div class="kpi"><span>{{ $label }}</span><strong>{{ $counts[$key] ?? 0 }}</strong></div>@endforeach
    <div class="kpi"><span>Menunggu tinjauan</span><strong>{{ $club->recruitments->sum('pending_count') }}</strong></div>
</div>

<div class="detail-columns">
<section class="panel">
    <h2>Profil UKM</h2>
    <form method="post" action="/kelola/ukm/{{ $club->id }}">@csrf
        @if(auth()->user()->isStudentAffairs())<label>Nama UKM<input name="name" required maxlength="120" value="{{ $club->name }}"></label>@endif
        <div class="form-grid">
            <label>Bidang<select name="field" required>@foreach(config('brudel.fields') as $field)<option @selected($club->field === $field)>{{ $field }}</option>@endforeach</select></label>
            <label>Pembina<input name="mentor" required maxlength="120" value="{{ $club->mentor }}"></label>
            <label class="span-2">Kontak resmi<input name="contact" required maxlength="190" value="{{ $club->contact }}"></label>
        </div>
        <label>Deskripsi<textarea name="description" required maxlength="3000" rows="3">{{ $club->description }}</textarea></label>
        <label>Kegiatan rutin<textarea name="activities" maxlength="3000" rows="3" placeholder="Jadwal latihan, kegiatan tahunan, dsb.">{{ $club->activities }}</textarea></label>
        @if(auth()->user()->isStudentAffairs())<label class="checkbox-label"><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" @checked($club->active)><span>UKM aktif menerima anggota</span></label>@endif
        <button class="button primary">Simpan profil</button>
    </form>
</section>

<section class="panel">
    <h2>Kepengurusan per periode</h2>
    <p class="muted">{{ $canLead ? 'Sebagai ketua/kemahasiswaan Anda dapat menambah atau mengakhiri pengurus.' : 'Perubahan pengurus dilakukan ketua UKM atau kemahasiswaan.' }}</p>
    @forelse($officers as $officer)
        <div class="list-row"><div><strong>{{ $officer->user->name }} · {{ $officer->position }}</strong><small>{{ $officer->period ?? 'Periode tidak dicatat' }} · {{ \App\Services\Wita::show($officer->starts_at, 'd M Y') }} – {{ \App\Services\Wita::show($officer->ends_at, 'd M Y') }} · {{ $officer->isCurrent() ? 'Berlaku' : 'Tidak berlaku' }}</small></div>
        @if($canLead && $officer->ends_at->isFuture() && $officer->user_id !== auth()->id())<form class="inline-form" method="post" action="/kelola/penugasan/{{ $officer->id }}/akhiri">@csrf<input name="reason" required minlength="5" placeholder="Alasan"><button class="button outline small">Akhiri</button></form>@endif</div>
    @empty<p class="muted">Belum ada pengurus tercatat.</p>@endforelse
    @if($canLead)
    <form class="form-grid" method="post" action="/kelola/penugasan">@csrf
        <input type="hidden" name="scope_id" value="{{ $club->id }}">
        <label>Email/NIM<input name="user" required maxlength="190"></label>
        <label>Peran<select name="role"><option value="pengurus">Pengurus</option><option value="pembina">Pelatih/pembina</option></select></label>
        <label>Jabatan pengurus<select name="position"><option>Ketua</option><option>Wakil Ketua</option><option>Sekretaris</option><option>Bendahara</option><option>Koordinator Divisi</option><option selected>Anggota pengurus</option></select></label>
        <label>Periode<input name="period" maxlength="30" placeholder="2026/2027"></label>
        <label>Mulai (WITA)<input type="datetime-local" name="starts_at" required value="{{ now()->timezone('Asia/Makassar')->format('Y-m-d\TH:i') }}"></label>
        <label>Berakhir (WITA)<input type="datetime-local" name="ends_at" required value="{{ now()->timezone('Asia/Makassar')->addYear()->format('Y-m-d\TH:i') }}"></label>
        <div class="span-2"><button class="button outline">Tambah pengurus/pembina</button></div>
    </form>
    @endif
</section>
</div>

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

<section class="section-block">
<div class="section-heading"><h2>Anggota <span class="count-pill">{{ $members->count() }}</span></h2>
<form method="get" class="inline-form"><select name="status" onchange="this.form.submit()"><option value="">Semua status</option>@foreach(config('brudel.membership_statuses') as $key => $label)<option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>@endforeach</select></form></div>
<div class="panel table-panel"><div class="table-wrap"><table><thead><tr><th>Anggota</th><th>Bergabung</th><th>Status & divisi</th></tr></thead><tbody>
@forelse($members as $m)
<tr><td><strong>{{ $m->user->name }}</strong><small>{{ $m->user->nim }} · {{ $m->user->program?->name }}</small><x-history :items="$m->histories"/></td><td>{{ \App\Services\Wita::show($m->joined_at, 'd M Y') }}@if($m->left_at)<small>Berakhir {{ \App\Services\Wita::show($m->left_at, 'd M Y') }}</small>@endif</td>
<td><form class="inline-form" method="post" action="/kelola/anggota/{{ $m->id }}">@csrf
    <select name="status">@foreach(config('brudel.membership_statuses') as $key => $label)<option value="{{ $key }}" @selected($m->status === $key)>{{ $label }}</option>@endforeach</select>
    <input name="division" value="{{ $m->division }}" maxlength="80" placeholder="Divisi">
    <input name="reason" maxlength="500" placeholder="Alasan bila status berubah">
    <button class="button outline small">Simpan</button></form></td></tr>
@empty<tr><td colspan="3" class="empty">Belum ada anggota.</td></tr>@endforelse
</tbody></table></div></div>
</section>
</x-layout>
