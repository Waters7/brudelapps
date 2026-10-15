<?php

namespace Database\Seeders;

use App\Models\Assignment;
use App\Models\AwardResult;
use App\Models\Certificate;
use App\Models\Competition;
use App\Models\CompetitionResult;
use App\Models\DepartmentApproval;
use App\Models\Event;
use App\Models\Matchup;
use App\Models\Membership;
use App\Models\Participant;
use App\Models\Program;
use App\Models\Recruitment;
use App\Models\Registration;
use App\Models\RubricCriterion;
use App\Models\User;
use App\Services\Account\AccountService;
use App\Services\Achievement\AchievementService;
use App\Services\Campus\CampusService;
use App\Services\Certificate\CertificateService;
use App\Services\Club\ClubService;
use App\Services\Delegation\DelegationService;
use App\Services\Match\BracketService;
use App\Services\Match\LiveScoreService;
use App\Services\Payment\PaymentService;
use App\Services\PrivateFiles;
use App\Services\Registration\EnrollmentService;
use App\Services\Scoring\ResultService;
use App\Services\Scoring\ScoringService;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Trial data on top of DemoSeeder: 100 fictional students and records in every workflow state, so review queues,
 * dashboards, live boards, certificates and reports show realistic numbers. Every record goes through the module
 * services (audit, status history, notifications) and is labelled demo. Notifications are delivered synchronously,
 * so inboxes are filled without a queue worker. Run on an empty database:
 * `php artisan migrate:fresh --seeder=TrialSeeder`.
 */
class TrialSeeder extends Seeder
{
    public const STUDENTS = 100;

    private const MOTIVATION = 'Saya ingin mengasah kemampuan dan membawa nama prodi dalam kompetisi kampus.';

    /** @var array<string, User> demo accounts keyed by the part of the email before "@" */
    private array $demo = [];

    /** @var list<User> active, activated trial students */
    private array $students = [];

    private int $cursor = 0;

    private int $certificateNumber = 0;

    public function __construct(
        private AccountService $accounts,
        private AchievementService $achievements,
        private BracketService $brackets,
        private CampusService $campus,
        private CertificateService $certificates,
        private ClubService $clubs,
        private DelegationService $delegations,
        private EnrollmentService $enrollment,
        private LiveScoreService $live,
        private PaymentService $payments,
        private PrivateFiles $files,
        private ResultService $results,
        private ScoringService $scoring,
    ) {}

    public function run(): void
    {
        if (! app()->environment('local', 'testing') || ! config('brudel.demo')) {
            throw new \RuntimeException('TrialSeeder hanya untuk lingkungan local/testing dengan BRUDEL_DEMO=true.');
        }
        if (User::where('email', 'trial001@demo.brudel.test')->exists()) {
            throw new \RuntimeException('Data trial sudah ada. Ulangi dari awal dengan: php artisan migrate:fresh --seeder=TrialSeeder');
        }

        config(['queue.default' => 'sync']);
        $this->call(DemoSeeder::class);

        DB::transaction(function () {
            $this->demo = User::where('email', 'like', '%@demo.brudel.test')->get()->keyBy(fn (User $user) => Str::before($user->email, '@'))->all();
            $this->students();
            $this->clubMembers();
            $this->openCompetitions();
            $this->finishedEvent();
            $this->externalAchievements();
            $this->delegationStages();
        });
    }

