<x-layout :title="'Operasional '.$competition->name">
@php($bracket = $competition->assessment_type === 'bracket')
@php($rubric = $competition->assessment_type === 'rubric')
@php($published = $competition->resultsPublished())
<a class="back-link" href="/kelola/event/{{ $competition->event_id }}">← {{ $competition->event->name }}</a>
<div class="page-heading"><div><p class="eyebrow">OPERASIONAL LOMBA · {{ strtoupper($competition->field) }} · {{ $bracket ? 'SISTEM GUGUR' : ($rubric ? 'RUBRIK JURI' : 'TANPA PENILAIAN') }}</p><h1>{{ $competition->name }}</h1><p>{{ \App\Services\Wita::show($competition->starts_at) }} WITA · {{ $competition->location }}</p></div>
<div class="inline-actions">@if($competition->live_score)<a class="button outline" href="/live/{{ $competition->id }}">Live score</a><a class="button outline" href="/operator?lomba={{ $competition->id }}">Panel operator</a>@endif<a class="button outline" href="/kelola/sertifikat?lomba={{ $competition->id }}">Sertifikat</a>@if($competition->event->pathway === 'delegation')<a class="button primary" href="/delegasi/baru?lomba={{ $competition->id }}">Usulkan delegasi</a>@endif</div></div>

@if($published)<div class="alert success">Hasil disahkan {{ \App\Services\Wita::show($competition->results_published_at) }} WITA dan terkunci.</div>@endif

<section class="section-block">
<div class="section-heading"><h2>Pendaftar <span class="count-pill">{{ $registrations->count() }}</span></h2><a class="text-link" href="/tinjauan">Tinjau pendaftaran →</a></div>
<form method="post" action="/kelola/lomba/{{ $competition->id }}/unggulan">@csrf
<div class="panel table-panel"><div class="table-wrap"><table><thead><tr><th>Peserta/tim</th><th>Anggota</th><th>Status</th><th>Pembayaran</th>@if($bracket)<th>Unggulan</th>@endif</tr></thead><tbody>
@forelse($registrations as $r)
<tr><td><strong>{{ $r->displayName() }}</strong><small>{{ $r->user->name }} · {{ \App\Services\Wita::show($r->submitted_at, 'd M H:i') }}</small></td>
<td>{{ $r->participants->map(fn ($p) => $p->user->name)->join(', ') }}</td><td><x-status :value="$r->status"/></td><td><x-status :value="$r->payment_status"/></td>
@if($bracket)<td>@if($r->status === 'accepted')<input class="seed-input" type="number" min="1" max="512" name="seeds[{{ $r->id }}]" value="{{ $r->seed }}" @disabled($matches->isNotEmpty()) aria-label="Unggulan {{ $r->displayName() }}">@endif</td>@endif</tr>
@empty<tr><td colspan="5" class="empty">Belum ada pendaftaran terkirim.</td></tr>@endforelse
</tbody></table></div></div>
@if($bracket && $matches->isEmpty() && $registrations->where('status', 'accepted')->isNotEmpty())<button class="button outline">Simpan unggulan</button>@endif
</form>
</section>

