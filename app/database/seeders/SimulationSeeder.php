<?php

namespace Database\Seeders;

use App\Models\Assignment;
use App\Models\Club;
use App\Models\Competition;
use App\Models\DepartmentApproval;
use App\Models\Event;
use App\Models\Matchup;
use App\Models\Participant;
use App\Models\PaymentAccount;
use App\Models\PaymentProof;
use App\Models\Registration;
use App\Models\StatusHistory;
use App\Models\User;
use App\Services\Achievement\AchievementService;
use App\Services\Certificate\CertificateService;
use App\Services\Event\CompetitionAccountService;
use App\Services\Match\BracketService;
use App\Services\Match\LiveScoreService;
use App\Services\Scoring\ResultService;
use App\Services\Scoring\ScoringService;
use App\Services\Wita;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Fictional competitions with an example of every process, from event proposals through registration, jurusan approval,
 * payment, judging, brackets and live score to published results, awards and a certificate. It uses students already in
 * the database, creates no student account, and labels everything "Simulasi". Seeding again changes nothing.
 */
class SimulationSeeder extends Seeder
{
    public const EVENT = 'Simulasi Lomba Mahasiswa · Fiktif';

    public const SINGLE = 'Simulasi Badminton Tunggal';

    public const TEAM = 'Simulasi Futsal Grup';

    public const FLOW = 'Simulasi Alur Pendaftaran';

    public const RUBRIC = 'Simulasi Poster Kesehatan';

    public const SINGING = 'Simulasi Lomba Menyanyi Solo';

    public const FINISHED = 'Simulasi Tenis Meja Selesai';

    private const FEE = 25000;

    private const SINGLE_PLAYERS = 8;

    private const TEAM_SIZE = 3;

    private const TEAM_NAMES = ['Tim Elang', 'Tim Garuda', 'Tim Rajawali', 'Tim Merpati'];

    /**
     * One team per step of the registration flow: name, status, payment, proof, jurusan decision, reason.
     */
    private const FLOW_TEAMS = [
        ['Tim Draf', 'draft', 'not_required', null, null, null],
        ['Tim Menunggu Jurusan', 'program_review', 'unpaid', null, 'pending', null],
        ['Tim Revisi Jurusan', 'revision', 'unpaid', null, 'rejected', 'Jurusan: lengkapi surat izin pembimbing.'],
        ['Tim Belum Bayar', 'submitted', 'unpaid', null, 'approved', null],
        ['Tim Bukti Diperiksa', 'submitted', 'pending', 'pending', 'approved', null],
        ['Tim Bukti Ditolak', 'submitted', 'rejected', 'rejected', 'approved', null],
        ['Tim Siap Ditinjau', 'submitted', 'paid', 'accepted', 'approved', null],
        ['Tim Diterima', 'accepted', 'paid', 'accepted', 'approved', null],
        ['Tim Ditolak', 'rejected', 'paid', 'accepted', 'approved', 'Anggota tim tidak memenuhi syarat lomba.'],
        ['Tim Dibatalkan', 'cancelled', 'not_required', null, null, null],
    ];

    private const PROPOSALS = [
        ['Simulasi Pengajuan Menunggu Keputusan', 'submitted', null],
        ['Simulasi Pengajuan Perlu Revisi', 'revision', 'Lengkapi jadwal dan rincian anggaran.'],
        ['Simulasi Pengajuan Ditolak', 'rejected', 'Waktunya bertabrakan dengan ujian akhir.'],
    ];

    private const MINIMUM_STUDENTS = 20;

    private const SINGERS = 5;

    /** @var list<array{name: string, weight: int}> */
    private const SINGING_CRITERIA = [
        ['name' => 'Teknik vokal', 'weight' => 35],
        ['name' => 'Intonasi dan ritme', 'weight' => 25],
        ['name' => 'Penghayatan lagu', 'weight' => 25],
        ['name' => 'Penampilan panggung', 'weight' => 15],
    ];

    private const PROOF = 'simulasi/bukti-transfer.pdf';

    private const PDF = "%PDF-1.4\n%Dokumen simulasi BRUDEL\n%%EOF\n";

    private User $admin;