    /**
     * Two extra programs and 100 students: 93 active, 2 deactivated with a reason, 5 not yet activated.
     */
    private function students(): void
    {
        $admin = $this->demo['admin'];
        foreach ([['D-III Kebidanan', 'KEB', 'KEB'], ['D-III Farmasi', 'FAR', 'FAR']] as [$name, $department, $code]) {
            $this->campus->saveProgram($admin, null, ['name' => $name, 'department_id' => DB::table('departments')->where('code', $department)->value('id'), 'code' => $code]);
        }

        $programs = Program::orderBy('id')->pluck('id')->all();
        $first = ['Aldo', 'Bella', 'Christian', 'Debora', 'Elia', 'Fransiska', 'Gilbert', 'Hana', 'Imanuel', 'Jessica', 'Kezia', 'Lukas',
            'Maria', 'Nikolas', 'Olivia', 'Petrus', 'Ribka', 'Samuel', 'Tirza', 'Valentino', 'Fadli', 'Nurul', 'Rahmat', 'Sri'];
        $last = ['Mandagi', 'Sumual', 'Runtuwene', 'Lasut', 'Wowor', 'Pangemanan', 'Rumengan', 'Tumbel', 'Rondonuwu', 'Tangkilisan', 'Kalalo',
            'Mamahit', 'Sondakh', 'Walangitan', 'Supit', 'Karundeng', 'Lumenta', 'Pesik', 'Tambuwun', 'Wenas', 'Paputungan', 'Mokodompit', 'Lakoro'];
        $password = Hash::make('BrudelDemo!2026');

        for ($number = 1; $number <= self::STUDENTS; $number++) {
            $activated = $number <= self::STUDENTS - 5;
            $student = User::create([
                'name' => $first[($number - 1) % count($first)].' '.$last[($number - 1) % count($last)],
                'email' => sprintf('trial%03d@demo.brudel.test', $number),
                'nim' => sprintf('TRIAL%03d', $number),
                'program_id' => $programs[($number - 1) % count($programs)],
                'password' => $activated ? $password : Hash::make(Str::random(40)),
                'email_verified_at' => $activated ? now() : null,
                'active' => true,
            ]);
            if ($number > self::STUDENTS - 7 && $activated) {
                $this->accounts->setStatus($admin, $student, false, 'Cuti akademik semester ganjil 2026 (data uji coba).');
            } elseif ($activated) {
                $this->students[] = $student;
            }
        }
    }

    /**
     * 14 applications per UKM in every review state, then divisions and a few inactive or departed members.
     */
    private function clubMembers(): void
    {
        $decisions = ['accepted', 'accepted', 'accepted', 'accepted', 'accepted', 'accepted', 'waitlisted', 'revision', 'rejected', null];
        $reasons = [
            'waitlisted' => 'Kuota latihan semester ini penuh; menunggu slot berikutnya.',
            'revision' => 'Lengkapi pengalaman organisasi atau prestasi sebelumnya.',
            'rejected' => 'Jadwal latihan bentrok dengan praktik klinik semester ini.',
        ];

        foreach (Recruitment::with('club')->orderBy('id')->get() as $recruitment) {
            $reviewer = $this->clubManager($recruitment->club_id);
            foreach ($this->take(14) as $position => $student) {
                $application = $this->enrollment->applyClub($student, $recruitment->id,
                    'Saya ingin bergabung dengan UKM '.$recruitment->club->name.' untuk berlatih, berorganisasi, dan berprestasi.');
                $decision = $decisions[$position % count($decisions)];
                if ($decision) {
                    $this->enrollment->reviewClub($reviewer, $application->id, $decision, $reasons[$decision] ?? null);
                }
            }
        }

        $divisions = ['Inti', 'Pelatihan', 'Humas', 'Logistik'];
        $trialMembers = Membership::whereIn('user_id', collect($this->students)->pluck('id'))->orderBy('id')->get();
        foreach ($trialMembers as $index => $membership) {
            $status = match ($index % 12) {
                5 => 'inactive',
                9 => 'left',
                default => 'active',
            };
            $this->clubs->updateMembership($this->clubManager($membership->club_id), $membership, [
                'status' => $status,
                'division' => $divisions[$index % count($divisions)],
                'reason' => match ($status) {
                    'inactive' => 'Cuti latihan selama praktik klinik.',
                    'left' => 'Mengundurkan diri untuk fokus tugas akhir.',
                    default => null,
                },
            ]);
        }
    }

