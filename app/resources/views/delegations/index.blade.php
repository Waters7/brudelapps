<x-layout title="Delegasi">
<div class="page-heading"><div><p class="eyebrow">SELEKSI DELEGASI NASIONAL</p><h1>Delegasi kampus.</h1><p>Pendaftaran seleksi → verifikasi → penilaian → hasil seleksi → usulan delegasi → keputusan pimpinan → surat tugas → keikutsertaan → pencatatan hasil. Lolos seleksi tidak otomatis menjadi delegasi.</p></div>
@if($canPropose)<a class="button primary" href="/delegasi/baru">Usulan tanpa seleksi BRUDEL</a>@endif</div>

@if($selections->isNotEmpty())
<section class="panel"><h2>Nomor lomba seleksi delegasi</h2>
<ul class="plain-list">@foreach($selections as $c)<li><strong>{{ $c->name }}</strong> · {{ $c->event->name }} · {{ $c->resultsPublished() ? 'Hasil seleksi disahkan' : 'Seleksi berjalan' }} <a class="text-link" href="/delegasi/baru?lomba={{ $c->id }}">Usulkan delegasi →</a></li>@endforeach</ul>
</section>
@endif

<div class="panel table-panel"><div class="table-wrap"><table><thead><tr><th>Delegasi</th><th>Tujuan</th><th>Jadwal (WITA)</th><th>Anggota</th><th>Status</th></tr></thead><tbody>
@forelse($delegations as $d)
<tr><td><a class="text-link" href="/delegasi/{{ $d->id }}"><strong>{{ $d->title }}</strong></a><small>{{ $d->competition?->name ?? 'Tanpa seleksi BRUDEL' }}</small></td><td>{{ $d->destination_event }}<small>{{ config('brudel.levels')[$d->level] }} · {{ $d->organizer }}</small></td><td>{{ \App\Services\Wita::show($d->starts_at, 'd M Y') }}</td><td><div class="avatar-stack" title="{{ $d->members->map(fn ($m) => $m->user->name)->join(', ') }}">@foreach($d->members->take(5) as $member)<x-avatar :user="$member->user"/>@endforeach @if($d->members_count > 5)<span class="avatar small more">+{{ $d->members_count - 5 }}</span>@endif</div></td><td><x-status :value="$d->status"/>@if($d->letter_number)<small>Surat: {{ $d->letter_number }}</small>@endif</td></tr>
@empty<tr><td colspan="5" class="empty">Belum ada delegasi dalam lingkup Anda.</td></tr>@endforelse
</tbody></table></div></div>
</x-layout>
