<?php

namespace App\Services\Campus;

use App\Models\Assignment;
use App\Models\MasterOption;
use App\Models\User;
use App\Services\DomainService;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Master data of kemahasiswaan direktorat: the choice lists used across BRUDEL. Codes are stable keys stored by
 * other records; labels may be renamed (a renamed bidang or jabatan is updated wherever it was stored).
 * Inactive options disappear from new choices but stay valid for existing records.
 */
class MasterData extends DomainService
{
    /**
     * Sub menus and the extra attributes of each list.
     *
     * @var array<string, array{label: string, icon: string, description: string, meta: array<string, array<string, mixed>>}>
     */
    public const TYPES = [
        'field' => [
            'label' => 'Bidang & kategori', 'icon' => 'grid',
            'description' => 'Kategori UKM, lomba, prestasi, delegasi, dan laporan. Bidang pilihan tunggal membatasi mahasiswa pada satu UKM di bidang itu (mis. Kerohanian).',
            'meta' => ['single_choice' => ['type' => 'bool', 'label' => 'Mahasiswa hanya boleh memilih satu UKM pada bidang ini']],
        ],
        'activity_type' => [
            'label' => 'Jenis kegiatan', 'icon' => 'calendar',
            'description' => 'Jenis kegiatan UKM di Jelajah Kegiatan. Format laporan menentukan isi laporan setelah kegiatan.',
            'meta' => [
                'report' => ['type' => 'select', 'label' => 'Format laporan', 'options' => ['attendance' => 'Daftar hadir & tidak hadir', 'match' => 'Lawan, skor, dan catatan', 'minutes' => 'Notulen wajib (PDF/DOCX)']],
                'internal_only' => ['type' => 'bool', 'label' => 'Selalu internal anggota (tidak tampil di halaman depan)'],
            ],
        ],
        'location' => [
            'label' => 'Lokasi & ruangan', 'icon' => 'flag',
            'description' => 'Pilihan lokasi kegiatan dan lomba. Ruangan yang perlu izin muncul sebagai pilihan saat pengurus meminjam ruangan.',
            'meta' => [
                'kind' => ['type' => 'select', 'label' => 'Jenis tempat', 'options' => ['ruang' => 'Ruang kelas/rapat', 'aula' => 'Aula', 'lab' => 'Laboratorium', 'lapangan' => 'Lapangan/GOR', 'luar' => 'Luar kampus']],
                'capacity' => ['type' => 'number', 'label' => 'Kapasitas (orang)', 'min' => 0, 'max' => 100000],
                'permit' => ['type' => 'bool', 'label' => 'Peminjaman perlu izin kemahasiswaan'],
            ],
        ],
        'competition_type' => [
            'label' => 'Jenis lomba', 'icon' => 'trophy',
            'description' => 'Template nomor lomba: memilih jenis lomba di form nomor lomba mengisi bidang, format, jumlah anggota, dan cara penilaian.',
            'meta' => [
                'field' => ['type' => 'field', 'label' => 'Bidang'],
                'format' => ['type' => 'select', 'label' => 'Format peserta', 'options' => ['individual' => 'Individu', 'team' => 'Tim']],
                'min_members' => ['type' => 'number', 'label' => 'Minimum anggota tim', 'min' => 1, 'max' => 50],
                'max_members' => ['type' => 'number', 'label' => 'Maksimum anggota tim', 'min' => 1, 'max' => 50],
                'assessment_type' => ['type' => 'select', 'label' => 'Penilaian', 'options' => ['none' => 'Tanpa penilaian', 'bracket' => 'Sistem gugur', 'rubric' => 'Rubrik juri']],
            ],
        ],
        'officer_position' => [
            'label' => 'Jabatan pengurus', 'icon' => 'users',
            'description' => 'Jabatan pengurus organisasi. Jabatan ketua dapat mengajukan event; pengelola pengurus dapat menambah dan mengakhiri pengurus lain.',
            'meta' => [
                'chair' => ['type' => 'bool', 'label' => 'Jabatan ketua (mengajukan event atas nama organisasi)'],
                'manager' => ['type' => 'bool', 'label' => 'Dapat menambah dan mengakhiri pengurus'],
            ],
        ],
        'award' => [
            'label' => 'Jenis penghargaan', 'icon' => 'star',
            'description' => 'Pilihan penghargaan saat mencatat prestasi, hasil delegasi, dan penghargaan lomba. Isian bebas tetap diterima.',
            'meta' => ['rank' => ['type' => 'number', 'label' => 'Urutan (1 = tertinggi)', 'min' => 1, 'max' => 99]],
        ],
        'registration_document' => [
            'label' => 'Dokumen pendaftaran lomba', 'icon' => 'file',
            'description' => 'Dokumen yang disiapkan untuk pendaftaran delegasi ke penyelenggara lomba (menu Perjadin Delegasi dan data untuk Pangi).',
            'meta' => ['per_student' => ['type' => 'bool', 'label' => 'Dibuat per mahasiswa']],
        ],
        'holiday' => [
            'label' => 'Hari libur (tanggal merah)', 'icon' => 'calendar',
            'description' => 'Tanggal merah di kalender halaman depan dan dashboard. Tambahkan libur nasional dan cuti bersama sesuai SKB 3 Menteri setiap tahun; libur bertanggal tetap (mis. 17 Agustus) cukup ditandai berulang.',
            'meta' => [
                'date' => ['type' => 'date', 'label' => 'Tanggal'],
                'kind' => ['type' => 'select', 'label' => 'Jenis libur', 'options' => ['nasional' => 'Libur nasional', 'cuti' => 'Cuti bersama', 'kampus' => 'Libur kampus']],
                'yearly' => ['type' => 'bool', 'label' => 'Berulang setiap tahun pada tanggal yang sama'],
            ],
        ],
    ];