    /**
     * The open demo event: review queues, rubric scoring in progress, team invitations, paid registrations
     * with program approval, and the selection used for delegations. Tenis Meja is left for a bracket walkthrough.
     */
    private function openCompetitions(): void
    {
        $manager = $this->demo['event'];

        $badminton = $this->competition('Badminton Tunggal');
        $plan = ['accepted', 'accepted', 'accepted', 'accepted', 'accepted', 'accepted', 'revision', 'revision', 'rejected',
            'submitted', 'submitted', 'submitted', 'submitted', 'submitted', 'submitted', 'draft', 'draft', 'draft'];
        foreach ($this->take(count($plan)) as $index => $student) {
            $registration = $this->enrollment->draft($student, $badminton->id, ['motivation' => self::MOTIVATION]);
            if ($plan[$index] !== 'draft') {
                $this->enrollment->submit($student, $registration->id);
            }
            if (! in_array($plan[$index], ['draft', 'submitted'], true)) {
                $this->enrollment->reviewRegistration($manager, $registration->id, $plan[$index], $this->reason($plan[$index]));
            }
        }

        $poster = $this->competition('Lomba Poster Kesehatan');
        $criteria = $poster->criteria()->orderBy('position')->get();
        foreach ($this->take(8) as $index => $student) {
            $registration = $this->accepted($poster, $student, [], document: true);
            $this->live->setPerformance($manager, $registration, $index < 6 ? 'done' : ($index === 6 ? 'performing' : 'waiting'), $index + 1);
            if ($index < 6) {
                $this->scoring->score($this->demo['juri'], $poster, $registration, $this->scores($criteria, $index));
            }
        }

        $paper = $this->competition('Karya Tulis Ilmiah');
        $teams = ['Sehat Mandiri' => 'accepted', 'Data Gizi' => 'accepted', 'Lingkungan Bersih' => 'submitted', 'Bidan Muda' => 'invited', 'Farmasi Cerdas' => 'draft', 'Perawat Peduli' => 'revision'];
        foreach ($teams as $name => $stage) {
            [$leader, $first, $second] = $this->take(3);
            $registration = $this->enrollment->draft($leader, $paper->id, ['motivation' => self::MOTIVATION, 'team_name' => 'Tim '.$name.' · Demo']);
            $members = $stage === 'submitted' ? [$first] : [$first, $second];
            foreach ($members as $member) {
                $this->enrollment->invite($leader, $registration->id, $member->nim);
            }
            if ($stage === 'invited') {
                continue;
            }
            $this->confirm($registration, $members);
            if ($stage === 'draft') {
                continue;
            }
            $this->enrollment->submit($leader, $registration->id);
            if ($stage !== 'submitted') {
                $this->enrollment->reviewRegistration($manager, $registration->id, $stage, $this->reason($stage));
            }
        }

        $futsal = $this->competition('Futsal Antarprodi');
        foreach (['Garuda Keperawatan', 'Gizi United', 'Kesling FC', 'Farmasi Muda'] as $name) {
            [$leader, $members] = $this->team(6);
            $this->accepted($futsal, $leader, $members, team: 'Tim '.$name.' · Demo');
        }

        $this->debate();

        $selection = $this->competition('Seleksi Baca Puisi');
        foreach ($this->take(6) as $student) {
            $this->accepted($selection, $student, []);
        }
    }

    /**
     * Debat Bahasa Inggris (paid, program approval): one team per stage of the approval and payment flow.
     */
    private function debate(): void
    {
        $admin = $this->demo['admin'];
        $manager = $this->demo['event'];
        $account = $this->campus->addAccount($admin, [
            'bank_name' => 'Bank Uji Coba',
            'account_number' => '0000000000',
            'account_holder' => 'REKENING UJI COBA - JANGAN TRANSFER',
            'notes' => 'Data trial lokal untuk mencoba alur pembayaran. Bukan rekening resmi kampus.',
        ]);

        $debate = $this->competition('Debat Bahasa Inggris');
        $nursing = Program::where('code', 'KEP')->value('id');
        $pool = collect($this->take(15));
        $sameProgram = $pool->where('program_id', $nursing)->values();
        $others = $pool->where('program_id', '!=', $nursing)->values();
        $teams = [
            'Nightingale' => ['program_review', [$sameProgram[0], $sameProgram[1]]],
            'Nutrition Voice' => ['payment_pending', [$others[0], $others[1], $sameProgram[2] ?? $others[7]]],
            'Green Health' => ['accepted', [$others[2], $others[3], $others[4]]],
            'Pharma Talk' => ['revision', [$others[5], $others[6]]],
        ];

        foreach ($teams as $name => [$stage, $roster]) {
            $leader = array_shift($roster);
            $registration = $this->enrollment->draft($leader, $debate->id, ['motivation' => self::MOTIVATION, 'team_name' => 'Tim '.$name.' · Demo']);
            foreach ($roster as $member) {
                $this->enrollment->invite($leader, $registration->id, $member->nim);
            }
            $this->confirm($registration, $roster);
            $this->enrollment->submit($leader, $registration->id);
            if ($stage === 'program_review') {
                continue;
            }

            foreach (DepartmentApproval::where('registration_id', $registration->id)->orderBy('id')->pluck('id') as $approval) {
                if ($stage === 'revision') {
                    $this->enrollment->decideDepartment($admin, $approval, 'rejected', 'Anggota tim sedang menjalani praktik klinik pada jadwal lomba.');

                    continue 2;
                }
                $this->enrollment->decideDepartment($admin, $approval, 'approved', null);
            }

            $proof = $this->payments->upload($leader, $registration->id, $this->pdf('bukti-transfer.pdf'), [
                'payment_account_id' => $account->id,
                'amount' => $registration->fresh()->fee_snapshot,
                'transferred_on' => now()->subDay()->toDateString(),
            ]);
            if ($stage === 'accepted') {
                $this->payments->verify($manager, $proof->id, 'accepted', null);
                $this->enrollment->reviewRegistration($manager, $registration->id, 'accepted', null);
            }
        }
    }

