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
    <h2>Admin event</h2>
    @forelse($event->admins as $admin)<div class="list-row"><div><strong>{{ $admin->user->name }}</strong><small>s.d. {{ \App\Services\Wita::show($admin->ends_at, 'd M Y') }}</small></div></div>@empty<p class="muted">Belum ada admin event. Ketua UKM tidak otomatis menjadi admin event.</p>@endforelse
    @if(auth()->user()->isStudentAffairs() && $event->status === 'approved')
    <form class="form-grid" method="post" action="/kelola/penugasan">@csrf
        <input type="hidden" name="role" value="admin_event"><input type="hidden" name="scope_id" value="{{ $event->id }}">
        <label class="span-2">Email/NIM admin event<input name="user" required maxlength="190"></label>
        <label>Mulai (WITA)<input type="datetime-local" name="starts_at" required value="{{ now()->timezone('Asia/Makassar')->format('Y-m-d\TH:i') }}"></label>
        <label>Berakhir (WITA)<input type="datetime-local" name="ends_at" required value="{{ now()->timezone('Asia/Makassar')->addMonths(6)->format('Y-m-d\TH:i') }}"></label>
        <div class="span-2"><button class="button outline">Tugaskan admin event</button></div>
    </form>
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
