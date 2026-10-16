<x-layout :title="$club->name">
<a class="back-link" href="/ukm">← Jelajahi UKM</a>
@if($club->banner_path)<img class="profile-banner" src="/banner/{{ $club->id }}?v={{ $club->updated_at?->timestamp }}" alt="Banner {{ $club->name }}">@endif
<div class="detail-header"><span class="large-icon {{ $club->tone() }}"><x-icon :name="$club->symbol"/></span><div><span class="eyebrow">{{ strtoupper($club->categoryLabel()) }}@if($club->field) · {{ strtoupper($club->field) }}@endif</span><h1>{{ $club->name }}</h1>@if($canEnterSpace)<a class="button primary space-enter" href="/ruang/{{ $club->id }}">Masuk ruang organisasi →</a>@endif<p>{{ $activeMembers }} anggota aktif · Poltekkes Kemenkes Manado @unless($club->active)· <strong>Sedang tidak menerima anggota</strong>@endunless</p></div></div>
<div class="detail-columns">
<div>
<section class="panel"><h2>Tumbuh bersama {{ $club->name }}</h2><p>{{ $club->description }}</p><hr>
    <dl>
        <dt>Pembina</dt><dd>{{ $club->mentor }}</dd>
        <dt>Kontak resmi</dt><dd>{{ $club->contact }}</dd>
        <dt>Pengurus aktif</dt><dd>@forelse($club->officers as $officer){{ $officer->user->name }} <span class="muted">({{ $officer->position }}{{ $officer->period ? ' · '.$officer->period : '' }})</span>@if(!$loop->last), @endif @empty <span class="muted">Belum ditetapkan</span>@endforelse</dd>
        @if($club->activities)<dt>Kegiatan</dt><dd class="prewrap">{{ $club->activities }}</dd>@endif
        <dt>Keanggotaan</dt><dd>@if($club->category === 'himaju')Wajib dan otomatis untuk seluruh mahasiswa aktif Jurusan {{ $club->department?->name }}.@elseif(! $club->isUkm())Anggota ditetapkan dari hasil pemilihan, bukan pendaftaran terbuka.@elseif(in_array($club->field, $master->singleChoiceFields(), true))Mahasiswa hanya dapat memilih satu UKM {{ strtolower($club->field) }}. Pendaftaran gratis.@else Mahasiswa bebas bergabung dengan UKM ini dan UKM lain. Pendaftaran gratis.@endif</dd>
    </dl>
</section>
@if($club->awards->isNotEmpty())
<section class="panel"><h2>Prestasi terverifikasi</h2>
    <ul class="plain-list">@foreach($club->awards as $award)<li><strong>{{ $award->award }}</strong> · {{ $award->title }} <span class="muted">{{ $award->levelLabel() }} · {{ $award->awarded_on->format('Y') }}</span></li>@endforeach</ul>
</section>
@endif
@if($club->clubActivities->isNotEmpty())
<section class="panel"><h2>Jadwal kegiatan</h2>
    <ul class="plain-list">@foreach($club->clubActivities as $activity)<li><a class="text-link" href="/kegiatan/{{ $activity->id }}">{{ $activity->title }}</a> <span class="muted">· {{ $activity->typeLabel() }} · {{ \App\Services\Wita::show($activity->starts_at, 'd M H:i') }} WITA · {{ $activity->location }}</span></li>@endforeach</ul>
</section>
@endif
@if($club->events->isNotEmpty())
<section class="panel"><h2>Lomba yang diajukan UKM</h2>
    <ul class="plain-list">@foreach($club->events as $event)<li>{{ $event->name }} <span class="muted">· {{ $event->pathway === 'delegation' ? 'Seleksi delegasi' : 'Lomba internal' }}</span></li>@endforeach</ul>
</section>
@endif
</div>
<div>
@if($club->category === 'himaju')
<section class="panel"><h2>Keanggotaan otomatis</h2><p>Seluruh mahasiswa aktif Jurusan {{ $club->department?->name }} otomatis menjadi anggota {{ $club->name }}, tanpa pendaftaran. Pindah jurusan memindahkan keanggotaan ke HIMAJU jurusan baru.</p>@if(auth()->user()->departmentId() === $club->department_id && auth()->user()->nim)<div class="alert success">Kamu anggota {{ $club->name }}.</div>@endif</section>
@elseif(! $club->isUkm())
<section class="panel"><h2>Anggota terpilih</h2><p>Anggota {{ $club->name }} ditetapkan oleh kemahasiswaan atau ketua organisasi dari hasil pemilihan mahasiswa. Setiap mahasiswa aktif dapat terpilih.</p></section>
@else
@forelse($club->recruitments as $r)
@php($mine = $applications->get($r->id))
<section class="panel">
    <span class="badge {{ $r->isOpen() ? 'status-accepted' : 'status-draft' }}">{{ $r->isOpen() ? 'Pendaftaran dibuka' : 'Di luar periode' }}</span>
    <h2>{{ $r->name }}</h2><p class="prewrap">{{ $r->requirements }}</p>
    @if($r->selection_notes)<p class="muted prewrap"><strong>Seleksi:</strong> {{ $r->selection_notes }}</p>@endif
    <div class="info-strip"><x-icon name="calendar"/>{{ \App\Services\Wita::show($r->opens_at, 'd M') }}–{{ \App\Services\Wita::show($r->closes_at, 'd M Y H:i') }} WITA</div>
    <p class="muted">Kuota penerimaan: {{ $r->quota }} anggota · {{ $r->applications()->where('status','accepted')->count() }} diterima @if($r->requires_document)· Wajib PDF persyaratan @endif</p>
    @if($mine && $mine->status !== 'revision')
        <div class="alert {{ $mine->status === 'accepted' ? 'success' : 'warning' }}">Status pendaftaranmu: <x-status :value="$mine->status"/> @if($mine->reason)· {{ $mine->reason }}@endif</div>
    @elseif($r->isOpen() && $club->active && auth()->user()->nim)
        <form action="/rekrutmen/{{ $r->id }}" method="post" enctype="multipart/form-data">@csrf
            @if($mine)<div class="alert warning">Perbaikan diminta: {{ $mine->reason }}</div>@endif
            <label>Minat dan motivasi<textarea name="motivation" rows="4" required minlength="20" maxlength="2000" placeholder="Ceritakan minatmu dan alasan ingin bergabung (minimal 20 karakter).">{{ old('motivation', $mine?->motivation) }}</textarea></label>
            @if($r->requires_document)<label>Dokumen persyaratan (PDF ≤ 5 MB){{ $mine?->document_path ? ' — kosongkan bila tidak diganti' : '' }}<input type="file" name="document" accept="application/pdf,.pdf" @required(!$mine?->document_path)></label>@endif
            <button class="button primary full">{{ $mine ? 'Kirim perbaikan' : 'Kirim pendaftaran' }} <x-icon name="arrow"/></button>
            <p class="form-note">Pengurus akan meninjau pengajuanmu. Status tersedia di Pendaftaran Saya.</p>
        </form>
    @endif
</section>
@empty<div class="panel empty">Belum ada periode rekrutmen.</div>@endforelse
@endif
</div>
</div>
</x-layout>