    /**
     * A finished event two months ago: complete rubric scoring by two judges, a full bracket, published results,
     * awards and certificates (including one replaced and one revoked certificate).
     */
    private function finishedEvent(): void
    {
        $admin = $this->demo['admin'];
        $manager = $this->demo['event'];
        $window = ['starts_at' => now()->subMonths(3), 'ends_at' => now()->addYear()];
        $guestJudge = User::create(['name' => 'Penilai Tamu (demo)', 'email' => 'penilai2@demo.brudel.test', 'password' => Hash::make('BrudelDemo!2026'), 'email_verified_at' => now(), 'active' => true]);
        $judges = [$this->demo['juri'], $guestJudge];

        $event = Event::create([
            'name' => 'Pekan Ilmiah dan Olahraga Semester Genap 2026 · Demo',
            'organizer' => 'Kemahasiswaan Poltekkes Kemenkes Manado (demo)',
            'description' => 'Event yang sudah selesai: hasil resmi, penghargaan, dan sertifikat (data uji coba).',
            'status' => 'approved',
            'proposed_by' => $admin->id,
            'decided_by' => $admin->id,
            'decided_at' => now()->subMonths(3),
        ]);
        Assignment::create(['user_id' => $manager->id, 'role' => 'admin_event', 'event_id' => $event->id] + $window);

        $quiz = $this->finishedCompetition($event, 'Cerdas Cermat Kesehatan', 'Akademik', 'team', 3, 'rubric');
        $video = $this->finishedCompetition($event, 'Lomba Video Edukasi Kesehatan', 'Seni', 'individual', 1, 'rubric');
        $chess = $this->finishedCompetition($event, 'Catur Cepat', 'Olahraga', 'individual', 1, 'bracket');
        $this->scoring->saveCriteria($manager, $quiz, [['name' => 'Ketepatan jawaban', 'weight' => 50], ['name' => 'Kecepatan', 'weight' => 30], ['name' => 'Kerja sama tim', 'weight' => 20]]);
        $this->scoring->saveCriteria($manager, $video, [['name' => 'Isi pesan kesehatan', 'weight' => 50], ['name' => 'Kreativitas', 'weight' => 30], ['name' => 'Kualitas teknis', 'weight' => 20]]);
        foreach ([$quiz, $video] as $competition) {
            foreach ($judges as $judge) {
                Assignment::create(['user_id' => $judge->id, 'role' => 'penilai', 'competition_id' => $competition->id] + $window);
            }
        }
        Assignment::create(['user_id' => $this->demo['operator']->id, 'role' => 'operator', 'competition_id' => $chess->id] + $window);

        foreach (['Stetoskop', 'Vitamin', 'Imun', 'Sanitasi', 'Bidan Tangguh', 'Apoteker Muda'] as $name) {
            [$leader, $members] = $this->team(3);
            $this->accepted($quiz, $leader, $members, team: 'Tim '.$name.' · Demo');
        }
        foreach ($this->take(10) as $student) {
            $this->accepted($video, $student, []);
        }
        foreach ([$quiz, $video] as $competition) {
            $criteria = $competition->criteria()->orderBy('position')->get();
            foreach ($competition->registrations()->where('status', 'accepted')->orderBy('id')->get() as $entry => $registration) {
                foreach ($judges as $judge => $user) {
                    $this->scoring->score($user, $competition, $registration, $this->scores($criteria, $entry, $judge));
                }
            }
        }

        foreach ($this->take(8) as $student) {
            $this->accepted($chess, $student, []);
        }
        $this->brackets->generate($manager, $chess);
        foreach (Matchup::where('competition_id', $chess->id)->orderBy('round')->orderBy('position')->pluck('id') as $id) {
            $match = $this->live->updateMatch($this->demo['operator'], Matchup::findOrFail($id), [
                'score_a' => 1, 'score_b' => 0, 'score_detail' => null, 'period_label' => 'Partai', 'status' => 'live', 'version' => Matchup::findOrFail($id)->version,
            ]);
            $this->brackets->recordWinner($this->demo['operator'], $match, $match->registration_a_id, 1, 0, 'Menang skakmat', $match->version);
        }

        foreach ([$quiz, $video, $chess] as $competition) {
            $this->results->publish($manager, $competition->fresh());
            $this->awardsAndCertificates($competition->fresh(), certifyEveryone: $competition->assessment_type === 'rubric');
        }

        $videoCertificates = Certificate::where('competition_id', $video->id)->orderBy('id')->get();
        $this->certificates->replace($manager, $videoCertificates->first(), $this->pdf('sertifikat-perbaikan.pdf'), 'Perbaikan penulisan nama penerima.');
        $this->certificates->revoke($manager, $videoCertificates->last(), 'Karya peserta terbukti bukan karya orisinal.');

        $held = now()->subMonths(2);
        Competition::where('event_id', $event->id)->update([
            'opens_at' => $held->copy()->subWeeks(4), 'closes_at' => $held->copy()->subWeeks(2), 'starts_at' => $held, 'ends_at' => $held->copy()->addDay(),
            'results_published_at' => $held->copy()->addDays(2),
        ]);
        $ids = [$quiz->id, $video->id, $chess->id];
        AwardResult::whereIn('competition_id', $ids)->update(['awarded_on' => $held->copy()->addDays(2)->toDateString()]);
        Certificate::whereIn('competition_id', $ids)->update(['issued_on' => $held->copy()->addDays(3)->toDateString()]);
    }

