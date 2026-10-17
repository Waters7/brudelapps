<x-layout title="Verifikasi Prestasi">
<div class="page-heading"><div><p class="eyebrow">KEMAHASISWAAN</p><h1>Verifikasi prestasi.</h1><p>Prestasi eksternal masuk laporan resmi setelah bukti diperiksa. Satu penghargaan tim dihitung satu kali.@if($scoped) Anda memverifikasi prestasi yang diajukan mahasiswa jurusan Anda.@endif</p></div><a class="button outline" href="/laporan/ekspor/prestasi">CSV prestasi ↓</a></div>
<section class="section-block"><h2>Menunggu verifikasi · {{ $pending->count() }}</h2>
@forelse($pending as $award)
@php($own = $award->submitted_by === auth()->id() || $award->recipients->contains('user_id', auth()->id()))
<article class="panel">
    <div class="section-heading"><div><span class="eyebrow">{{ $award->field }} · {{ $award->levelLabel() }} · {{ $award->awarded_on->format('d/m/Y') }}</span><h2>{{ $award->award }} — {{ $award->title }}</h2></div><span class="chip">{{ $award->is_team ? 'Tim' : 'Individu' }}</span></div>
    <p>Penyelenggara: {{ $award->organizer }} · diajukan {{ $award->submitter->name }}{{ $award->delegation_id ? ' (hasil delegasi)' : '' }}</p>
    <p>Penerima: {{ $award->recipients->map(fn ($r) => $r->user->name.' ('.($r->user->program?->name ?? '—').', '.$r->team_role.')')->join('; ') }}</p>
    <div class="inline-actions">@if($award->evidence_path)<a class="text-link" href="/prestasi/{{ $award->id }}/bukti">Unduh bukti ↓</a>@endif @if($award->documentation_url)<a class="text-link" href="{{ $award->documentation_url }}" target="_blank" rel="noopener noreferrer">Dokumentasi ↗</a>@endif</div>
    @if($own)<div class="alert warning">Anda terlibat dalam prestasi ini. Verifikasi oleh petugas lain.</div>@else
    <form class="review-form" method="post" action="/kelola/prestasi/{{ $award->id }}">@csrf
        <label>Keputusan<select name="decision" required><option value="">Pilih</option><option value="verified">Verifikasi</option><option value="rejected">Tolak</option></select></label>
        <label>Alasan<input name="reason" maxlength="1000" placeholder="Wajib bila ditolak"></label>
        <button class="button primary">Simpan</button>
    </form>@endif
</article>
@empty<div class="panel empty">Tidak ada prestasi yang menunggu verifikasi.</div>@endforelse
</section>
<section class="panel"><h2>Riwayat terakhir</h2>
<ul class="plain-list">@forelse($recent as $award)<li><x-status :value="$award->status"/> {{ $award->award }} · {{ $award->title }} · {{ $award->levelLabel() }} · {{ $award->recipients_count }} penerima</li>@empty<li class="muted">Belum ada.</li>@endforelse</ul>
</section>
</x-layout>
