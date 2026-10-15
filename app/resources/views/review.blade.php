<x-layout title="Tinjau Pengajuan">
<div class="page-heading"><div><p class="eyebrow">RUANG PENGELOLA</p><h1>Tinjau langkah berikutnya.</h1><p>Hanya pengajuan dalam lingkup penugasan aktif Anda yang ditampilkan.</p></div><div class="inline-actions"><a class="button outline" href="/ekspor/ukm">CSV UKM ↓</a><a class="button outline" href="/ekspor/lomba">CSV Lomba ↓</a></div></div>
@foreach(['ukm'=>$applications,'lomba'=>$registrations] as $type=>$rows)
<section class="section-block"><h2>{{ $type==='ukm'?'Pendaftaran UKM':'Pendaftaran lomba' }} · {{ $rows->count() }}</h2>
@forelse($rows as $r)
<article class="panel">
    <div class="section-heading"><div><span class="eyebrow">{{ $type==='ukm'?$r->recruitment->club->name.' · '.$r->recruitment->name:$r->competition->name }}</span><h2>{{ $r->user->name }}{{ $r->team_name?' · '.$r->team_name:'' }}</h2></div>
        <div class="inline-actions"><x-status :value="$r->status"/>@if($type==='lomba' && $r->payment_status !== 'not_required')<x-status :value="'payment:'.$r->payment_status"/>@endif</div></div>
    <p>{{ $r->motivation }}</p>
    @if($type==='ukm')
        <p class="muted">{{ $r->user->program?->name }} · diajukan {{ \App\Services\Wita::show($r->created_at, 'd M Y H:i') }} WITA</p>
        @if($r->document_path)<a class="text-link" href="/ukm-pendaftaran/{{ $r->id }}/document">Unduh dokumen privat ↓</a>@endif
    @else
        <p>Peserta: {{ $r->participants->map(fn($p)=>$p->user->name.' ('.($p->user->program?->name ?? '—').')')->join(', ') }}</p>
        @if($r->approvals->isNotEmpty())<p class="muted">Disetujui prodi: {{ $r->approvals->map(fn ($a) => $a->program->name)->join(', ') }}</p>@endif
        @if($r->document_path)<a class="text-link" href="/pendaftaran/{{ $r->id }}/document">Unduh dokumen privat ↓</a>@endif
        @if(!in_array($r->payment_status, ['not_required', 'paid']))<div class="alert warning">Pembayaran belum diverifikasi. Pendaftaran belum dapat diterima.</div>@endif
    @endif
    <form action="/tinjauan/{{ $type }}/{{ $r->id }}" method="post" class="review-form">@csrf
        <label>Keputusan<select name="decision" required><option value="">Pilih keputusan</option><option value="accepted">Terima</option><option value="revision">Minta revisi</option>@if($type==='ukm')<option value="waitlisted">Daftar tunggu</option>@endif<option value="rejected">Tolak</option></select></label>
        <label>Alasan / catatan<input name="reason" maxlength="2000" placeholder="Wajib untuk revisi, daftar tunggu, atau penolakan"></label>
        <button class="button primary">Simpan keputusan</button>
    </form>
</article>
@empty<div class="panel empty">Tidak ada pengajuan yang menunggu tinjauan.</div>@endforelse
</section>
@endforeach
</x-layout>
