<x-layout title="Verifikasi Pembayaran">
<div class="page-heading"><div><p class="eyebrow">PEMBAYARAN MANUAL</p><h1>Verifikasi bukti transfer.</h1><p>Cocokkan nominal dan rekening tujuan dengan mutasi rekening resmi. Petugas tidak dapat memverifikasi pembayarannya sendiri.</p></div><a class="button outline" href="/laporan/ekspor/pembayaran">CSV pembayaran ↓</a></div>
<section class="section-block"><h2>Menunggu verifikasi · {{ $pending->count() }}</h2>
@forelse($pending as $proof)
@php($own = $proof->uploaded_by === auth()->id() || $proof->registration->participants->contains('user_id', auth()->id()))
<article class="panel">
    <div class="section-heading"><div><span class="eyebrow">{{ $proof->registration->competition->name }}</span><h2>{{ $proof->registration->displayName() }}</h2></div><strong>Rp {{ number_format($proof->amount, 0, ',', '.') }}</strong></div>
    <p>Tagihan Rp {{ number_format($proof->registration->fee_snapshot, 0, ',', '.') }} · transfer {{ $proof->transferred_on->format('d/m/Y') }} ke {{ $proof->account->bank_name }} {{ $proof->account->account_number }} · diunggah {{ $proof->uploader->name }} {{ \App\Services\Wita::show($proof->created_at, 'd M H:i') }} WITA</p>
    <a class="text-link" href="/pembayaran/{{ $proof->id }}/bukti">Unduh bukti transfer (privat) ↓</a>
    @if($own)<div class="alert warning">Ini pembayaran Anda/tim Anda. Verifikasi harus dilakukan petugas lain.</div>@else
    <form class="review-form" method="post" action="/pembayaran/{{ $proof->id }}">@csrf
        <label>Keputusan<select name="decision" required><option value="">Pilih</option><option value="accepted">Terima</option><option value="rejected">Tolak</option></select></label>
        <label>Alasan<input name="reason" maxlength="1000" placeholder="Wajib bila ditolak, mis. nominal tidak sesuai"></label>
        <button class="button primary">Simpan verifikasi</button>
    </form>@endif
</article>
@empty<div class="panel empty">Tidak ada bukti pembayaran yang menunggu.</div>@endforelse
</section>
<section class="panel"><h2>Riwayat verifikasi</h2>
<div class="table-wrap"><table class="compact"><thead><tr><th>Lomba</th><th>Nominal</th><th>Status</th><th>Petugas</th><th>Waktu</th></tr></thead><tbody>
@forelse($recent as $proof)<tr><td>{{ $proof->registration->competition->name }}</td><td>Rp {{ number_format($proof->amount, 0, ',', '.') }}</td><td><x-status :value="$proof->status"/>@if($proof->reason)<small>{{ $proof->reason }}</small>@endif</td><td>{{ $proof->verifier?->name }}</td><td>{{ \App\Services\Wita::show($proof->verified_at, 'd M H:i') }}</td></tr>@empty<tr><td colspan="5" class="empty">Belum ada.</td></tr>@endforelse
</tbody></table></div></section>
</x-layout>