    /**
     * Ten external achievement claims: six verified, two rejected and two waiting for kemahasiswaan.
     */
    private function externalAchievements(): void
    {
        $admin = $this->demo['admin'];
        $claims = [
            ['Lomba Karya Tulis Ilmiah Kesehatan Nasional · Demo', 'Akademik', 5, 'Juara 2', 2],
            ['Pekan Olahraga Mahasiswa Provinsi · Demo', 'Olahraga', 3, 'Medali Perunggu', 0],
            ['Festival Paduan Suara Kota Manado · Demo', 'Seni', 2, 'Juara 1', 0],
            ['Olimpiade Gizi Regional Sulawesi · Demo', 'Akademik', 4, 'Juara 3', 0],
            ['Kejuaraan Futsal Antarkampus Kota · Demo', 'Olahraga', 2, 'Juara 2', 4],
            ['International Student Health Poster Competition · Demo', 'Akademik', 6, 'Honorable Mention', 0],
            ['Lomba Tari Kreasi Provinsi · Demo', 'Seni', 3, 'Penyaji Terbaik', 0],
            ['Lomba Debat Kesehatan Nasional · Demo', 'Akademik', 5, 'Finalis', 2],
            ['Lomba Esai Kesehatan Lingkungan · Demo', 'Akademik', 2, 'Juara 1', 0],
            ['Turnamen Bulutangkis Mahasiswa Regional · Demo', 'Olahraga', 4, 'Juara 3', 0],
        ];

        foreach ($claims as $index => [$title, $field, $level, $award, $teammates]) {
            [$student, $members] = $this->team($teammates + 1);
            $claim = $this->achievements->submitExternal($student, [
                'title' => $title,
                'organizer' => 'Penyelenggara contoh (demo)',
                'field' => $field,
                'level' => $level,
                'award' => $award,
                'awarded_on' => now()->subMonths(1 + $index % 5)->toDateString(),
                'team_name' => $teammates ? 'Tim '.$student->name : null,
                'members' => collect($members)->pluck('nim')->implode(' '),
            ], $this->pdf('bukti-prestasi.pdf'));
            if ($index < 6) {
                $this->achievements->verify($admin, $claim, 'verified', null);
            } elseif ($index < 8) {
                $this->achievements->verify($admin, $claim, 'rejected', 'Bukti tidak memuat nama penerima dan tanggal kegiatan.');
            }
        }

        $achievers = User::whereIn('id', DB::table('award_recipients')
            ->join('award_results', 'award_results.id', '=', 'award_recipients.award_result_id')
            ->where('award_results.status', 'verified')->pluck('award_recipients.user_id'))
            ->where('email', 'like', 'trial%')->orderBy('id')->limit(15)->get();
        foreach ($achievers as $achiever) {
            $this->achievements->setConsent($achiever, true);
        }
    }

