{{-- A call for new officers of BEM, MPM or HIMAJU, and how the signed-in student stands with it. --}}
<section class="panel"><h2>Rekrutmen pengurus</h2>
    <p><strong>{{ $officerRecruitment->title }}</strong> · periode {{ $officerRecruitment->period }}. Pendaftaran ditutup {{ \App\Services\Wita::show($officerRecruitment->closes_at, 'd M Y H:i') }} WITA.</p>
    @if($officerRecruitment->description)<p class="muted prewrap">{{ $officerRecruitment->description }}</p>@endif
    <h3>Kriteria dari pengurus</h3>
    <ul class="plain-list">@foreach($officerRecruitment->criteria as $criterion)<li><strong>{{ $loop->iteration }}.</strong> {{ $criterion }}</li>@endforeach</ul>
    @if($officerCandidate && $officerCandidate->status !== 'cancelled')
    <div class="alert success">Kamu mengajukan diri sebagai {{ $officerCandidate->position }}. Status: <x-status :value="$officerCandidate->status"/></div>
    @if($officerCandidate->status === 'submitted')<form method="post" action="/kandidat-pengurus/{{ $officerCandidate->id }}/batal">@csrf<button class="text-link plain">Batalkan pengajuan</button></form>@endif
    @elseif($officerBlocked)
    <p class="muted">{{ $officerBlocked }}</p>
    @else
    <form method="post" action="/rekrutmen-pengurus/{{ $officerRecruitment->id }}/ajukan">@csrf
        <label>Jabatan<select name="position" required>@foreach($officerRecruitment->positions as $position)<option @selected(old('position') === $position)>{{ $position }}</option>@endforeach</select></label>
        <label>Pernyataan diri<textarea name="statement" required minlength="20" maxlength="2000" rows="4" placeholder="Jelaskan motivasi dan bagaimana kamu memenuhi kriteria (minimal 20 karakter).">{{ old('statement') }}</textarea></label>
        <button class="button primary full">Ajukan diri <x-icon name="arrow"/></button>
    </form>
    @endif
</section>