<section class="panel">
    <div class="section-heading"><h2>Tim lomba</h2>@if($canSeeAccounts)<a class="button outline small" href="/kelola/lomba/{{ $competition->id }}/akun">Lihat akun lomba otomatis</a>@endif</div>
    <div class="inline-actions">
        @foreach(['pengelola_lomba' => 'Pengelola', 'operator' => 'Operator skor & live', 'penilai' => 'Juri ('.($staff['penilai'] ?? collect())->count().'/'.config('brudel.max_judges').')'] as $role => $label)
            <span><strong>{{ $label }}:</strong> {{ ($staff[$role] ?? collect())->map(fn ($a) => $a->user->name)->join(', ') ?: 'belum ada' }}</span>
        @endforeach
    </div>
    <p class="form-note">Akun pengelola, operator, dan juri dibuat otomatis untuk setiap lomba; pengaju lomba atau kemahasiswaan membagikannya. Penugasan tambahan di bawah opsional (total juri tetap maksimal {{ config('brudel.max_judges') }}).</p>
    <details><summary class="text-link">Tugaskan pengguna terdaftar</summary>
    <form class="form-grid" method="post" action="/kelola/penugasan">@csrf
        <input type="hidden" name="scope_id" value="{{ $competition->id }}">
        <label>Email/NIM<input name="user" required maxlength="190"></label>
        <label>Peran<select name="role"><option value="penilai">Juri/penilai</option><option value="operator">Operator skor & live score</option></select></label>
        <label>Mulai (WITA)<input type="datetime-local" name="starts_at" required value="{{ now()->timezone('Asia/Makassar')->format('Y-m-d\TH:i') }}"></label>
        <label>Berakhir (WITA)<input type="datetime-local" name="ends_at" required value="{{ \App\Services\Wita::input($competition->staffUntil()) }}"></label>
        <div class="span-2"><button class="button outline">Tugaskan</button><p class="form-note">Juri tidak dapat menilai diri atau timnya sendiri; operator tidak dapat mencatat pertandingan yang diikutinya.</p></div>
    </form></details>
</section>

@if($bracket)
<section class="section-block">
<div class="section-heading"><h2>Bagan sistem gugur</h2>
@if($matches->isEmpty())<form method="post" action="/kelola/lomba/{{ $competition->id }}/bagan" onsubmit="return confirm('Buat bagan dari peserta diterima? Unggulan akan dikunci.')">@csrf<button class="button primary">Buat bagan</button></form>@endif</div>
@if($matches->isNotEmpty())
<div class="bracket">
@foreach($matches as $round => $roundMatches)
<div class="bracket-round"><h3>{{ $round === $matches->keys()->max() ? 'Final' : ($round === $matches->keys()->max() - 1 ? 'Semifinal' : 'Babak '.$round) }}</h3>
@foreach($roundMatches as $m)
<div class="match-card {{ $m->status }}">
    <div class="match-meta">#{{ $m->position }} · <x-status :value="$m->status"/>@if($m->is_bye) <span class="chip">Bye</span>@endif</div>
    @foreach(['a' => $m->sideA, 'b' => $m->sideB] as $slot => $side)
        <div class="match-side {{ $side && $m->winner_registration_id === $side->id ? 'winner' : '' }}"><span>@if($m->{'seed_'.$slot})<small class="seed">{{ $m->{'seed_'.$slot} }}</small>@endif {{ $side?->displayName() ?? ($m->is_bye ? '— bye —' : 'Menunggu pemenang') }}</span><strong>{{ $m->{'score_'.$slot} }}</strong></div>
    @endforeach
    @if($m->score_detail)<small class="muted">{{ $m->score_detail }}</small>@endif
    @unless($m->is_bye)
    <details><summary class="text-link">Jadwal{{ $m->scheduled_at ? ': '.\App\Services\Wita::show($m->scheduled_at, 'd M H:i') : '' }}</summary>
        <form class="inline-form" method="post" action="/pertandingan/{{ $m->id }}/jadwal">@csrf<input type="datetime-local" name="scheduled_at" value="{{ \App\Services\Wita::input($m->scheduled_at) }}"><input name="location" value="{{ $m->location }}" placeholder="Lokasi"><button class="button outline small">Simpan</button></form></details>
    @if($m->sideA && $m->sideB && $m->status !== 'finished' && !$published)
    <details><summary class="text-link">Catat pemenang resmi</summary>
        <form class="inline-form" method="post" action="/pertandingan/{{ $m->id }}/pemenang">@csrf<input type="hidden" name="version" value="{{ $m->version }}">
            <select name="winner" required><option value="">Pemenang</option><option value="{{ $m->sideA->id }}">{{ $m->sideA->displayName() }}</option><option value="{{ $m->sideB->id }}">{{ $m->sideB->displayName() }}</option></select>
            <input type="number" name="score_a" value="{{ $m->score_a }}" placeholder="Skor A" min="0"><input type="number" name="score_b" value="{{ $m->score_b }}" placeholder="Skor B" min="0"><input name="score_detail" value="{{ $m->score_detail }}" placeholder="Detail set (21-18, 19-21, …)">
            <button class="button primary small">Sahkan hasil</button></form></details>
    @elseif($m->status === 'finished' && !$published)
    <details><summary class="text-link">Koreksi hasil</summary>
        <form class="inline-form" method="post" action="/pertandingan/{{ $m->id }}/koreksi">@csrf<input name="reason" required minlength="5" placeholder="Alasan koreksi"><button class="button outline small">Batalkan hasil</button></form>
        <p class="form-note">Koreksi ditolak bila babak berikutnya sudah berjalan.</p></details>
    @endif
    @endunless
