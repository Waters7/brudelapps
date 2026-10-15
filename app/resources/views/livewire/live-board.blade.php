<div wire:poll.10s class="live-board" data-checked="{{ $checkedAt->getTimestampMs() }}"
    x-data="{ stale: false, serverStart: {{ $checkedAt->getTimestampMs() }}, clientStart: Date.now(),
        check() { const lag = (Date.now() - this.clientStart) - (Number($root.dataset.checked) - this.serverStart); this.stale = !navigator.onLine || lag > 30000; } }"
    x-init="setInterval(() => check(), 5000)">
    <div class="page-heading"><div><p class="eyebrow">{{ $competition->event->name }} · {{ strtoupper($competition->field) }}</p><h1>{{ $competition->name }}</h1>
        <p>Pembaruan terakhir: <strong>{{ $updated_at ? \App\Services\Wita::show($updated_at, 'd M Y, H:i:s') . ' WITA' : 'belum ada' }}</strong> · diperiksa {{ \App\Services\Wita::show($checkedAt, 'H:i:s') }} WITA</p></div>
        <span class="badge {{ $official ? 'status-accepted' : 'status-submitted' }} big-badge">{{ $official ? 'HASIL RESMI' : 'SKOR SEMENTARA' }}</span></div>
    <div class="alert warning" x-show="stale" x-cloak role="status">Koneksi terputus atau pembaruan tertunda. Skor di layar mungkin belum diperbarui.</div>

    @if($matches->isNotEmpty())
    <div class="bracket">
    @foreach($matches as $round => $roundMatches)
        <div class="bracket-round"><h3>{{ $round === $matches->keys()->max() ? 'Final' : ($round === $matches->keys()->max() - 1 ? 'Semifinal' : 'Babak '.$round) }}</h3>
        @foreach($roundMatches as $m)
            <div class="match-card {{ $m->status }}">
                <div class="match-meta">@if($m->status === 'live')<span class="live-dot"></span> BERLANGSUNG · sementara @elseif($m->status === 'finished') {{ $m->is_bye ? 'Bye' : 'Hasil resmi' }} @else {{ $m->scheduled_at ? \App\Services\Wita::show($m->scheduled_at, 'd M H:i') : 'Menunggu jadwal' }} @endif {{ $m->period_label ? '· '.$m->period_label : '' }}</div>
                @foreach(['a' => $m->sideA, 'b' => $m->sideB] as $slot => $side)
                    <div class="match-side {{ $side && $m->winner_registration_id === $side->id ? 'winner' : '' }}"><span>{{ $side?->displayName() ?? ($m->is_bye ? '— bye —' : 'Menunggu') }}</span><strong>{{ $m->{'score_'.$slot} }}</strong></div>
                @endforeach
                @if($m->score_detail)<small class="muted">{{ $m->score_detail }}</small>@endif
            </div>
        @endforeach
        </div>
    @endforeach
    </div>
    @endif

    @if($performances->isNotEmpty())
    <section class="panel"><h2>Urutan & status tampil</h2>
        <ol class="performance-list">@foreach($performances as $entry)<li class="{{ $entry->performance_status }}"><span>{{ $entry->performance_order ?? '–' }}</span><strong>{{ $entry->displayName() }}</strong><x-status :value="$entry->performance_status ?? 'waiting'"/></li>@endforeach</ol>
    </section>
    @endif

    @if($ranking->isNotEmpty())
    <section class="panel"><h2>{{ $official ? 'Peringkat resmi' : 'Peringkat sementara' }}</h2>
        @unless($official)<p class="form-note">Peringkat sementara dihitung dari nilai yang sudah masuk dan dapat berubah sampai hasil disahkan.</p>@endunless
        <table class="compact"><thead><tr><th>#</th><th>Peserta</th><th>Nilai</th><th></th></tr></thead><tbody>
        @foreach($ranking as $row)<tr><td><strong>{{ $row['rank'] }}</strong></td><td>{{ $row['registration']->displayName() }}</td><td>{{ $row['total'] !== null ? number_format($row['total'], 2, ',', '.') : '—' }}</td><td>{{ $row['note'] }}</td></tr>@endforeach
        </tbody></table>
    </section>
    @endif

    @if($matches->isEmpty() && $performances->isEmpty())<div class="panel empty">Belum ada pertandingan atau peserta yang dijadwalkan.</div>@endif
</div>