    /** @var array<string, Collection<int, MasterOption>> */
    private array $loaded = [];

    /**
     * @return Collection<int, MasterOption>
     */
    public function all(string $type): Collection
    {
        return $this->loaded[$type] ??= MasterOption::where('type', $type)->orderBy('position')->orderBy('id')->get();
    }

    /**
     * @return Collection<int, MasterOption>
     */
    public function active(string $type): Collection
    {
        return $this->all($type)->where('active', true)->values();
    }

    public function flush(): void
    {
        $this->loaded = [];
    }

    /**
     * Active bidang, plus $current when an existing record still uses an inactive one.
     *
     * @return list<string>
     */
    public function fields(?string $current = null): array
    {
        $labels = $this->active('field')->pluck('label')->all();

        return $current && ! in_array($current, $labels, true) ? [...$labels, $current] : $labels;
    }

    /**
     * Every bidang ever offered, for validating edits of existing records.
     *
     * @return list<string>
     */
    public function allFields(): array
    {
        return $this->all('field')->pluck('label')->all();
    }

    /**
     * @return list<string>
     */
    public function singleChoiceFields(): array
    {
        return $this->all('field')->filter(fn ($option) => $option->meta('single_choice'))->pluck('label')->values()->all();
    }

    /**
     * @return array<string, string> code => label
     */
    public function activityTypes(): array
    {
        return $this->active('activity_type')->pluck('label', 'code')->all();
    }

    public function activityTypeLabel(string $code): string
    {
        return $this->all('activity_type')->firstWhere('code', $code)?->label ?? $code;
    }

    /**
     * Report format of an activity type: attendance, match, or minutes.
     */
    public function activityReport(string $code): string
    {
        return $this->all('activity_type')->firstWhere('code', $code)?->meta('report', 'attendance') ?? 'attendance';
    }

    public function activityInternalOnly(string $code): bool
    {
        return (bool) $this->all('activity_type')->firstWhere('code', $code)?->meta('internal_only');
    }

    /**
     * @return list<string>
     */
    public function officerPositions(): array
    {
        return $this->active('officer_position')->pluck('label')->all();
    }

    /**
     * @return list<string>
     */
    public function chairPositions(): array
    {
        return $this->all('officer_position')->filter(fn ($option) => $option->meta('chair'))->pluck('label')->values()->all();
    }

    /**
     * @return list<string>
     */
    public function officerManagers(): array
    {
        return $this->all('officer_position')->filter(fn ($option) => $option->meta('manager'))->pluck('label')->values()->all();
    }