</div>
@endforeach
</div>
@endforeach
</div>
@endif
</section>
@endif

@if($rubric)
<div class="detail-columns">
<section class="panel">
    <h2>Rubrik penilaian</h2>
    <p class="muted">Bobot total harus 100%. Rubrik terkunci setelah penilaian dimulai.</p>
    @if($competition->criteria->isEmpty())<div class="alert warning">Lembar nilai juri belum terbuka sampai rubrik disimpan.</div>@endif
    <form method="post" action="/kelola/lomba/{{ $competition->id }}/rubrik">@csrf
        <div class="table-wrap"><table class="compact"><thead><tr><th>Kriteria</th><th>Bobot %</th><th>Keterangan</th></tr></thead><tbody>
        @php($rows = $competition->criteria->values())
        @for($i = 0; $i < max(5, $rows->count() + 1); $i++)
            <tr><td><input name="criteria[{{ $i }}][name]" value="{{ $rows[$i]->name ?? '' }}" maxlength="120"></td><td><input type="number" step="0.01" min="0" max="100" name="criteria[{{ $i }}][weight]" value="{{ $rows[$i]->weight ?? '' }}"></td><td><input name="criteria[{{ $i }}][description]" value="{{ $rows[$i]->description ?? '' }}" maxlength="500"></td></tr>
        @endfor
        </tbody></table></div>
        <button class="button outline">Simpan rubrik</button>
    </form>
</section>
<section class="panel">
    <h2>Kelengkapan penilaian</h2>
    @if($missing === [])<div class="alert success">Seluruh penilai telah menilai seluruh peserta.</div>@else
    <ul class="plain-list warning-list">@foreach(array_slice($missing, 0, 12) as $line)<li>{{ $line }}</li>@endforeach @if(count($missing) > 12)<li>… dan {{ count($missing) - 12 }} lainnya</li>@endif</ul>@endif
    @if($ranking)
    <h3>Rekap sementara (belum resmi)</h3>
    <table class="compact"><thead><tr><th>#</th><th>Peserta</th><th>Nilai</th><th>Catatan</th></tr></thead><tbody>
    @foreach($ranking as $row)<tr><td>{{ $row['rank'] }}</td><td>{{ $row['registration']->displayName() }}</td><td>{{ number_format($row['total'], 2, ',', '.') }}</td><td>{{ $row['note'] }}</td></tr>@endforeach
    </tbody></table>
    @endif