    public function __construct(
        private BracketService $brackets,
        private LiveScoreService $live,
        private CompetitionAccountService $accounts,
        private ScoringService $scoring,
        private ResultService $results,
        private AchievementService $achievements,
        private CertificateService $certificates,
    ) {}

    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new \RuntimeException('SimulationSeeder hanya untuk lingkungan local/testing: mahasiswa nyata akan tampil di lomba fiktif.');
        }
        $admin = User::whereHas('currentAssignments', fn ($q) => $q->where('role', 'kemahasiswaan'))->orderBy('id')->first();
        if (! $admin) {
            throw new \RuntimeException('Akun kemahasiswaan direktorat tidak ditemukan.');
        }
        $this->admin = $admin;
        $students = User::whereNotNull('nim')->where('active', true)->whereHas('program', fn ($q) => $q->whereNotNull('department_id'))
            ->orderBy('id')->limit(self::MINIMUM_STUDENTS)->get();
        if ($students->count() < self::MINIMUM_STUDENTS) {
            throw new \RuntimeException('Butuh minimal '.self::MINIMUM_STUDENTS.' mahasiswa aktif, baru ada '.$students->count().'. Impor mahasiswa terlebih dahulu.');
        }

        $event = Event::firstOrCreate(['name' => self::EVENT], [
            'organizer' => 'Simulasi Kemahasiswaan (fiktif)', 'status' => 'approved', 'proposed_by' => $admin->id, 'responsible_id' => $admin->id, 'decided_by' => $admin->id, 'decided_at' => now(),
        ]);

        $this->proposals();
        $single = $this->singleBracket($event, $students);
        $team = $this->teamBracket($event, $students);
        $flow = $this->registrationFlow($event, $students);
        $rubric = $this->judging($event, $students);
        $singing = $this->singing($event, $students);
        $finished = $this->finishedCompetition($event, $students);

        // One competition per access level, so each can be tried while signed in as a student or as a guest.
        $single->update(['live_visibility' => 'public']);
        $rubric->update(['results_locked_at' => $rubric->results_locked_at ?? now(), 'results_locked_by' => $rubric->results_locked_by ?? $admin->id]);

        $this->command?->table(['Proses', 'Buka di'], [
            ['Pengajuan event: menunggu, revisi, ditolak', '/kelola/event'],
            ['Pendaftaran tiap status', '/kelola/lomba/'.$flow->id],
            ['Persetujuan jurusan', '/jurusan?jurusan='.DepartmentApproval::where('status', 'pending')->whereIn('registration_id', $flow->registrations()->select('id'))->value('department_id')],
            ['Verifikasi pembayaran', '/pembayaran'],
            ['Tinjau pendaftaran', '/tinjauan'],
            ['Penilaian juri belum lengkap', '/kelola/lomba/'.$rubric->id],
            ['Penilaian bernyanyi lengkap, siap disahkan', '/kelola/lomba/'.$singing->id],
            ['Bagan tunggal dan skor langsung', '/live/'.$single->id],
            ['Bagan grup', '/live/'.$team->id],
            ['Panel operator', '/operator?lomba='.$single->id],
            ['Hasil resmi', '/live/'.$finished->id],
            ['Akses umum, pengguna masuk, terkunci', '/live/'.$single->id.', /live/'.$team->id.', /live/'.$rubric->id],
            ['Prestasi', '/kelola/prestasi'],
            ['Sertifikat', '/kelola/sertifikat?lomba='.$finished->id],
        ]);
    }

    private function proposals(): void
    {
        $club = Club::where('category', 'ukm')->where('active', true)->orderBy('id')->first();
        $proposer = User::whereHas('currentAssignments', fn ($q) => $q->where('role', 'pengurus'))->orderBy('id')->first() ?? $this->admin;
        foreach (self::PROPOSALS as [$name, $status, $reason]) {
            $event = Event::firstOrCreate(['name' => $name], [
                'organizer' => $club?->name ?? 'Organisasi simulasi', 'club_id' => $club?->id, 'pathway' => 'internal', 'field' => 'Olahraga', 'status' => $status, 'reason' => $reason,
                'description' => 'Pengajuan fiktif untuk simulasi keputusan event.', 'proposed_by' => $proposer->id, 'responsible_id' => $proposer->id, 'submitted_at' => now()->subDays(2),
                'decided_by' => $reason ? $this->admin->id : null, 'decided_at' => $reason ? now()->subDay() : null,
            ]);
            if (! $event->wasRecentlyCreated) {
                continue;
            }
            $this->competition($event, $name.' Nomor Uji', 'individual', 1, 1, 'Lapangan (fiktif)', ['assessment_type' => 'none', 'live_score' => false]);
            $this->history($event, null, 'submitted', $proposer);
            if ($reason) {
                $this->history($event, 'submitted', $status, $this->admin, $reason);
            }
        }
    }

    /**
     * @param  Collection<int, User>  $students
     */
    private function singleBracket(Event $event, Collection $students): Competition
    {
        $competition = $this->competition($event, self::SINGLE, 'individual', 1, 1, 'Gedung olahraga (fiktif)');
        if (! $competition->registrations()->exists()) {
            foreach ($students->take(self::SINGLE_PLAYERS)->values() as $index => $student) {
                $this->register($competition, $student, new Collection([$student]), null, $index);
            }
            $this->brackets->generate($this->admin, $competition);
            $this->playRound($competition, 1, self::SINGLE_PLAYERS / 2, 21, 15);
        }
        if (! Matchup::where('competition_id', $competition->id)->where('status', 'live')->exists()) {
            $semifinal = Matchup::where('competition_id', $competition->id)->where('round', 2)->where('status', 'scheduled')->orderBy('position')->first();
            if ($semifinal) {
                $this->live->updateMatch($this->admin, $semifinal, ['score_a' => 11, 'score_b' => 9, 'period_label' => 'Gim 1', 'status' => 'live', 'version' => $semifinal->version]);
            }
        }

        return $competition;
    }

    /**
     * @param  Collection<int, User>  $students
     */
    private function teamBracket(Event $event, Collection $students): Competition
    {
        $competition = $this->competition($event, self::TEAM, 'team', self::TEAM_SIZE, self::TEAM_SIZE, 'Lapangan futsal (fiktif)');
        if (! $competition->registrations()->exists()) {
            foreach (self::TEAM_NAMES as $index => $name) {
                $members = $students->slice($index * self::TEAM_SIZE, self::TEAM_SIZE)->values();
                $this->register($competition, $members->first(), $members, $name, $index);
            }
            $this->brackets->generate($this->admin, $competition);
            $this->playRound($competition, 1, 1, 3, 1);
        }

        return $competition;
    }

    /**
     * @param  Collection<int, User>  $students
     */
    private function registrationFlow(Event $event, Collection $students): Competition
    {
        $competition = $this->competition($event, self::FLOW, 'team', 2, 3, 'Aula kampus (fiktif)', [
            'assessment_type' => 'none', 'live_score' => false, 'fee' => self::FEE, 'fee_basis' => 'team', 'fee_notes' => 'Biaya fiktif untuk simulasi. Bukan tagihan nyata.',
            'requires_program_approval' => true, 'quota' => 24, 'opens_at' => now()->subDays(5), 'closes_at' => now()->addDays(14), 'starts_at' => now()->addDays(30),
        ]);
        if ($competition->registrations()->exists()) {
            return $competition;
        }
        Storage::disk(config('brudel.document_disk'))->put(self::PROOF, self::PDF);
        $account = PaymentAccount::firstOrCreate(['account_number' => '0000000000'], ['bank_name' => 'Bank Simulasi (fiktif)', 'account_holder' => 'Rekening fiktif simulasi', 'active' => true]);

        foreach (self::FLOW_TEAMS as $index => [$name, $status, $payment, $proof, $approval, $reason]) {
            $members = $students->slice($index * 2, 2)->values();
            $leader = $members->first();
            $decided = in_array($status, ['accepted', 'rejected'], true);
            $registration = Registration::create([
                'competition_id' => $competition->id, 'user_id' => $leader->id, 'team_name' => $name, 'motivation' => 'Pendaftaran fiktif untuk simulasi alur pendaftaran.',
                'status' => $status, 'payment_status' => $payment, 'fee_snapshot' => $payment === 'not_required' ? 0 : self::FEE, 'reason' => $reason,
                'submitted_at' => in_array($status, ['draft', 'cancelled'], true) ? null : now()->subDays(3)->addMinutes($index),
                'reviewed_by' => $decided ? $this->admin->id : null, 'reviewed_at' => $decided ? now()->subDay() : null,
            ]);
            foreach ($members as $member) {
                Participant::create(['registration_id' => $registration->id, 'competition_id' => $competition->id, 'user_id' => $member->id,
                    'status' => $status === 'draft' && $member->isNot($leader) ? 'invited' : 'confirmed']);
            }
            if ($approval) {
                foreach ($members->map->departmentId()->unique() as $department) {
                    DepartmentApproval::create([
                        'registration_id' => $registration->id, 'department_id' => $department, 'status' => $approval, 'reason' => $approval === 'rejected' ? 'Lengkapi surat izin pembimbing.' : null,
                        'decided_by' => $approval === 'pending' ? null : $this->admin->id, 'decided_at' => $approval === 'pending' ? null : now()->subDays(2),
                    ]);
                }
            }
            if ($proof) {
                PaymentProof::create([
                    'registration_id' => $registration->id, 'uploaded_by' => $leader->id, 'payment_account_id' => $account->id, 'amount' => self::FEE, 'transferred_on' => now()->subDays(2)->toDateString(),
                    'path' => self::PROOF, 'disk' => config('brudel.document_disk'), 'status' => $proof, 'reason' => $proof === 'rejected' ? 'Nominal pada bukti tidak terbaca.' : null,
                    'verified_by' => $proof === 'pending' ? null : $this->admin->id, 'verified_at' => $proof === 'pending' ? null : now()->subDay(),
                ]);
            }
            $this->history($registration, null, $status, $leader, $reason);
        }

        return $competition;
    }

    /**
     * @param  Collection<int, User>  $students
     */
    private function judging(Event $event, Collection $students): Competition
    {
        $competition = $this->competition($event, self::RUBRIC, 'individual', 1, 1, 'Aula kampus (fiktif)', ['assessment_type' => 'rubric', 'judge_count' => 2, 'live_show_provisional' => true]);
        if ($competition->registrations()->exists()) {
            return $competition;
        }
        $entries = $students->take(4)->values()->map(fn (User $student, int $index) => $this->register($competition, $student, new Collection([$student]), null, $index));
        $this->scoring->saveCriteria($this->admin, $competition, [
            ['name' => 'Kesesuaian tema', 'weight' => 40], ['name' => 'Kreativitas', 'weight' => 35], ['name' => 'Kerapian', 'weight' => 25],
        ]);
        $criteria = $competition->criteria()->pluck('id');
        $judges = User::whereNull('nim')->where('active', true)->whereKeyNot($this->admin->id)->whereDoesntHave('employee')->orderBy('id')->limit(2)->get();
        foreach ($judges as $order => $judge) {
            Assignment::create(['user_id' => $judge->id, 'role' => 'penilai', 'competition_id' => $competition->id, 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth(), 'created_by' => $this->admin->id]);
            // The second judge stops halfway, so the operator sees a score sheet that is still being filled.
            foreach ($entries->take($order === 0 ? 4 : 2) as $index => $entry) {
                $this->scoring->score(User::find($judge->id), $competition, $entry, $criteria->mapWithKeys(fn (int $id) => [$id => ['score' => 90 - $index * 5 - $order]])->all());
            }
        }

        return $competition;
    }

    /**
     * A singing contest judged in full by its generated juri accounts, so the ranking is complete and ready to publish.
     *
     * @param  Collection<int, User>  $students
     */
    private function singing(Event $event, Collection $students): Competition
    {
        $competition = $this->competition($event, self::SINGING, 'individual', 1, 1, 'Aula kampus (fiktif)', ['field' => 'Seni', 'assessment_type' => 'rubric', 'judge_count' => 2, 'live_show_provisional' => true]);
        if ($competition->registrations()->exists()) {
            return $competition;
        }
        $entries = $students->take(self::SINGERS)->values()->map(fn (User $student, int $index) => $this->register($competition, $student, new Collection([$student]), null, $index));
        $this->scoring->saveCriteria($this->admin, $competition, self::SINGING_CRITERIA);
        $this->accounts->provision($competition, $this->admin);
        $criteria = $competition->criteria()->pluck('id')->values();
        foreach ($competition->accounts()->where('role', 'penilai')->get() as $order => $account) {
            foreach ($entries as $index => $entry) {
                $this->scoring->score($account->user, $competition, $entry, $criteria->mapWithKeys(fn (int $id, int $position) => [$id => ['score' => 92 - $index * 5 - $order * 2 + $position]])->all());
            }
        }

        return $competition;
    }

    /**
     * A bracket played to the end: results published, awards recorded, and a certificate issued to the champion.
     *
     * @param  Collection<int, User>  $students
     */
    private function finishedCompetition(Event $event, Collection $students): Competition
    {
        $competition = $this->competition($event, self::FINISHED, 'individual', 1, 1, 'Gedung olahraga (fiktif)', ['starts_at' => now()->subDays(2)]);
        if ($competition->registrations()->exists()) {
            return $competition;
        }
        foreach ($students->take(4)->values() as $index => $student) {
            $this->register($competition, $student, new Collection([$student]), null, $index);
        }
        $this->brackets->generate($this->admin, $competition);
        $this->playRound($competition, 1, 2, 11, 7);
        $this->playRound($competition, 2, 1, 11, 9);
        $this->results->publish($this->admin, $competition);

        $competition->refresh();
        $ranked = $competition->results()->orderBy('rank')->get();
        foreach ($ranked->take(2)->values() as $place => $result) {
            $this->achievements->fromResult($this->admin, $competition, $result->registration_id, 'Juara '.($place + 1));
        }
        $this->certificates->issue($this->admin, [
            'number' => 'SIMULASI/'.$competition->id.'/001', 'user_id' => $ranked->first()->registration->user_id, 'competition_id' => $competition->id, 'award_type' => 'Juara 1', 'issued_on' => Wita::input(now()),
        ], UploadedFile::fake()->createWithContent('sertifikat-simulasi.pdf', self::PDF));

        return $competition;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function competition(Event $event, string $name, string $format, int $min, int $max, string $location, array $overrides = []): Competition
    {
        return Competition::firstOrCreate(['event_id' => $event->id, 'name' => $name], $overrides + [
            'field' => 'Olahraga', 'format' => $format, 'min_members' => $min, 'max_members' => $max, 'quota' => 16, 'location' => $location,
            'description' => 'Lomba fiktif untuk simulasi proses lomba. Bukan kegiatan nyata.',
            'requirements' => 'Data simulasi, tidak ada pendaftaran sungguhan.',
            'assessment_type' => 'bracket', 'live_score' => true, 'live_visibility' => 'auth',
            'opens_at' => now()->subDays(10), 'closes_at' => now()->subDay(), 'starts_at' => now()->addHour(),
        ]);
    }

    /**
     * @param  Collection<int, User>  $members
     */
    private function register(Competition $competition, User $leader, Collection $members, ?string $teamName, int $index): Registration
    {
        $registration = Registration::create([
            'competition_id' => $competition->id, 'user_id' => $leader->id, 'team_name' => $teamName,
            'motivation' => 'Pendaftaran fiktif untuk simulasi proses lomba.', 'status' => 'accepted',
            'submitted_at' => now()->subDays(5)->addMinutes($index), 'seed' => $index < 2 ? $index + 1 : null,
        ]);
        foreach ($members as $member) {
            Participant::create(['registration_id' => $registration->id, 'competition_id' => $competition->id, 'user_id' => $member->id, 'status' => 'confirmed']);
        }

        return $registration;
    }

    private function playRound(Competition $competition, int $round, int $matches, int $winningScore, int $losingScore): void
    {
        $games = Matchup::where('competition_id', $competition->id)->where('round', $round)->where('status', 'scheduled')->orderBy('position')->limit($matches)->get();
        foreach ($games as $match) {
            $this->brackets->recordWinner($this->admin, $match, $match->registration_a_id, $winningScore, $losingScore, null, $match->version);
        }
    }

    private function history(Event|Registration $subject, ?string $from, string $to, User $actor, ?string $reason = null): void
    {
        StatusHistory::create(['subject_type' => $subject->getTable(), 'subject_id' => $subject->getKey(), 'from_status' => $from, 'to_status' => $to, 'reason' => $reason, 'user_id' => $actor->id]);
    }
}