    /**
     * @return Collection<int, MasterOption>
     */
    public function rooms(): Collection
    {
        return $this->active('location')->filter(fn ($option) => $option->meta('permit'))->values();
    }

    /**
     * @return list<string>
     */
    public function awards(): array
    {
        return $this->active('award')->sortBy(fn ($option) => $option->meta('rank', 99))->pluck('label')->values()->all();
    }

    /**
     * @return array<string, string> code => label
     */
    public function registrationDocuments(): array
    {
        return $this->active('registration_document')->pluck('label', 'code')->all();
    }

    /**
     * Active tanggal merah between two dates (inclusive), yearly holidays repeated in every year of the range.
     *
     * @return array<string, list<array{name: string, kind: string}>> keyed by Y-m-d
     */
    public function holidays(CarbonInterface $from, CarbonInterface $to): array
    {
        $days = [];
        foreach ($this->active('holiday') as $holiday) {
            $date = $holiday->meta('date');
            if (! is_string($date) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                continue;
            }
            $years = $holiday->meta('yearly') ? range($from->year, $to->year) : [(int) substr($date, 0, 4)];
            foreach ($years as $year) {
                $day = $year.substr($date, 4);
                if ($day >= $from->format('Y-m-d') && $day <= $to->format('Y-m-d') && checkdate((int) substr($day, 5, 2), (int) substr($day, 8, 2), $year)) {
                    $days[$day][] = ['name' => $holiday->label, 'kind' => (string) $holiday->meta('kind', 'nasional')];
                }
            }
        }
        ksort($days);

        return $days;
    }

    /**
     * @param  array{label: string, position?: ?int, active?: ?bool, meta?: array<string, mixed>}  $data
     */
    public function save(User $actor, string $type, ?MasterOption $option, array $data): MasterOption
    {
        abort_unless($actor->isStudentAffairs(), 403);
        abort_unless(isset(self::TYPES[$type]), 404);
        abort_if($option && $option->type !== $type, 404);
        $label = trim(preg_replace('/\s+/', ' ', $data['label']));
        $this->ensure($label !== '' && mb_strlen($label) <= 120, 'Nama pilihan wajib diisi (maksimal 120 karakter).');
        $this->ensure(! str_contains($label, ','), 'Nama pilihan tidak boleh memuat koma.');
        $this->ensure(! MasterOption::where('type', $type)->whereRaw('LOWER(label) = ?', [mb_strtolower($label)])->when($option, fn ($q) => $q->whereKeyNot($option->id))->exists(),
            'Pilihan "'.$label.'" sudah ada.');
        $meta = $this->meta($type, $data['meta'] ?? []);
        $active = (bool) ($data['active'] ?? true);
        if ($option && ! $active) {
            $this->ensure($this->active($type)->whereNotIn('id', [$option->id])->isNotEmpty() || in_array($type, ['location', 'competition_type', 'award', 'registration_document', 'holiday'], true),
                'Minimal satu pilihan '.mb_strtolower(self::TYPES[$type]['label']).' harus tetap aktif.');
        }
        if ($type === 'officer_position' && $option) {
            $others = $this->all($type)->whereNotIn('id', [$option->id]);
            $this->ensure(($meta['chair'] ?? false) || $others->contains(fn ($o) => $o->meta('chair')), 'Minimal satu jabatan harus berstatus ketua.');
        }

        return DB::transaction(function () use ($actor, $type, $option, $data, $label, $meta, $active) {
            $old = $option?->label;
            $option ??= new MasterOption(['type' => $type, 'code' => $this->code($type, $label),
                'position' => (int) MasterOption::where('type', $type)->max('position') + 1]);
            $option->fill(['label' => $label, 'meta' => $meta, 'active' => $active] + (isset($data['position']) ? ['position' => (int) $data['position']] : []))->save();
            if ($old !== null && $old !== $label) {
                $this->renameReferences($type, $old, $label);
            }
            $this->audit($actor, $option->wasRecentlyCreated ? 'master.created' : 'master.updated', $option, ['type' => $type, 'label' => $label, 'from' => $old]);
            $this->flush();

            return $option;
        });
    }

