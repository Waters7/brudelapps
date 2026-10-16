<?php

namespace App\Services\Delegation;

use App\Models\Delegation;
use App\Services\Campus\MasterData;
use App\Services\DomainService;
use Illuminate\Support\Collection;

/**
 * Prepares delegation data for the student official-travel (perjadin mahasiswa) letters that Pangi will
 * generate. The Pangi connection is not built yet: this service fixes the data contract and checks whether
 * each delegation has everything a letter needs.
 */
class PerjadinService extends DomainService
{
    public const SCHEMA = 'brudel.perjadin-mahasiswa.v1';

    /** Letters Pangi is expected to produce from one delegation. */
    public const LETTERS = [
        'surat_tugas' => 'Surat tugas delegasi',
        'sppd' => 'Surat perintah perjalanan dinas (SPPD)',
        'dispensasi' => 'Surat dispensasi kuliah per mahasiswa',
        'laporan' => 'Laporan perjalanan dinas',
    ];

    public function connected(): bool
    {
        return filled(config('services.pangi.base_url')) && filled(config('services.pangi.token'));
    }

    /**
     * Selected delegations: approved by leadership (or already back with a result), newest trip first.
     *
     * @return Collection<int, Delegation>
     */
    public function delegations(): Collection
    {
        return Delegation::with('members.user.program.department', 'competition.event', 'decider')
            ->whereIn('status', ['approved', 'completed'])->latest('starts_at')->get();
    }

    /**
     * @return list<array{label: string, ok: bool, required: bool}>
     */
    public function readiness(Delegation $delegation): array
    {
        $participants = $delegation->members->where('role', 'peserta');

        return [
            ['label' => 'Disetujui pimpinan', 'ok' => in_array($delegation->status, ['approved', 'completed'], true), 'required' => true],
            ['label' => 'Kegiatan, penyelenggara, dan lokasi', 'ok' => filled($delegation->destination_event) && filled($delegation->organizer) && filled($delegation->location), 'required' => true],
            ['label' => 'Tanggal berangkat dan kembali', 'ok' => $delegation->starts_at !== null && $delegation->ends_at !== null, 'required' => true],
            ['label' => 'Peserta lengkap dengan NIM dan prodi', 'ok' => $participants->isNotEmpty() && $participants->every(fn ($member) => $member->user->nim && $member->user->program), 'required' => true],
            ['label' => 'Dosen/pembina pendamping', 'ok' => $delegation->members->where('role', 'pendamping')->isNotEmpty(), 'required' => false],
            ['label' => 'Nomor surat tugas BRUDEL', 'ok' => filled($delegation->letter_number), 'required' => false],
        ];
    }

    public function isReady(Delegation $delegation): bool
    {
        return collect($this->readiness($delegation))->every(fn ($check) => $check['ok'] || ! $check['required']);
    }

    /**
     * The data one delegation contributes to its perjadin letters, in the shape Pangi will receive.
     *
     * @return array<string, mixed>
     */
    public function payload(Delegation $delegation): array
    {
        $timezone = config('brudel.timezone');
        $person = fn ($member) => [
            'name' => $member->user->name,
            'nim' => $member->user->nim,
            'email' => $member->user->email,
            'program' => $member->user->program?->name,
            'department' => $member->user->program?->department?->name,
        ];

        return [
            'schema' => self::SCHEMA,
            'source' => ['app' => 'BRUDEL', 'url' => url('/delegasi/'.$delegation->id), 'generated_at' => now($timezone)->toIso8601String()],
            'ready' => $this->isReady($delegation),
            'delegation' => [
                'id' => $delegation->id,
                'title' => $delegation->title,
                'status' => $delegation->status,
                'approved_by' => $delegation->decider?->name,
                'approved_at' => $delegation->decided_at?->timezone($timezone)->toIso8601String(),
                'assignment_letter_number' => $delegation->letter_number,
                'reason' => $delegation->reason,
            ],
            'activity' => [
                'name' => $delegation->destination_event,
                'organizer' => $delegation->organizer,
                'level' => config('brudel.levels')[$delegation->level] ?? null,
                'field' => $delegation->field,
                'location' => $delegation->location,
                'starts_at' => $delegation->starts_at?->timezone($timezone)->toIso8601String(),
                'ends_at' => $delegation->ends_at?->timezone($timezone)->toIso8601String(),
                'selection' => $delegation->competition ? $delegation->competition->event->name.' · '.$delegation->competition->name : null,
            ],
            'participants' => $delegation->members->where('role', 'peserta')->map($person)->values()->all(),
            'companions' => $delegation->members->where('role', 'pendamping')->map(fn ($member) => ['name' => $member->user->name, 'email' => $member->user->email])->values()->all(),
            'letters' => array_keys(self::LETTERS),
            // Documents for registering with the organizer: master data "Dokumen pendaftaran lomba".
            'registration_documents' => collect(app(MasterData::class)->active('registration_document'))
                ->map(fn ($document) => ['code' => $document->code, 'name' => $document->label, 'per_student' => (bool) $document->meta('per_student')])->all(),
        ];
    }
}
