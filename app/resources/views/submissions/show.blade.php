<x-layout :title="$submission->title">
<a class="back-link" href="/proposal">← Proposal & LPJ</a>
<div class="page-heading"><div><p class="eyebrow">{{ strtoupper($submission->typeLabel()) }} · {{ strtoupper($submission->club->name) }}</p><h1>{{ $submission->title }}</h1><p>Versi {{ $submission->version }} · diajukan {{ $submission->submitter->name }} · {{ \App\Services\Wita::show($submission->submitted_at, 'd M Y H:i') }} WITA</p></div><x-status :value="$submission->status"/></div>

@if($submission->review_note && $submission->status !== 'submitted')
<div class="alert {{ $submission->status === 'rejected' ? 'error' : 'warning' }}"><strong>Catatan peninjau:</strong> {{ $submission->review_note }}</div>
@endif

<div class="detail-columns">
<section class="panel"><h2>Dokumen</h2>
    <dl>
        <dt>Ditujukan kepada</dt><dd>{{ $submission->targetLabel() }}</dd>
        @if($submission->activity)<dt>Kegiatan</dt><dd>{{ $submission->activity->title }} · {{ \App\Services\Wita::show($submission->activity->starts_at, 'd M Y') }}</dd>@endif
        @if($submission->period)<dt>Periode kepengurusan</dt><dd>{{ $submission->period }}</dd>@endif
        @if($submission->summary)<dt>Ringkasan</dt><dd class="prewrap">{{ $submission->summary }}</dd>@endif
        @if($submission->reviewer)<dt>Ditinjau oleh</dt><dd>{{ $submission->reviewer->name }} · {{ \App\Services\Wita::show($submission->reviewed_at, 'd M Y H:i') }} WITA</dd>@endif
    </dl>
    <a class="button outline" href="/proposal/{{ $submission->id }}/unduh">Unduh dokumen ↓</a>
</section>
<section class="panel"><h2>Proses validasi</h2>
    <div class="journey-card">@foreach($submission->stages() as [$stage, $detail, $finished])<div class="journey-step"><span class="step-number {{ $finished ? 'complete' : '' }}">@if($finished)<x-icon name="check"/>@else{{ $loop->iteration }}@endif</span><div><strong>{{ $stage }}</strong><small>{{ $detail }}</small></div></div>@endforeach</div>
    <x-history :items="$submission->histories" open/>
</section>
</div>

@if($canDecide)
<section class="panel"><h2>Keputusan</h2>
    <form method="post" action="/proposal/{{ $submission->id }}/tinjau">@csrf
        <label>Catatan (wajib bila perlu perbaikan atau ditolak)<textarea name="note" rows="3" maxlength="2000">{{ old('note') }}</textarea></label>
        <div class="inline-actions"><button class="button primary" name="decision" value="accepted">Terima</button><button class="button outline" name="decision" value="revision">Minta perbaikan</button><button class="button outline danger" name="decision" value="rejected">Tolak</button></div>
    </form>
</section>
@endif

@if($canResubmit)
<section class="panel"><h2>Kirim perbaikan</h2>
    <form method="post" action="/proposal/{{ $submission->id }}/perbaiki" enctype="multipart/form-data">@csrf
        <label>Dokumen perbaikan (PDF/DOCX ≤ 5 MB)<input type="file" name="document" required accept=".pdf,.docx,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document"></label>
        <label>Ringkasan (kosongkan bila tidak berubah)<textarea name="summary" rows="3" maxlength="2000">{{ old('summary') }}</textarea></label>
        <button class="button primary">Kirim perbaikan</button>
    </form>
</section>
@endif
</x-layout>