    /**
     * Delegations at three stages: waiting for leadership, completed with a pending award, and a draft.
     */
    private function delegationStages(): void
    {
        $admin = $this->demo['admin'];
        $manager = $this->demo['event'];
        $selection = $this->competition('Seleksi Baca Puisi');
        $selected = Participant::where('competition_id', $selection->id)->orderBy('id')->value('user_id');
        $common = ['organizer' => 'Penyelenggara nasional (contoh)', 'level' => 5, 'location' => 'Jakarta', 'companions' => 'pembina@demo.brudel.test'];

        $waiting = $this->delegations->propose($manager, [
            'competition_id' => $selection->id, 'title' => 'Delegasi Festival Baca Puisi Nasional · Demo', 'destination_event' => 'Festival Baca Puisi Nasional (contoh)',
            'field' => 'Seni', 'starts_at' => now()->addMonths(2)->format('Y-m-d\TH:i'), 'reason' => 'Nilai tertinggi pada seleksi internal.', 'participants' => [$selected],
        ] + $common);
        $this->delegations->submit($manager, $waiting);

        $completed = $this->delegations->propose($admin, [
            'title' => 'Delegasi Debat Bahasa Inggris Nasional · Demo', 'destination_event' => 'Kompetisi Debat Bahasa Inggris Nasional (contoh)',
            'field' => 'Akademik', 'starts_at' => now()->subWeeks(3)->format('Y-m-d\TH:i'), 'reason' => 'Tim terbaik pada latihan rutin English Club.',
            'participants' => collect($this->take(3))->pluck('id')->all(),
        ] + $common);
        $this->delegations->submit($admin, $completed);
        $this->delegations->decide($this->demo['pimpinan'], $completed, 'approved', null);
        $this->delegations->uploadLetter($admin, $completed->fresh(), 'ST/045/BRUDEL-DEMO/2026', $this->pdf('surat-tugas.pdf'));
        $this->delegations->recordOutcome($admin, $completed->fresh(), [
            'outcome' => 'Tim mencapai semifinal dan meraih juara harapan.', 'award' => 'Juara Harapan 1', 'awarded_on' => now()->subWeeks(2)->toDateString(),
        ], $this->pdf('bukti-penghargaan.pdf'));

        $this->delegations->propose($admin, [
            'title' => 'Delegasi Pekan Olahraga Mahasiswa Wilayah · Demo', 'destination_event' => 'Pekan Olahraga Mahasiswa Wilayah (contoh)', 'organizer' => 'Penyelenggara wilayah (contoh)',
            'level' => 4, 'field' => 'Olahraga', 'location' => 'Makassar', 'starts_at' => now()->addMonths(3)->format('Y-m-d\TH:i'),
            'reason' => 'Atlet UKM Olahraga (badminton) dengan hasil latihan terbaik.', 'participants' => collect($this->take(2))->pluck('id')->all(),
        ] + $common);
    }

    /**
     * Juara 1–3 from the published ranking, and a certificate per recipient (winners, or every participant).
     */
    private function awardsAndCertificates(Competition $competition, bool $certifyEveryone): void
    {
        $manager = $this->demo['event'];
        foreach (CompetitionResult::where('competition_id', $competition->id)->orderBy('rank')->orderBy('id')->get() as $result) {
            $award = $result->rank <= 3 ? 'Juara '.$result->rank : null;
            if ($award) {
                $this->achievements->fromResult($manager, $competition, $result->registration_id, $award);
            }
            if (! $award && ! $certifyEveryone) {
                continue;
            }
            foreach (Participant::where('registration_id', $result->registration_id)->orderBy('id')->pluck('user_id') as $userId) {
                $this->certificates->issue($manager, [
                    'number' => sprintf('BRUDEL/DEMO/%d/%03d', $competition->id, ++$this->certificateNumber),
                    'user_id' => $userId,
                    'competition_id' => $competition->id,
                    'award_type' => $award ?? 'Peserta',
                    'issued_on' => now()->toDateString(),
                ], $this->pdf('sertifikat.pdf'));
            }
        }
    }