</section>
</div>
<section class="panel">
    <div class="section-heading"><div><h2>Nilai per juri & log penilaian</h2><p>Setiap pengisian dan perubahan nilai tercatat (nilai lama, nilai baru, catatan, waktu, alamat IP).</p></div><a class="button outline small" href="/kelola/lomba/{{ $competition->id }}/log-nilai">Unduh log lengkap (CSV) ↓</a></div>
    @if($judgeTotals->isNotEmpty())
    <div class="table-wrap"><table class="compact"><thead><tr><th>Peserta</th><th>Total per juri (Σ nilai × bobot)</th></tr></thead><tbody>
    @foreach($judgeTotals as $registrationId => $byJudge)<tr><td>{{ $registrations->firstWhere('id', $registrationId)?->displayName() }}</td><td>@foreach($byJudge as $row)<span class="chip">{{ $row['judge'] }}: {{ number_format($row['total'], 2, ',', '.') }} ({{ $row['count'] }}/{{ $competition->criteria->count() }})</span>@endforeach</td></tr>@endforeach
    </tbody></table></div>
    @endif
    <div class="table-wrap"><table class="compact"><thead><tr><th>Waktu WITA</th><th>Juri</th><th>Peserta · kriteria</th><th>Perubahan</th></tr></thead><tbody>
    @forelse($scoreLogs as $log)<tr><td>{{ \App\Services\Wita::show($log->created_at, 'd M H:i:s') }}</td><td>{{ $log->judge->name }}</td><td>{{ $log->registration->displayName() }}<small>{{ $log->criterion->name }}</small></td><td>{{ $log->old_score === null ? 'baru' : number_format($log->old_score, 2, ',', '') }} → <strong>{{ number_format($log->new_score, 2, ',', '') }}</strong>@if($log->note)<small>{{ $log->note }}</small>@endif</td></tr>
    @empty<tr><td colspan="4" class="empty">Belum ada nilai masuk.</td></tr>@endforelse
    </tbody></table></div>
</section>
@endif

@if($bracket || $rubric)
<section class="panel">
    <div class="section-heading"><h2>Hasil resmi</h2>
    @if(!$published)<form method="post" action="/kelola/lomba/{{ $competition->id }}/sahkan" onsubmit="return confirm('Sahkan dan kunci hasil? Hasil final hanya dapat disahkan bila penilaian/pertandingan lengkap.')">@csrf<button class="button primary">Sahkan hasil</button></form>@endif</div>
    @if($results->isEmpty())<p class="muted">Belum ada hasil resmi.</p>@else
    <div class="table-wrap"><table class="compact"><thead><tr><th>Peringkat</th><th>Peserta</th><th>Nilai</th><th>Catatan</th><th>Penghargaan</th></tr></thead><tbody>
    @foreach($results as $result)
    @php($given = $awards->where('registration_id', $result->registration_id))
    <tr><td><strong>{{ $result->rank }}</strong></td><td>{{ $result->registration->displayName() }}</td><td>{{ $result->total !== null ? number_format($result->total, 2, ',', '.') : '—' }}</td><td>{{ $result->note }}</td>
    <td>@foreach($given as $award)<span class="chip">{{ $award->award }}</span>@endforeach
        <form class="inline-form" method="post" action="/kelola/lomba/{{ $competition->id }}/penghargaan">@csrf<input type="hidden" name="registration_id" value="{{ $result->registration_id }}"><select name="award">@foreach(['Juara 1', 'Juara 2', 'Juara 3', 'Harapan 1', 'Harapan 2', 'Penampil Terbaik'] as $label)<option @selected($label === 'Juara '.$result->rank)>{{ $label }}</option>@endforeach</select><button class="button outline small">Tetapkan</button></form></td></tr>
    @endforeach
    </tbody></table></div>
    <p class="form-note">Penghargaan tim dicatat satu kali dengan setiap anggota sebagai penerima. {{ $awards->count() }} penghargaan · {{ $certificates }} sertifikat terbit.</p>
    @endif
    @if($published && auth()->user()->isStudentAffairs())
    <details><summary class="text-link">Buka koreksi hasil (kemahasiswaan)</summary>
        <form class="inline-form" method="post" action="/kelola/lomba/{{ $competition->id }}/buka-koreksi">@csrf<input name="reason" required minlength="10" placeholder="Alasan koreksi (min. 10 karakter)"><button class="button outline small">Buka koreksi</button></form>
        <p class="form-note">Ditolak bila sudah ada penghargaan atau sertifikat sah dari hasil ini.</p></details>
    @endif
</section>
@endif
</x-layout>
