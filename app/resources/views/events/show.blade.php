<x-layout :title="$event->name">
<a class="back-link" href="/kelola/event">← Kelola event</a>
<div class="page-heading"><div><p class="eyebrow">{{ $event->pathway === 'delegation' ? 'SELEKSI DELEGASI' : 'LOMBA INTERNAL' }} · {{ $event->club?->name ?? 'KEMAHASISWAAN' }}</p><h1>{{ $event->name }}</h1><p>{{ $event->organizer }}{{ $event->external_organizer ? ' · '.$event->external_organizer : '' }}</p></div><x-status :value="$event->status"/></div>

@if($event->reason)<div class="alert warning">Catatan keputusan: {{ $event->reason }}</div>@endif

<div class="detail-columns">
<section class="panel">
    <h2>Data event</h2>
    @if($canEdit)
    <form class="form-grid" method="post" action="/kelola/event/{{ $event->id }}">@csrf
        <label class="span-2">Nama event<input name="name" required maxlength="150" value="{{ $event->name }}"></label>
        <label>Penyelenggara<input name="organizer" required maxlength="190" value="{{ $event->organizer }}"></label>
        <label>Jalur<select name="pathway"><option value="internal" @selected($event->pathway === 'internal')>Lomba internal</option><option value="delegation" @selected($event->pathway === 'delegation')>Seleksi delegasi nasional</option></select></label>
        <label>Bidang utama<select name="field"><option value="">Campuran</option>@foreach($master->fields($event->field) as $field)<option @selected($event->field === $field)>{{ $field }}</option>@endforeach</select></label>
        <label>Penyelenggara eksternal<input name="external_organizer" maxlength="190" value="{{ $event->external_organizer }}"></label>
        <label class="span-2">Deskripsi<textarea name="description" maxlength="3000" rows="3">{{ $event->description }}</textarea></label>
        <div class="span-2"><button class="button outline">Simpan event</button></div>
    </form>
    @else
        <p class="prewrap">{{ $event->description }}</p>
    @endif
    @if($canSubmit)<form method="post" action="/kelola/event/{{ $event->id }}/ajukan">@csrf<button class="button primary">Ajukan ke kemahasiswaan <x-icon name="arrow"/></button></form>@endif
    @if($canDecide)
    <hr><h3>Keputusan kemahasiswaan</h3>
    <form class="review-form" method="post" action="/kelola/event/{{ $event->id }}/keputusan">@csrf
        <label>Keputusan<select name="decision" required><option value="">Pilih</option><option value="approved">Setujui</option><option value="revision">Minta revisi</option><option value="rejected">Tolak</option></select></label>
        <label>Alasan<input name="reason" maxlength="2000" placeholder="Wajib untuk revisi/penolakan"></label>
        <button class="button primary">Simpan keputusan</button>
    </form>
    @endif
    @if($histories->isNotEmpty())<x-history :items="$histories"/>@endif
