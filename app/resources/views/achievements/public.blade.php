<x-public :title="'Prestasi '.$student->name">
<div class="page-heading"><div><p class="eyebrow">PROFIL PRESTASI · POLTEKKES KEMENKES MANADO</p><h1>{{ $student->name }}</h1><p>{{ $student->program?->name }}</p></div></div>
@forelse(config('brudel.fields') as $field)
@continue(!isset($awards[$field]))
<section class="section-block"><h2>{{ $field }}</h2>
<div class="catalog-grid">@foreach($awards[$field] as $recipient)
<article class="panel"><span class="eyebrow">{{ $recipient->award->levelLabel() }} · {{ $recipient->award->awarded_on->format('Y') }}</span><h3>{{ $recipient->award->award }}</h3><p>{{ $recipient->award->title }}</p><p class="muted">{{ $recipient->award->is_team ? 'Tim'.($recipient->award->team_name ? ' '.$recipient->award->team_name : '').' · '.ucfirst($recipient->team_role) : 'Individu' }}</p>
@if($recipient->award->documentation_url)<a class="text-link" href="{{ $recipient->award->documentation_url }}" target="_blank" rel="noopener noreferrer">Dokumentasi ↗</a>@endif</article>
@endforeach</div></section>
@empty
@endforelse
@if($awards->isEmpty())<div class="panel empty">Belum ada prestasi terverifikasi.</div>@endif
<p class="form-note">Ditampilkan atas persetujuan mahasiswa. NIM, dokumen persyaratan, dan bukti pembayaran tidak ditampilkan.</p>
</x-public>
