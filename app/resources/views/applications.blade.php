<x-layout title="Pendaftaran Saya">
<div class="page-heading"><div><p class="eyebrow">SETIAP LANGKAH TERCATAT</p><h1>Perjalanan pendaftaranmu.</h1><p>Kelola draf, konfirmasi undangan, pembayaran, dan pantau hasil peninjauan.</p></div><a href="/lomba" class="button primary">Temukan lomba <x-icon name="arrow"/></a></div>

@foreach($invitations as $p)
<section class="panel invitation"><div><span class="badge status-invited">Undangan tim</span><h2>{{ $p->registration->team_name }}</h2><p>{{ $p->registration->user->name }} mengundangmu untuk {{ $p->registration->competition->name }}.</p></div>
<form action="/undangan/{{ $p->id }}" method="post" class="inline-actions">@csrf<button name="decision" value="accept" class="button primary">Terima undangan</button><button name="decision" value="decline" class="button outline">Tolak</button></form></section>
@endforeach

<section class="section-block"><div class="section-heading"><h2>Pendaftaran UKM <span class="count-pill">{{ $applications->count() }}</span></h2></div>
<div class="panel table-panel"><div class="table-wrap"><table><thead><tr><th>UKM & periode</th><th>Diajukan</th><th>Status</th><th>Tindak lanjut</th></tr></thead><tbody>
@forelse($applications as $a)
<tr><td><strong>{{ $a->recruitment->club->name }}</strong><small>{{ $a->recruitment->name }}</small></td><td>{{ \App\Services\Wita::show($a->created_at, 'd M Y') }}</td><td><x-status :value="$a->status"/></td>
<td>@if($a->reason)<p>{{ $a->reason }}</p>@endif
@if($a->status==='revision')<a class="text-link" href="/ukm/{{ $a->recruitment->club_id }}">Kirim perbaikan →</a>@elseif($a->status==='accepted')<a class="text-link" href="/ukm-saya">Lihat keanggotaan →</a>@else<span class="muted">Pantau status di sini</span>@endif
@if($a->document_path)<br><a class="text-link" href="/ukm-pendaftaran/{{ $a->id }}/document">Dokumen saya ↓</a>@endif
<x-history :items="$a->histories"/></td></tr>
@empty<tr><td colspan="4" class="empty">Belum ada pendaftaran UKM. <a href="/ukm" class="text-link">Temukan komunitasmu →</a></td></tr>@endforelse
</tbody></table></div></div></section>

