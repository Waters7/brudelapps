<?php

namespace Database\Seeders;

use App\Models\Competition;
use App\Models\Event;
use App\Models\Matchup;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\User;
use App\Services\Match\BracketService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;

/**
 * Fictional competitions for rehearsing brackets with students already in the database: one individual and one team
 * single-elimination bracket. No account is created and everything is labelled "Simulasi". Seeding again changes nothing.
 */
class SimulationSeeder extends Seeder
{
    public const EVENT = 'Simulasi Lomba Mahasiswa · Fiktif';

    public const SINGLE = 'Simulasi Badminton Tunggal';

    public const TEAM = 'Simulasi Futsal Grup';

    private const SINGLE_PLAYERS = 8;

    private const TEAM_SIZE = 3;

    private const TEAM_NAMES = ['Tim Elang', 'Tim Garuda', 'Tim Rajawali', 'Tim Merpati'];

    public function run(BracketService $brackets): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new \RuntimeException('SimulationSeeder hanya untuk lingkungan local/testing: mahasiswa nyata akan tampil di lomba fiktif.');
        }
        $admin = User::whereHas('currentAssignments', fn ($q) => $q->where('role', 'kemahasiswaan'))->orderBy('id')->first();
        if (! $admin) {
            throw new \RuntimeException('Akun kemahasiswaan direktorat tidak ditemukan.');
        }
        $needed = max(self::SINGLE_PLAYERS, count(self::TEAM_NAMES) * self::TEAM_SIZE);
        $students = User::whereNotNull('nim')->where('active', true)->orderBy('id')->limit($needed)->get();
        if ($students->count() < $needed) {
            throw new \RuntimeException('Butuh minimal '.$needed.' mahasiswa aktif, baru ada '.$students->count().'. Impor mahasiswa terlebih dahulu.');
        }

        $event = Event::firstOrCreate(['name' => self::EVENT], [
            'organizer' => 'Simulasi Kemahasiswaan (fiktif)', 'status' => 'approved', 'proposed_by' => $admin->id, 'decided_by' => $admin->id, 'decided_at' => now(),
        ]);

        $single = $this->competition($event, self::SINGLE, 'individual', 1, 1, 'Gedung olahraga (fiktif)');
        if (! $single->registrations()->exists()) {
            foreach ($students->take(self::SINGLE_PLAYERS)->values() as $index => $student) {
                $this->register($single, $student, new Collection([$student]), null, $index);
            }
            $this->play($brackets, $admin, $single, finished: self::SINGLE_PLAYERS / 2, winningScore: 21, losingScore: 15);
        }

        $team = $this->competition($event, self::TEAM, 'team', self::TEAM_SIZE, self::TEAM_SIZE, 'Lapangan futsal (fiktif)');
        if (! $team->registrations()->exists()) {
            foreach (self::TEAM_NAMES as $index => $name) {
                $members = $students->slice($index * self::TEAM_SIZE, self::TEAM_SIZE)->values();
                $this->register($team, $members->first(), $members, $name, $index);
            }
            $this->play($brackets, $admin, $team, finished: 1, winningScore: 3, losingScore: 1);
        }
    }

    private function competition(Event $event, string $name, string $format, int $min, int $max, string $location): Competition
    {
        return Competition::firstOrCreate(['event_id' => $event->id, 'name' => $name], [
            'field' => 'Olahraga', 'format' => $format, 'min_members' => $min, 'max_members' => $max, 'quota' => 16, 'location' => $location, 'level' => 'Internal kampus',
            'description' => 'Lomba fiktif untuk simulasi bagan sistem gugur. Bukan kegiatan nyata.',
            'requirements' => 'Data simulasi, tidak ada pendaftaran sungguhan.',
            'assessment_type' => 'bracket', 'live_score' => true, 'live_visibility' => 'auth',
            'opens_at' => now()->subDays(10), 'closes_at' => now()->subDay(), 'starts_at' => now()->addHour(),
        ]);
    }

    /**
     * @param  Collection<int, User>  $members
     */
    private function register(Competition $competition, User $leader, Collection $members, ?string $teamName, int $index): void
    {
        $registration = Registration::create([
            'competition_id' => $competition->id, 'user_id' => $leader->id, 'team_name' => $teamName,
            'motivation' => 'Pendaftaran fiktif untuk simulasi bagan sistem gugur.', 'status' => 'accepted',
            'submitted_at' => now()->subDays(5)->addMinutes($index), 'seed' => $index < 2 ? $index + 1 : null,
        ]);
        foreach ($members as $member) {
            Participant::create(['registration_id' => $registration->id, 'competition_id' => $competition->id, 'user_id' => $member->id, 'status' => 'confirmed']);
        }
    }

    /**
     * Builds the bracket with the real service and decides the first matches, leaving the rest for the operator.
     */
    private function play(BracketService $brackets, User $admin, Competition $competition, int $finished, int $winningScore, int $losingScore): void
    {
        $brackets->generate($admin, $competition);
        $openers = Matchup::where('competition_id', $competition->id)->where('round', 1)->orderBy('position')->limit($finished)->get();
        foreach ($openers as $match) {
            $brackets->recordWinner($admin, $match, $match->registration_a_id, $winningScore, $losingScore, null, $match->version);
        }
    }
}
