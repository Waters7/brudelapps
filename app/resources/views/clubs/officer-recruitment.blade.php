<x-layout :title="$recruitment->title">
<a class="back-link" href="/kelola/ukm/{{ $club->id }}/rekrutmen-pengurus">← Rekrutmen pengurus</a>
<div class="page-heading"><div><p class="eyebrow">{{ strtoupper($club->name) }} · PERIODE {{ strtoupper($recruitment->period) }}</p><h1>{{ $recruitment->title }}</h1><p>Pendaftaran {{ \App\Services\Wita::show($recruitment->opens_at, 'd M Y H:i') }} – {{ \App\Services\Wita::show($recruitment->closes_at, 'd M Y H:i') }} WITA. Masa jabatan {{ \App\Services\Wita::show($recruitment->term_starts_at, 'd M Y') }} – {{ \App\Services\Wita::show($recruitment->term_ends_at, 'd M Y') }}.</p></div></div>

<div class="detail-columns">
<section class="panel"><h2>Kriteria</h2>
    <ul class="plain-list">@foreach($recruitment->criteria as $criterion)<li><strong>{{ $loop->iteration }}.</strong> {{ $criterion }}</li>@endforeach</ul>
</section>
<section class="panel"><h2>Jabatan dibuka</h2>
    <div class="inline-actions">@foreach($recruitment->positions as $position)<span class="chip">{{ $position }}</span>@endforeach</div>
    @if($recruitment->description)<p class="prewrap">{{ $recruitment->description }}</p>@endif
    <a class="text-link" href="/ukm/{{ $club->id }}">Lihat di profil organisasi →</a>
</section>
</div>

<section class="section-block"><h2>Kandidat <span class="count-pill">{{ $candidates->count() }}</span></h2>
@forelse($candidates as $candidate)
<article class="panel" id="candidate-{{ $candidate->id }}">
    <div class="section-heading"><div><h3>{{ $candidate->user->name }}</h3><small class="muted">{{ $candidate->user->nim }} · {{ $candidate->user->program?->name ?? '—' }} · mengajukan diri sebagai {{ $candidate->position }}</small></div><x-status :value="$candidate->status"/></div>
    <p class="prewrap">{{ $candidate->statement }}</p>
    @if($candidate->status === 'submitted' && $canManage && $candidate->user_id !== auth()->id())
    <form method="post" action="/kelola/kandidat-pengurus/{{ $candidate->id }}">@csrf
        <fieldset><legend>Kriteria yang terpenuhi</legend>
            @foreach($recruitment->criteria as $index => $criterion)<label class="checkbox-label"><input type="checkbox" name="met[]" value="{{ $index }}"><span>{{ $criterion }}</span></label>@endforeach
        </fieldset>
        <label>Catatan (wajib bila ditolak)<textarea name="note" rows="2" maxlength="1000"></textarea></label>
        <div class="inline-actions"><button class="button primary" name="decision" value="accepted">Terima</button><button class="button outline danger" name="decision" value="rejected">Tolak</button></div>
    </form>
    @elseif($candidate->status !== 'submitted')
    <p class="muted">{{ count($candidate->met_criteria ?? []) }}/{{ count($recruitment->criteria) }} kriteria terpenuhi · diputuskan {{ $candidate->reviewer?->name }} · {{ \App\Services\Wita::show($candidate->reviewed_at, 'd M Y H:i') }} WITA</p>
    @if($candidate->review_note)<p class="prewrap">{{ $candidate->review_note }}</p>@endif
    @endif
</article>
@empty<div class="panel empty"><p>Belum ada mahasiswa yang mengajukan diri.</p></div>@endforelse
</section>

@if($canManage)
<details class="panel collapsible"><summary><strong>Ubah rekrutmen</strong></summary>
    @include('clubs._officer-recruitment-form', ['action' => '/kelola/rekrutmen-pengurus/'.$recruitment->id])
</details>
@endif
</x-layout>
