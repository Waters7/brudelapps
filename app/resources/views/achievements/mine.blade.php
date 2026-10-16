<x-layout title="Prestasi Saya">
<div class="page-heading"><div><p class="eyebrow">PORTOFOLIO PRESTASI</p><h1>Prestasi saya.</h1><p>Prestasi dari lomba BRUDEL dan kegiatan eksternal. Prestasi eksternal masuk laporan resmi setelah diverifikasi kemahasiswaan.</p></div>
@if($user->publication_consent && $user->public_slug)<a class="button outline" href="/mahasiswa/{{ $user->public_slug }}">Lihat profil publik ↗</a>@else<a class="button outline" href="/profil">Atur publikasi profil</a>@endif</div>

@foreach($master->allFields() as $field)
@php($items = $recipients->filter(fn ($r) => $r->award->field === $field))
@continue($items->isEmpty())
<section class="section-block"><h2>{{ $field }}</h2>
<div class="panel table-panel"><div class="table-wrap"><table><thead><tr><th>Kegiatan</th><th>Penghargaan</th><th>Tingkat</th><th>Tahun</th><th>Peran</th><th>Status</th></tr></thead><tbody>
@foreach($items as $r)
<tr><td><strong>{{ $r->award->title }}</strong><small>{{ $r->award->organizer }}{{ $r->award->team_name ? ' · Tim '.$r->award->team_name : '' }}</small></td><td>{{ $r->award->award }}</td><td>{{ $r->award->levelLabel() }}</td><td>{{ $r->award->awarded_on->format('Y') }}</td><td>{{ ucfirst($r->team_role) }}</td>
<td><x-status :value="$r->award->status"/>@if($r->award->reason)<small>{{ $r->award->reason }}</small>@endif @if($r->award->evidence_path)<a class="text-link" href="/prestasi/{{ $r->award->id }}/bukti">Bukti ↓</a>@endif</td></tr>
@endforeach
</tbody></table></div></div></section>
@endforeach
@if($recipients->isEmpty())<div class="panel empty"><x-icon name="trophy"/><h3>Belum ada prestasi tercatat.</h3><p>Prestasi lomba BRUDEL tercatat otomatis setelah hasil disahkan. Prestasi luar kampus dapat kamu ajukan di bawah.</p></div>@endif

@if($user->nim)
<section class="panel">
    <h2>Ajukan prestasi eksternal</h2>
    <p>Lengkapi bukti resmi (sertifikat/piagam/SK) dalam PDF. Klasifikasi tingkat mengikuti cakupan resmi penyelenggara. {{ \App\Models\Setting::value('regional_definition') }}</p>
    <form class="form-grid" method="post" action="/prestasi" enctype="multipart/form-data">@csrf
        <label class="span-2">Nama kegiatan/lomba<input name="title" required maxlength="190" value="{{ old('title') }}"></label>
        <label>Penyelenggara<input name="organizer" required maxlength="190" value="{{ old('organizer') }}"></label>
        <label>Penghargaan<input name="award" required maxlength="80" value="{{ old('award') }}" placeholder="Juara 2 / Medali Perak" list="award-options"></label><datalist id="award-options">@foreach($master->awards() as $award)<option value="{{ $award }}">@endforeach</datalist>
        <label>Bidang<select name="field">@foreach($master->fields() as $field)<option @selected(old('field') === $field)>{{ $field }}</option>@endforeach</select></label>
        <label>Tingkat<select name="level">@foreach(config('brudel.levels') as $key => $label)<option value="{{ $key }}" @selected((int) old('level') === $key)>{{ $label }}</option>@endforeach</select></label>
        <label>Tanggal penghargaan<input type="date" name="awarded_on" required max="{{ now()->timezone('Asia/Makassar')->toDateString() }}" value="{{ old('awarded_on') }}"></label>
        <label>Nama tim (bila tim)<input name="team_name" maxlength="80" value="{{ old('team_name') }}"></label>
        <label class="span-2">NIM anggota tim lain (pisahkan dengan koma)<input name="members" maxlength="500" value="{{ old('members') }}" placeholder="Kosongkan bila individu"></label>
        <label class="span-2">Tautan dokumentasi publik (opsional, https)<input type="url" name="documentation_url" maxlength="255" value="{{ old('documentation_url') }}"></label>
        <label class="span-2">Bukti resmi (PDF ≤ 5 MB)<input type="file" name="evidence" accept="application/pdf,.pdf" required></label>
        <div class="span-2"><button class="button primary">Kirim untuk verifikasi</button></div>
    </form>
</section>
@endif
</x-layout>
