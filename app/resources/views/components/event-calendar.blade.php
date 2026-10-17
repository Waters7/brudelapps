@props(['calendar', 'param' => 'kalender', 'anchor' => 'kalender', 'title' => 'Kalender lomba & kegiatan'])
@php
    $month = $calendar['month']->locale('id');
    $link = fn (string $value) => request()->fullUrlWithQuery([$param => $value]).'#'.$anchor;
    $tones = ['lomba' => 'bg-amber-500', 'kegiatan' => 'bg-brand-teal', 'libur' => 'bg-rose-500'];
    $tagTones = ['lomba' => 'bg-amber-100 text-amber-800', 'kegiatan' => 'bg-brand-teal/10 text-brand-teal', 'libur' => 'bg-rose-100 text-rose-700'];
    $count = collect($calendar['agenda'])->sum(fn ($day) => count($day['entries']));
@endphp
<section id="{{ $anchor }}" class="scroll-mt-24" data-calendar aria-labelledby="{{ $anchor }}-judul">
<div class="overflow-hidden rounded-3xl border border-brand-ink/5 bg-white shadow-sm">
    <div class="flex flex-col gap-4 border-b border-brand-ink/5 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
        <div>
            <p class="text-xs font-bold tracking-[0.2em] text-brand-teal">KALENDER</p>
            <h2 id="{{ $anchor }}-judul" class="mt-1 text-2xl font-bold tracking-tight text-brand-ink">{{ $title }}</h2>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ $link($calendar['prev']) }}" class="flex size-10 items-center justify-center rounded-full border border-brand-ink/10 text-lg text-brand-ink transition hover:border-brand-teal hover:text-brand-teal" aria-label="Bulan sebelumnya">‹</a>
            <strong class="min-w-36 text-center text-base font-bold capitalize text-brand-ink">{{ $month->translatedFormat('F Y') }}</strong>
            <a href="{{ $link($calendar['next']) }}" class="flex size-10 items-center justify-center rounded-full border border-brand-ink/10 text-lg text-brand-ink transition hover:border-brand-teal hover:text-brand-teal" aria-label="Bulan berikutnya">›</a>
            @if($calendar['month']->format('Y-m') !== $calendar['current'])<a href="{{ $link($calendar['current']) }}" class="whitespace-nowrap rounded-full bg-brand-ink px-4 py-2 text-xs font-semibold text-white transition hover:bg-brand-teal">Bulan ini</a>@endif
        </div>
    </div>
    <div class="grid grid-cols-1 lg:grid-cols-[1.35fr_1fr]">
        <div class="p-4 sm:p-6">
            <div class="grid grid-cols-7 gap-1 text-center text-[11px] font-bold tracking-wide text-brand-ink/50 sm:gap-2" aria-hidden="true">
                @foreach(['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $weekday)<span class="{{ $loop->last ? 'text-rose-600' : '' }}">{{ $weekday }}</span>@endforeach
            </div>
            <div class="mt-2 grid grid-cols-7 gap-1 sm:gap-2" role="group" aria-label="Tanggal {{ $month->translatedFormat('F Y') }}">
                @foreach($calendar['weeks'] as $week)
                    @foreach($week as $day)
                    @php($busy = array_sum($day['counts']) > 0)
                    <button type="button" @disabled(! $day['inMonth'])
                        class="group relative flex min-h-14 flex-col items-center justify-start rounded-xl border p-1 text-sm transition sm:min-h-20 sm:items-start sm:p-2 aria-pressed:border-brand-teal aria-pressed:bg-brand-teal/10 aria-pressed:ring-2 aria-pressed:ring-brand-teal/30 {{ $day['inMonth'] ? 'border-brand-ink/5 bg-brand-paper hover:border-brand-teal/40 hover:bg-white' : 'cursor-default border-transparent bg-transparent opacity-35' }} {{ $day['red'] && $day['inMonth'] ? 'bg-rose-50/70' : '' }}"
                        data-calendar-day="{{ $day['key'] }}" data-calendar-label="{{ $day['label'] }}" aria-pressed="false"
                        aria-label="{{ $day['label'] }}{{ $day['holidays'] ? ', '.implode(', ', $day['holidays']) : '' }}{{ $busy ? ', '.array_sum($day['counts']).' agenda' : '' }}">
                        <span class="flex size-7 items-center justify-center rounded-full font-bold {{ $day['today'] ? 'bg-brand-ink text-white' : ($day['red'] ? 'text-rose-600' : 'text-brand-ink') }}">{{ $day['day'] }}</span>
                        @if($day['holidays'] && $day['inMonth'])<span class="mt-0.5 hidden w-full truncate text-left text-[10px] font-semibold leading-tight text-rose-600 sm:block">{{ $day['holidays'][0] }}</span>@endif
                        @if($busy && $day['inMonth'])
                        <span class="mt-auto flex flex-wrap items-center justify-center gap-1 sm:justify-start">
                            @foreach(['lomba', 'kegiatan'] as $kind)@if($day['counts'][$kind])<span class="flex items-center gap-0.5 text-[10px] font-bold text-brand-ink/60"><span class="size-2 rounded-full {{ $tones[$kind] }}"></span><span class="hidden sm:inline">{{ $day['counts'][$kind] }}</span></span>@endif @endforeach
                        </span>
                        @endif
                    </button>
                    @endforeach
                @endforeach
            </div>
            <div class="mt-4 flex flex-wrap gap-4 text-xs text-brand-ink/60">
                <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-full {{ $tones['lomba'] }}"></span> Lomba (pendaftaran & pelaksanaan)</span>
                <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-full {{ $tones['kegiatan'] }}"></span> Kegiatan UKM</span>
                <span class="flex items-center gap-1.5"><span class="font-bold text-rose-600">17</span> Tanggal merah & hari Minggu</span>
            </div>
        </div>
        <div class="border-t border-brand-ink/5 bg-brand-paper/60 p-4 sm:p-6 lg:border-l lg:border-t-0">
            <div class="flex items-center justify-between gap-3">
                <h3 class="font-bold text-brand-ink" data-calendar-title data-month-label="Agenda {{ $month->translatedFormat('F Y') }}">Agenda {{ $month->translatedFormat('F Y') }}</h3>
                <button type="button" class="hidden rounded-full border border-brand-ink/10 bg-white px-3 py-1 text-xs font-semibold text-brand-ink/70 hover:text-brand-teal" data-calendar-all>Semua tanggal</button>
            </div>
            <p class="mt-1 text-xs text-brand-ink/50">{{ $count }} agenda bulan ini · pilih tanggal untuk melihat harinya saja</p>
            <ol class="mt-4 max-h-[460px] space-y-4 overflow-y-auto pr-1">
                @foreach($calendar['agenda'] as $date => $day)
                <li data-calendar-date="{{ $date }}">
                    <p class="text-xs font-bold {{ $day['red'] ? 'text-rose-600' : 'text-brand-ink/60' }}">{{ $day['label'] }}</p>
                    <ul class="mt-2 space-y-2">
                        @foreach($day['entries'] as $entry)
                        <li>
                            @if($entry['url'])<a href="{{ $entry['url'] }}" class="block rounded-2xl bg-white p-3 shadow-sm ring-1 ring-brand-ink/5 transition hover:-translate-y-0.5 hover:shadow-md">@else<div class="rounded-2xl bg-white p-3 shadow-sm ring-1 ring-brand-ink/5">@endif
                                <span class="inline-block rounded-full px-2 py-0.5 text-[10px] font-bold {{ $tagTones[$entry['kind']] }}">{{ $entry['tag'] }}</span>
                                <strong class="mt-1 block text-sm font-bold text-brand-ink">{{ $entry['title'] }}</strong>
                                @if($entry['detail'])<span class="block text-xs text-brand-ink/55">{{ $entry['detail'] }}</span>@endif
                            @if($entry['url'])</a>@else</div>@endif
                        </li>
                        @endforeach
                    </ul>
                </li>
                @endforeach
            </ol>
            <p class="{{ $calendar['agenda'] ? 'hidden' : '' }} rounded-2xl bg-white p-5 text-center text-sm text-brand-ink/55" data-calendar-empty>Belum ada agenda pada {{ $calendar['agenda'] ? 'tanggal ini' : 'bulan ini' }}.</p>
        </div>
    </div>
</div>
</section>