<section class="section-block"><div class="section-heading"><h2>Pendaftaran lomba <span class="count-pill">{{ $registrations->count() }}</span></h2></div>
@forelse($registrations as $r)
@php($leader = $r->user_id === auth()->id())
@php($competition = $r->competition)
<article class="panel registration-card" id="registration-{{ $r->id }}">
    <div class="section-heading"><div><span class="eyebrow">{{ $competition->field }} · {{ $competition->format==='team'?'Tim':'Individu' }} · {{ $competition->event->name }}</span><h2>{{ $competition->name }}{{ $r->team_name?' — '.$r->team_name:'' }}</h2></div>
        <div class="inline-actions"><x-status :value="$r->status"/>@if($r->payment_status !== 'not_required')<x-status :value="'payment:'.$r->payment_status"/>@endif</div></div>
    @if($r->reason)<div class="alert warning">Catatan: {{ $r->reason }}</div>@endif
    <p>{{ $r->motivation }}</p>
    <div class="participant-list">@foreach($r->participants as $p)
        <div class="participant"><span class="avatar small">{{ mb_substr($p->user->name,0,1) }}</span><span>{{ $p->user->name }} @if($p->user_id===$r->user_id)<small>Ketua / pendaftar</small>@endif</span><x-status :value="$p->status"/>
        @if($leader && $r->isEditable() && $p->user_id !== $r->user_id)<form method="post" action="/pendaftaran/{{ $r->id }}/anggota/{{ $p->id }}/hapus">@csrf<button class="icon-button" aria-label="Hapus {{ $p->user->name }} dari tim" title="Hapus dari tim">✕</button></form>@endif</div>
    @endforeach</div>

    @if($r->approvals->isNotEmpty())
    <div class="approval-list"><strong>Persetujuan prodi:</strong> @foreach($r->approvals as $approval)<span class="chip">{{ $approval->program->name }} · <x-status :value="$approval->status"/></span>@endforeach</div>
    @endif

    @if($leader && $r->isEditable())
        <div class="registration-actions">
            @if($competition->format==='team')<form action="/pendaftaran/{{ $r->id }}/invite" method="post">@csrf<label>Undang anggota dengan NIM<div class="input-action"><input name="nim" placeholder="NIM mahasiswa aktif" required maxlength="30"><button class="button outline">Undang</button></div></label><p class="form-note">Butuh {{ $competition->min_members }}–{{ $competition->max_members }} anggota. Anggota mengonfirmasi lewat akun sendiri.</p></form>@endif
            <form action="/pendaftaran/{{ $r->id }}/document" method="post" enctype="multipart/form-data">@csrf<label>Dokumen PDF {{ $competition->requires_document?'(wajib)':'(opsional)' }}<input type="file" name="document" accept="application/pdf,.pdf" required></label><button class="button outline">Unggah PDF</button><p class="form-note">Maksimal 5 MB. Dokumen tersimpan privat.</p></form>
        </div>
        @php($members = $r->participants->count())
        @php($fee = $competition->fee * ($competition->fee_basis === 'participant' ? $members : 1))
        <div class="submit-bar"><span>Biaya pendaftaran: <strong>{{ $fee ? 'Rp '.number_format($fee, 0, ',', '.') : 'Gratis' }}</strong>@if($fee && $competition->fee_basis === 'participant') <small class="muted">({{ $members }} peserta × Rp {{ number_format($competition->fee, 0, ',', '.') }})</small>@endif @if($competition->requires_program_approval)<br><small class="muted">Setelah dikirim, pendaftaran menunggu persetujuan prodi.</small>@endif</span>
            <div class="inline-actions"><a href="/lomba/{{ $r->competition_id }}" class="text-link">Ubah draf</a>
                @if($r->status === 'draft')<form method="post" action="/pendaftaran/{{ $r->id }}/batal" onsubmit="return confirm('Batalkan draf ini? Undangan anggota juga dilepas.')">@csrf<button class="button outline">Batalkan draf</button></form>@endif
                <form method="post" action="/pendaftaran/{{ $r->id }}/submit">@csrf<button class="button primary">Kirim pendaftaran <x-icon name="arrow"/></button></form></div></div>
    @elseif($r->isEditable())
        <p class="form-note">Ketua tim mengelola susunan dan pengiriman pendaftaran.</p>
    @else
        <p class="form-note">Pendaftaran terkunci. Revisi hanya dapat dilakukan jika diminta pengelola atau prodi.</p>
    @endif

    @if($r->payment_status !== 'not_required')
    <div class="payment-box">
        <h3>Pembayaran · tagihan Rp {{ number_format($r->fee_snapshot, 0, ',', '.') }}</h3>
        @foreach($r->payments as $proof)
            <p class="muted">Bukti #{{ $proof->id }} · Rp {{ number_format($proof->amount, 0, ',', '.') }} · {{ $proof->transferred_on->format('d/m/Y') }} · <x-status :value="$proof->status"/> @if($proof->reason)· {{ $proof->reason }}@endif <a class="text-link" href="/pembayaran/{{ $proof->id }}/bukti">Unduh ↓</a></p>
        @endforeach
        @if($leader && in_array($r->payment_status, ['unpaid', 'rejected']) && in_array($r->status, \App\Models\Registration::HOLDING))
            @if($accounts->isEmpty())<div class="alert warning">Rekening resmi belum tersedia. Hubungi kemahasiswaan.</div>@else
            <form class="form-grid" method="post" action="/pendaftaran/{{ $r->id }}/pembayaran" enctype="multipart/form-data">@csrf
                <label>Rekening tujuan resmi<select name="payment_account_id" required>@foreach($accounts as $account)<option value="{{ $account->id }}">{{ $account->bank_name }} · {{ $account->account_number }} · a.n. {{ $account->account_holder }}</option>@endforeach</select></label>
                <label>Nominal transfer (Rp)<input type="number" name="amount" required min="1" value="{{ $r->fee_snapshot }}"></label>
                <label>Tanggal transfer<input type="date" name="transferred_on" required max="{{ now()->timezone('Asia/Makassar')->toDateString() }}"></label>
                <label>Bukti transfer (PDF ≤ 5 MB)<input type="file" name="proof" accept="application/pdf,.pdf" required></label>
                <div class="span-2"><button class="button primary">Kirim bukti pembayaran</button><p class="form-note">{{ $refundPolicy }}</p></div>
            </form>@endif
        @elseif($r->payment_status === 'pending')
            <p class="form-note">Bukti sedang diverifikasi petugas. Petugas tidak dapat memverifikasi pembayarannya sendiri.</p>
        @endif
    </div>
    @endif

    <div class="inline-actions">@if($r->document_path)<a href="/pendaftaran/{{ $r->id }}/document" class="text-link">Unduh dokumen ↓</a>@endif @if($competition->live_score)<a class="text-link" href="/live/{{ $competition->id }}">Live score →</a>@endif</div>
    <x-history :items="$r->histories"/>
</article>
@empty<div class="panel empty"><x-icon name="trophy"/><h3>Masih banyak kesempatan menanti.</h3><p>Mulai dengan memilih lomba yang sesuai minatmu.</p><a class="button outline" href="/lomba">Jelajahi lomba →</a></div>@endforelse
</section>
</x-layout>