    /**
     * Unused options are deleted; options still referenced must be deactivated instead.
     */
    public function delete(User $actor, MasterOption $option): void
    {
        abort_unless($actor->isStudentAffairs(), 403);
        $used = $this->usage($option);
        $this->ensure($used === 0, 'Pilihan ini masih dipakai '.$used.' data. Nonaktifkan agar tidak muncul lagi tanpa mengubah data lama.');
        $this->ensure($this->active($option->type)->whereNotIn('id', [$option->id])->isNotEmpty() || ! in_array($option->type, ['field', 'activity_type', 'officer_position'], true),
            'Minimal satu pilihan harus tetap ada.');
        $this->audit($actor, 'master.deleted', $option, ['type' => $option->type, 'label' => $option->label]);
        $option->delete();
        $this->flush();
    }

    /**
     * Number of records that store this option.
     */
    public function usage(MasterOption $option): int
    {
        return match ($option->type) {
            'field' => collect(['clubs', 'competitions', 'award_results', 'delegations'])->sum(fn ($table) => DB::table($table)->where('field', $option->label)->count())
                + DB::table('master_options')->where('type', 'competition_type')->where('meta->field', $option->label)->count(),
            'activity_type' => DB::table('club_activities')->where('type', $option->code)->count(),
            'officer_position' => Assignment::where('role', 'pengurus')->where('position', $option->label)->count(),
            default => 0,
        };
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function meta(string $type, array $input): array
    {
        $meta = [];
        foreach (self::TYPES[$type]['meta'] as $key => $spec) {
            $value = $input[$key] ?? null;
            $meta[$key] = match ($spec['type']) {
                'bool' => (bool) $value,
                'number' => $value === null || $value === '' ? null : (int) $value,
                'select' => $value,
                'field' => $value === null || $value === '' ? null : $value,
                'date' => is_string($value) ? trim($value) : null,
            };
            if ($spec['type'] === 'date') {
                $valid = is_string($meta[$key]) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $meta[$key], $parts) && checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]);
                $this->ensure($valid, $spec['label'].' wajib diisi dengan tanggal yang valid.');
            }
            if ($spec['type'] === 'select') {
                $this->ensure(array_key_exists((string) $value, $spec['options']), $spec['label'].' wajib dipilih.');
            }
            if ($spec['type'] === 'field' && $meta[$key] !== null) {
                $this->ensure(in_array($meta[$key], $this->allFields(), true), 'Bidang tidak dikenal.');
            }
            if ($spec['type'] === 'number' && $meta[$key] !== null) {
                $this->ensure($meta[$key] >= $spec['min'] && $meta[$key] <= $spec['max'], $spec['label'].' harus '.$spec['min'].'–'.$spec['max'].'.');
            }
        }
        if ($type === 'competition_type' && $meta['format'] === 'team') {
            $this->ensure(($meta['min_members'] ?? 2) <= ($meta['max_members'] ?? 2), 'Minimum anggota tidak boleh melebihi maksimum.');
        }

        return $meta;
    }

    private function code(string $type, string $label): string
    {
        $base = Str::slug($label, '_') ?: 'pilihan';
        $code = $base;
        for ($i = 2; MasterOption::where('type', $type)->where('code', $code)->exists(); $i++) {
            $code = $base.'_'.$i;
        }

        return $code;
    }

    /**
     * Bidang and jabatan are stored by label, so a rename follows them into existing records.
     */
    private function renameReferences(string $type, string $old, string $new): void
    {
        if ($type === 'field') {
            foreach (['clubs', 'competitions', 'award_results', 'delegations'] as $table) {
                DB::table($table)->where('field', $old)->update(['field' => $new]);
            }
            MasterOption::where('type', 'competition_type')->get()->filter(fn ($o) => $o->meta('field') === $old)
                ->each(fn ($o) => $o->update(['meta' => ['field' => $new] + $o->meta]));
        }
        if ($type === 'officer_position') {
            Assignment::where('role', 'pengurus')->where('position', $old)->update(['position' => $new]);
            Assignment::$revision++;
        }
    }
}
