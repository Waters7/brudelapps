<?php

namespace App\Services\Campus;

use App\Models\ClubActivity;
use App\Models\Competition;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Month calendar for the front page and the dashboard: competition dates (registration opens and closes, competition
 * day), the UKM activities the viewer may see (public ones for guests), Sundays, and tanggal merah from master data.
 * Every date is WITA.
 */
class CalendarService
{
    public const KINDS = [
        'lomba' => 'Lomba',
        'kegiatan' => 'Kegiatan UKM',
        'libur' => 'Tanggal merah',
    ];

    public function __construct(private MasterData $master) {}

    /**
     * The month asked for as "YYYY-MM"; the current month when missing, malformed, or more than two years away.
     */
    public function resolveMonth(mixed $value): CarbonImmutable
    {
        $current = CarbonImmutable::now(config('brudel.timezone'))->startOfMonth();
        if (! is_string($value) || ! preg_match('/^(\d{4})-(\d{2})$/', $value, $parts) || (int) $parts[2] < 1 || (int) $parts[2] > 12) {
            return $current;
        }
        $month = CarbonImmutable::create((int) $parts[1], (int) $parts[2], 1, 0, 0, 0, config('brudel.timezone'));

        return $month->between($current->subYears(2), $current->addYears(2)) ? $month : $current;
    }

    /**
     * Weeks (Monday first) with per-day counts, and the month's agenda grouped by day.
     *
     * @return array{month: CarbonImmutable, prev: string, next: string, current: string,
     *     weeks: list<list<array{key: string, day: int, label: string, inMonth: bool, today: bool, red: bool, holidays: list<string>, counts: array<string, int>}>>,
     *     agenda: array<string, array{label: string, red: bool, entries: list<array{kind: string, tag: string, title: string, detail: string, url: ?string}>}>}
     */
    public function month(CarbonImmutable $month, ?User $viewer): array
    {
        $first = $month->startOfMonth();
        $gridStart = $first->startOfWeek(CarbonImmutable::MONDAY);
        $gridEnd = $first->endOfMonth()->endOfWeek(CarbonImmutable::SUNDAY)->startOfDay();
        $entries = $this->entries($gridStart, $gridEnd->endOfDay(), $viewer);
        foreach ($this->master->holidays($gridStart, $gridEnd) as $date => $holidays) {
            foreach ($holidays as $holiday) {
                $entries[$date][] = ['kind' => 'libur', 'tag' => ['cuti' => 'Cuti bersama', 'kampus' => 'Libur kampus'][$holiday['kind']] ?? 'Libur nasional',
                    'title' => $holiday['name'], 'detail' => '', 'url' => null, 'sort' => -1];
            }
        }
        $today = CarbonImmutable::now(config('brudel.timezone'))->format('Y-m-d');

        $weeks = [];
        $agenda = [];
        for ($day = $gridStart; $day->lte($gridEnd); $day = $day->addDay()) {
            $key = $day->format('Y-m-d');
            $list = collect($entries[$key] ?? [])->sortBy('sort')->values();
            $holidays = $list->where('kind', 'libur')->pluck('title')->all();
            $inMonth = $day->month === $first->month;
            $red = $day->isSunday() || $holidays !== [];
            $label = $day->locale('id')->translatedFormat('l, j F Y');
            $weeks[intdiv((int) round($gridStart->diffInDays($day)), 7)][] = [
                'key' => $key, 'day' => $day->day, 'label' => $label, 'inMonth' => $inMonth, 'today' => $key === $today, 'red' => $red,
                'holidays' => $holidays, 'counts' => array_map(fn ($kind) => $list->where('kind', $kind)->count(), array_combine(array_keys(self::KINDS), array_keys(self::KINDS))),
            ];
            if ($inMonth && $list->isNotEmpty()) {
                $agenda[$key] = ['label' => $label, 'red' => $red, 'entries' => $list->map(fn ($entry) => collect($entry)->except('sort')->all())->all()];
            }
        }

        return [
            'month' => $first,
            'prev' => $first->subMonth()->format('Y-m'),
            'next' => $first->addMonth()->format('Y-m'),
            'current' => CarbonImmutable::now(config('brudel.timezone'))->format('Y-m'),
            'weeks' => array_values($weeks),
            'agenda' => $agenda,
        ];
    }

    /**
     * Competition dates and activities between two WITA moments, keyed by WITA day.
     *
     * @return array<string, list<array{kind: string, tag: string, title: string, detail: string, url: ?string, sort: int}>>
     */
    private function entries(CarbonImmutable $from, CarbonImmutable $to, ?User $viewer): array
    {
        $range = [$from->utc(), $to->utc()];
        $local = fn ($moment) => CarbonImmutable::instance($moment)->setTimezone(config('brudel.timezone'));
        $entries = [];

        $competitions = Competition::with('event')->whereHas('event', fn ($q) => $q->where('status', 'approved'))
            ->where(fn ($q) => $q->whereBetween('opens_at', $range)->orWhereBetween('closes_at', $range)->orWhereBetween('starts_at', $range))->get();
        foreach ($competitions as $competition) {
            foreach (['opens_at' => 'Pendaftaran dibuka', 'closes_at' => 'Pendaftaran ditutup', 'starts_at' => 'Pelaksanaan lomba'] as $column => $tag) {
                $at = $local($competition->{$column});
                if ($at->betweenIncluded($from, $to)) {
                    $entries[$at->format('Y-m-d')][] = ['kind' => 'lomba', 'tag' => $tag, 'title' => $competition->name,
                        'detail' => $at->format('H:i').' WITA · '.($column === 'starts_at' ? $competition->location : $competition->event->name),
                        'url' => '/lomba/'.$competition->id, 'sort' => (int) $at->format('Hi')];
                }
            }
        }

        $activities = ClubActivity::with('club')->where('status', '!=', 'cancelled')->whereBetween('starts_at', $range)
            ->when($viewer, fn ($q) => $q->visibleTo($viewer), fn ($q) => $q->confirmed()->where('is_public', true))->get();
        foreach ($activities as $activity) {
            $at = $local($activity->starts_at);
            $entries[$at->format('Y-m-d')][] = ['kind' => 'kegiatan', 'tag' => $activity->typeLabel(), 'title' => $activity->title,
                'detail' => $at->format('H:i').' WITA · '.$activity->club->name.' · '.$activity->location,
                'url' => $viewer ? '/kegiatan/'.$activity->id : null, 'sort' => (int) $at->format('Hi')];
        }

        return $entries;
    }
}