    private function finishedCompetition(Event $event, string $name, string $field, string $format, int $members, string $assessment): Competition
    {
        return Competition::create([
            'event_id' => $event->id, 'name' => $name, 'field' => $field, 'format' => $format, 'min_members' => $members, 'max_members' => $members,
            'quota' => 16, 'location' => 'Aula kampus', 'description' => $name.' pada event yang sudah selesai. Kegiatan ini adalah data demonstrasi.',
            'requirements' => 'Mahasiswa aktif.', 'assessment_type' => $assessment, 'live_score' => true, 'live_visibility' => 'public',
            'live_show_provisional' => $assessment === 'rubric', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDays(7), 'starts_at' => now()->addDays(10),
        ]);
    }

    /**
     * Draft, roster confirmation, submission and acceptance by the event admin.
     *
     * @param  list<User>  $members
     */
    private function accepted(Competition $competition, User $leader, array $members, ?string $team = null, bool $document = false): Registration
    {
        $registration = $this->enrollment->draft($leader, $competition->id, ['motivation' => self::MOTIVATION, 'team_name' => $team]);
        foreach ($members as $member) {
            $this->enrollment->invite($leader, $registration->id, $member->nim);
        }
        $this->confirm($registration, $members);
        if ($document) {
            [$path, $disk] = $this->files->storePdf($this->pdf('karya.pdf'), 'registrations/'.$registration->id);
            $registration->update(['document_path' => $path, 'document_disk' => $disk]);
        }
        $this->enrollment->submit($leader, $registration->id);
        $this->enrollment->reviewRegistration($this->demo['event'], $registration->id, 'accepted', null);

        return $registration->fresh();
    }

    /**
     * @param  list<User>  $members
     */
    private function confirm(Registration $registration, array $members): void
    {
        foreach ($members as $member) {
            $invitation = Participant::where('registration_id', $registration->id)->where('user_id', $member->id)->value('id');
            $this->enrollment->respond($member, $invitation, true);
        }
    }

    /**
     * Next students from the pool, cycling, so each module involves different people.
     *
     * @return list<User>
     */
    private function take(int $count): array
    {
        $taken = [];
        for ($i = 0; $i < $count; $i++) {
            $taken[] = $this->students[$this->cursor++ % count($this->students)];
        }

        return $taken;
    }

    /**
     * @return array{0: User, 1: list<User>} leader and the other members
     */
    private function team(int $size): array
    {
        $people = $this->take($size);

        return [array_shift($people), $people];
    }

    private function clubManager(int $clubId): User
    {
        return $this->demo['pengurus']->assigned('pengurus', $clubId) ? $this->demo['pengurus'] : $this->demo['admin'];
    }

    private function competition(string $name): Competition
    {
        return Competition::where('name', $name)->firstOrFail();
    }

    private function reason(string $decision): ?string
    {
        return match ($decision) {
            'revision' => 'Lengkapi motivasi dengan pengalaman bertanding atau berkarya sebelumnya.',
            'rejected' => 'Berkas pendaftaran tidak sesuai ketentuan nomor lomba.',
            default => null,
        };
    }

    /**
     * Deterministic scores 60–98 so rankings differ between entries and judges.
     *
     * @param  Collection<int, RubricCriterion>  $criteria
     * @return array<int, array{score: int}>
     */
    private function scores(Collection $criteria, int $entry, int $judge = 0): array
    {
        return $criteria->values()->mapWithKeys(fn (RubricCriterion $criterion, int $index) => [
            $criterion->id => ['score' => 60 + (($entry * 11 + $index * 7 + $judge * 5) % 39)],
        ])->all();
    }

    private function pdf(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n% Dokumen uji coba BRUDEL - bukan dokumen resmi\n%%EOF\n");
    }
}