</section>
<section class="panel">
    <h2>Penanggung jawab</h2>
    @if($responsibleVacant)
        <div class="alert warning">{{ $event->responsible ? $event->responsible->name.' tidak lagi memegang jabatan yang memenuhi syarat.' : 'Event ini belum memiliki penanggung jawab.' }} Tetapkan Ketua organisasi penyelenggara, admin jurusan, atau admin kemahasiswaan.</div>
    @else
        <div class="list-row"><div><strong>{{ $event->responsible->name }}</strong><small>Menunjuk tim penyelenggara dan membagikan akun lomba.</small></div></div>
    @endif
    @if($canSetResponsible)
    <details @if($responsibleVacant) open @endif><summary class="text-link">Ganti penanggung jawab</summary>
    <form class="form-grid" method="post" action="/kelola/event/{{ $event->id }}/penanggung-jawab">@csrf
        <label class="span-2">Penanggung jawab<select name="user_id" required><option value="">Pilih</option>@foreach($candidates as $id => $label)<option value="{{ $id }}" @selected($id === $event->responsible_id)>{{ $label }}</option>@endforeach</select></label>
        <div class="span-2"><button class="button outline">Simpan penanggung jawab</button><p class="form-note">Hanya Ketua organisasi penyelenggara, admin jurusan, atau admin kemahasiswaan.</p></div>
    </form></details>
    @endif
    <hr>
    <h2>Tim penyelenggara</h2>
    @forelse($team as $member)
    <div class="list-row"><div><strong>{{ $member->user->name }}</strong><small>{{ $member->roleLabel() }} · {{ $member->competition?->name ?? 'Semua nomor lomba' }} · s.d. {{ \App\Services\Wita::show($member->ends_at, 'd M Y') }}</small></div>
        @if($canAppoint && $member->user_id !== auth()->id())<details><summary class="text-link">Akhiri</summary><form class="inline-form" method="post" action="/kelola/penugasan/{{ $member->id }}/akhiri">@csrf<input name="reason" required minlength="5" maxlength="500" placeholder="Alasan diakhiri"><button class="button outline small">Akhiri</button></form></details>@endif
    </div>
    @empty<p class="muted">Belum ada yang ditunjuk. Penanggung jawab tidak otomatis mengelola lomba.</p>@endforelse
    @if($canAppoint)
    <form class="form-grid" method="post" action="/kelola/event/{{ $event->id }}/tim">@csrf
        <label class="span-2">Email, NIM, atau NIP<input name="user" required maxlength="190"></label>
        <label>Peran<select name="role" required>@foreach($teamRoles as $role => $label)<option value="{{ $role }}">{{ $label }}</option>@endforeach</select></label>
        <label>Nomor lomba<select name="competition_id"><option value="">Semua (khusus admin event)</option>@foreach($event->competitions as $competition)<option value="{{ $competition->id }}">{{ $competition->name }}</option>@endforeach</select></label>
        <label>Mulai (WITA)<input type="datetime-local" name="starts_at" required value="{{ now()->timezone('Asia/Makassar')->format('Y-m-d\TH:i') }}"></label>
        <label>Berakhir (WITA)<input type="datetime-local" name="ends_at" required value="{{ \App\Services\Wita::input($teamUntil) }}"></label>
        <div class="span-2"><button class="button outline">Tunjuk anggota tim</button><p class="form-note">Operator mencatat skor dan live score. Pengelola mengatur satu nomor lomba, admin event mengatur semua nomor lomba. Anda tidak dapat menunjuk diri sendiri.</p></div>
    </form>
    @elseif($event->status !== 'approved')<p class="form-note">Tim dapat ditunjuk setelah event disetujui.</p>
    @endif
</section>
</div>

<section class="section-block">
<div class="section-heading"><h2>Nomor lomba <span class="count-pill">{{ $event->competitions->count() }}</span></h2></div>
@foreach($event->competitions as $competition)
<details class="panel collapsible" id="competition-{{ $competition->id }}">
    <summary><strong>{{ $competition->name }}</strong> <span class="muted">{{ $competition->field }} · {{ $competition->format === 'team' ? 'Tim' : 'Individu' }} · {{ $competition->feeLabel() }} · {{ $competition->holding_count }}/{{ $competition->quota }} terkirim</span>
    @if(auth()->user()->managesCompetition($competition))<a class="text-link" href="/kelola/lomba/{{ $competition->id }}">Operasional lomba →</a>@endif @if($canSeeAccounts)<a class="text-link" href="/kelola/lomba/{{ $competition->id }}/akun">Akun lomba ({{ $competition->assessment_type === 'rubric' ? 2 + $competition->judge_count : 2 }}) →</a>@endif</summary>
    @if($canEdit)@include('events._competition-form', ['event' => $event, 'competition' => $competition])@else<p class="prewrap">{{ $competition->description }}</p>@endif
</details>
@endforeach
@if($canEdit)<details class="panel collapsible" @if($event->competitions->isEmpty()) open @endif><summary><strong>+ Tambah nomor lomba</strong></summary>@include('events._competition-form', ['event' => $event, 'competition' => null])</details>@endif
</section>
</x-layout>
