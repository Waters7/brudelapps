<?php

namespace Database\Seeders;

use App\Models\Assignment;
use App\Models\AwardRecipient;
use App\Models\AwardResult;
use App\Models\Club;
use App\Models\ClubApplication;
use App\Models\Competition;
use App\Models\Event;
use App\Models\Membership;
use App\Models\Participant;
use App\Models\Recruitment;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * Demonstration data only. Every record is labelled "Demo"; no bank account or payment is created.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing') || ! config('brudel.demo')) {
            throw new \RuntimeException('DemoSeeder hanya untuk lingkungan local/testing dengan BRUDEL_DEMO=true.');
        }

        DB::transaction(function () {
            // Keyed by code, not by explicit id: explicit ids would leave the PostgreSQL sequence behind.
            foreach ([['D-III Keperawatan', 'Keperawatan', 'KEP'], ['D-IV Gizi dan Dietetika', 'Gizi', 'GIZ'], ['D-III Kesehatan Lingkungan', 'Kesehatan Lingkungan', 'KESLING']] as [$name, $department, $code]) {
                DB::table('programs')->updateOrInsert(['code' => $code], ['name' => $name, 'department' => $department]);
            }
            $programs = DB::table('programs')->pluck('id', 'code');

            $people = [
                ['Bintang Mandagi', 'mahasiswa', 'DEMO001', 'KEP'], ['Michelle Sumual', 'anggota', 'DEMO002', 'GIZ'], ['Andre Runtuwene', 'pengurus', 'DEMO003', 'KEP'],
                ['Pengelola Kemahasiswaan', 'admin', null, null], ['Admin Pekan Prestasi', 'event', null, null], ['Koordinator Prodi Keperawatan', 'prodi', null, null],
                ['Juri Poster Kesehatan', 'juri', null, null], ['Operator Pertandingan', 'operator', null, null], ['Wakil Direktur III', 'pimpinan', null, null],
                ['Pelatih Badminton', 'pembina', null, null], ['Rifky Lasut', 'atlet1', 'DEMO004', 'KESLING'], ['Grace Wowor', 'atlet2', 'DEMO005', 'GIZ'],
                ['Yosua Pangemanan', 'atlet3', 'DEMO006', 'KEP'], ['Natalia Rumengan', 'atlet4', 'DEMO007', 'KESLING'], ['Kevin Tumbel', 'atlet5', 'DEMO008', 'GIZ'],
            ];
            $users = [];
            foreach ($people as [$name, $email, $nim, $program]) {
                $users[$email] = User::firstOrCreate(['email' => $email.'@demo.brudel.test'], ['name' => $name, 'nim' => $nim, 'program_id' => $program ? $programs[$program] : null, 'password' => Hash::make('BrudelDemo!2026'), 'email_verified_at' => now(), 'active' => true]);
            }
            User::firstOrCreate(['email' => 'baru@demo.brudel.test'], ['name' => 'Mahasiswa Belum Aktivasi', 'nim' => 'DEMO009', 'program_id' => $programs['KEP'], 'password' => Hash::make(str()->random(40)), 'active' => true]);
            $users['mahasiswa']->update(['publication_consent' => true, 'public_slug' => 'bintang-mandagi-demo']);

            $clubs = [
                ['Badminton', 'Olahraga', 'Temukan ritme permainanmu. Berlatih bersama, bangun sportivitas, dan bersiap menuju podium.', 'racket'],
                ['Paduan Suara', 'Seni', 'Satukan suara, ciptakan harmoni. Ruang berkarya untuk kamu yang ingin tumbuh bersama musik.', 'music'],
                ['Karya Tulis Ilmiah', 'Akademik', 'Dari rasa ingin tahu menjadi karya bermakna. Kembangkan ide, riset, dan kemampuan presentasimu.', 'book'],
                ['Futsal', 'Olahraga', 'Bangun kerja sama dan ketangkasan di lapangan. Terbuka untuk pemain dari semua prodi.', 'ball'],
                ['Seni Tari', 'Seni', 'Ekspresikan diri melalui tari tradisional dan kreasi. Rayakan keberagaman budaya bersama.', 'spark'],
                ['English Club', 'Akademik', 'Asah debat, public speaking, dan bahasa Inggris bersama teman lintas prodi.', 'globe'],
            ];
            $all = [];
            foreach ($clubs as $i => [$name, $field, $description, $symbol]) {
                $club = Club::firstOrCreate(['name' => $name], ['field' => $field, 'description' => $description, 'mentor' => 'Pembina '.$name.' (demo)', 'contact' => 'Kontak resmi belum dikonfigurasi', 'symbol' => $symbol, 'activities' => 'Latihan rutin mingguan (jadwal demo).']);
                $all[] = $club;
                Recruitment::firstOrCreate(['club_id' => $club->id, 'name' => 'Rekrutmen semester ganjil 2026 · Demo'], ['opens_at' => now()->subDays(7), 'closes_at' => now()->addDays(14 + $i), 'quota' => 30, 'requirements' => 'Mahasiswa aktif Poltekkes Kemenkes Manado. Bersedia mengikuti latihan dan kegiatan UKM. Pendaftaran gratis.']);
            }

            $window = ['starts_at' => now()->subMonth(), 'ends_at' => now()->addYear()];
            Assignment::firstOrCreate(['user_id' => $users['pengurus']->id, 'role' => 'pengurus', 'club_id' => $all[0]->id], $window + ['position' => 'Ketua', 'period' => '2026/2027']);
            Assignment::firstOrCreate(['user_id' => $users['pembina']->id, 'role' => 'pembina', 'club_id' => $all[0]->id], $window);
            Assignment::firstOrCreate(['user_id' => $users['admin']->id, 'role' => 'kemahasiswaan'], $window);
            Assignment::firstOrCreate(['user_id' => $users['pimpinan']->id, 'role' => 'pimpinan'], $window);
            Assignment::firstOrCreate(['user_id' => $users['prodi']->id, 'role' => 'prodi', 'program_id' => $programs['KEP']], $window);

            $event = Event::firstOrCreate(['name' => 'Pekan Prestasi Mahasiswa 2026 · Demo'], ['organizer' => 'Kemahasiswaan Poltekkes Kemenkes Manado (demo)', 'status' => 'approved', 'proposed_by' => $users['admin']->id, 'decided_by' => $users['admin']->id, 'decided_at' => now()]);
            Assignment::firstOrCreate(['user_id' => $users['event']->id, 'role' => 'admin_event', 'event_id' => $event->id], $window);

            $competitions = [
                ['Badminton Tunggal', 'Olahraga', 'individual', 1, 1, 'Lapangan kampus', false, 'none', false, 0, false],
                ['Lomba Poster Kesehatan', 'Seni', 'individual', 1, 1, 'Aula kampus', true, 'rubric', true, 0, false],
                ['Karya Tulis Ilmiah', 'Akademik', 'team', 2, 3, 'Ruang seminar', false, 'none', false, 0, false],
                ['Futsal Antarprodi', 'Olahraga', 'team', 5, 8, 'Lapangan futsal', false, 'none', false, 0, false],
                ['Tenis Meja Tunggal', 'Olahraga', 'individual', 1, 1, 'Gedung olahraga', false, 'bracket', true, 0, false],
                ['Debat Bahasa Inggris', 'Akademik', 'team', 2, 3, 'Ruang kuliah 2', false, 'rubric', false, 25000, true],
            ];
            $made = [];
            foreach ($competitions as $i => [$name, $field, $format, $min, $max, $location, $document, $assessment, $live, $fee, $programApproval]) {
                $made[$name] = Competition::firstOrCreate(['event_id' => $event->id, 'name' => $name], [
                    'field' => $field, 'format' => $format, 'min_members' => $min, 'max_members' => $max, 'quota' => 24, 'location' => $location,
                    'description' => 'Jadikan potensi sebagai prestasi. Ikuti '.$name.' dan tunjukkan kemampuan terbaikmu bersama mahasiswa lintas prodi. Kegiatan ini adalah data demonstrasi.',
                    'requirements' => 'Mahasiswa aktif. '.($document ? 'Unggah karya poster dalam PDF maksimal 5 MB.' : 'Lengkapi motivasi dan konfirmasi semua anggota jika berkelompok.'),
                    'fee' => $fee, 'fee_basis' => 'team', 'fee_notes' => $fee ? 'Biaya demo untuk mencoba alur pembayaran manual. Bukan tagihan nyata.' : null,
                    'requires_document' => $document, 'requires_program_approval' => $programApproval, 'assessment_type' => $assessment,
                    'live_score' => $live, 'live_visibility' => $name === 'Tenis Meja Tunggal' ? 'public' : 'auth', 'live_show_provisional' => $assessment === 'rubric',
                    'opens_at' => now()->subDays(3), 'closes_at' => now()->addDays(5 + $i * 3), 'starts_at' => now()->addDays(20 + $i),
                ]);
            }

            Assignment::firstOrCreate(['user_id' => $users['juri']->id, 'role' => 'penilai', 'competition_id' => $made['Lomba Poster Kesehatan']->id], $window);
            Assignment::firstOrCreate(['user_id' => $users['operator']->id, 'role' => 'operator', 'competition_id' => $made['Tenis Meja Tunggal']->id], $window);
            if (! $made['Lomba Poster Kesehatan']->criteria()->exists()) {
                foreach ([['Kesesuaian pesan kesehatan', 40], ['Kreativitas visual', 35], ['Keterbacaan', 25]] as $position => [$criterion, $weight]) {
                    $made['Lomba Poster Kesehatan']->criteria()->create(['name' => $criterion, 'weight' => $weight, 'position' => $position + 1]);
                }
            }

            foreach (['atlet1', 'atlet2', 'atlet3', 'atlet4', 'atlet5'] as $index => $key) {
                $registration = Registration::firstOrCreate(['competition_id' => $made['Tenis Meja Tunggal']->id, 'user_id' => $users[$key]->id], ['motivation' => 'Pendaftaran demo untuk mencoba bagan sistem gugur.', 'status' => 'accepted', 'submitted_at' => now()->subDays(2)->addMinutes($index), 'seed' => $index < 2 ? $index + 1 : null]);
                Participant::firstOrCreate(['registration_id' => $registration->id, 'competition_id' => $registration->competition_id, 'user_id' => $users[$key]->id], ['status' => 'confirmed']);
            }

            $proposal = Event::firstOrCreate(['name' => 'Badminton Open Internal 2026 · Demo'], ['organizer' => 'UKM Badminton', 'club_id' => $all[0]->id, 'pathway' => 'internal', 'field' => 'Olahraga', 'description' => 'Contoh pengajuan lomba oleh Ketua UKM yang menunggu keputusan kemahasiswaan.', 'status' => 'submitted', 'proposed_by' => $users['pengurus']->id, 'submitted_at' => now()->subDay()]);
            Competition::firstOrCreate(['event_id' => $proposal->id, 'name' => 'Ganda Campuran'], ['field' => 'Olahraga', 'format' => 'team', 'min_members' => 2, 'max_members' => 2, 'quota' => 16, 'location' => 'Lapangan kampus', 'description' => 'Nomor lomba usulan UKM (demo).', 'requirements' => 'Mahasiswa aktif.', 'opens_at' => now()->addDays(7), 'closes_at' => now()->addDays(20), 'starts_at' => now()->addDays(30), 'assessment_type' => 'bracket']);

            $selection = Event::firstOrCreate(['name' => 'Seleksi Delegasi Lomba Nasional 2026 · Demo'], ['organizer' => 'Kemahasiswaan Poltekkes Kemenkes Manado (demo)', 'pathway' => 'delegation', 'external_organizer' => 'Penyelenggara nasional (contoh)', 'status' => 'approved', 'proposed_by' => $users['admin']->id, 'decided_by' => $users['admin']->id, 'decided_at' => now()]);
            Competition::firstOrCreate(['event_id' => $selection->id, 'name' => 'Seleksi Baca Puisi'], ['field' => 'Seni', 'format' => 'individual', 'quota' => 20, 'location' => 'Aula kampus', 'level' => 'Nasional', 'description' => 'Seleksi internal untuk memilih delegasi kampus (demo).', 'requirements' => 'Mahasiswa aktif.', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDays(10), 'starts_at' => now()->addDays(15), 'assessment_type' => 'rubric']);
            Assignment::firstOrCreate(['user_id' => $users['event']->id, 'role' => 'admin_event', 'event_id' => $selection->id], $window);

            $recruitment = $all[2]->recruitments()->first();
            $application = ClubApplication::firstOrCreate(['recruitment_id' => $recruitment->id, 'user_id' => $users['mahasiswa']->id], ['motivation' => 'Saya ingin mengembangkan riset kesehatan dan kemampuan menulis ilmiah.', 'status' => 'accepted']);
            Membership::firstOrCreate(['club_id' => $all[2]->id, 'user_id' => $users['mahasiswa']->id], ['club_application_id' => $application->id, 'joined_at' => now()->subDays(2), 'division' => 'Riset']);

            $this->awards($users, $all);
        });
    }

    /**
     * One verified team award (two recipients), one verified individual award and one pending external claim.
     *
     * @param  array<string, User>  $users
     * @param  list<Club>  $clubs
     */
    private function awards(array $users, array $clubs): void
    {
        $disk = config('brudel.document_disk');
        $evidence = 'demo/bukti-prestasi-demo.pdf';
        if (! Storage::disk($disk)->exists($evidence)) {
            Storage::disk($disk)->put($evidence, "%PDF-1.4\n% Dokumen demo BRUDEL - bukan dokumen resmi\n%%EOF\n");
        }
        $rows = [
            ['Lomba Karya Tulis Ilmiah Kesehatan Provinsi · Demo', 'Akademik', 3, 'Juara 2', true, 'Tim Sehat', 'verified', ['mahasiswa' => 'ketua', 'anggota' => 'anggota'], $clubs[2]->id],
            ['Kejuaraan Badminton Kota Manado · Demo', 'Olahraga', 2, 'Juara 3', false, null, 'verified', ['pengurus' => 'individu'], $clubs[0]->id],
            ['Lomba Poster Regional · Demo', 'Seni', 4, 'Juara Harapan', false, null, 'pending', ['anggota' => 'individu'], null],
        ];
        foreach ($rows as [$title, $field, $level, $award, $team, $teamName, $status, $recipients, $club]) {
            $result = AwardResult::firstOrCreate(['title' => $title, 'award' => $award], [
                'source' => 'external', 'organizer' => 'Penyelenggara contoh (demo)', 'field' => $field, 'level' => $level, 'awarded_on' => now()->subMonths(2)->toDateString(),
                'is_team' => $team, 'team_name' => $teamName, 'club_id' => $club, 'status' => $status, 'evidence_path' => $evidence, 'evidence_disk' => $disk,
                'submitted_by' => $users[array_key_first($recipients)]->id, 'verified_by' => $status === 'verified' ? $users['admin']->id : null, 'verified_at' => $status === 'verified' ? now() : null,
            ]);
            foreach ($recipients as $key => $role) {
                AwardRecipient::firstOrCreate(['award_result_id' => $result->id, 'user_id' => $users[$key]->id], ['team_role' => $role]);
            }
        }
    }
}
