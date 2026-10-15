<?php

namespace Tests\Feature;

use App\Models\AwardRecipient;
use App\Models\AwardResult;
use App\Models\Certificate;
use App\Models\CertificateVersion;
use App\Models\Department;
use App\Models\Program;
use App\Notifications\BrudelNotice;
use App\Services\Report\ReportService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DepartmentTest extends DemoTestCase
{
    public function test_the_seven_official_jurusan_exist_and_programs_belong_to_one(): void
    {
        $this->assertSame(['Farmasi', 'Gizi dan Dietetika', 'Kebidanan', 'Keperawatan', 'Kesehatan Gigi', 'Kesehatan Lingkungan', 'Teknologi Laboratorium Medis'],
            Department::orderBy('name')->pluck('name')->all());
        $this->assertFalse(Program::whereNull('department_id')->exists());
        $this->assertSame('Keperawatan', $this->user('mahasiswa')->program->department->name);
    }

    public function test_jurusan_sees_only_its_students_and_their_ukm_participation(): void
    {
        $reviewer = $this->user('jurusan');
        $this->actingAs($reviewer)->get('/jurusan')->assertOk()
            ->assertSee('Bintang Mandagi')->assertSee('UKM Inovasi dan Pengabmas')
            ->assertSee('Yosua Pangemanan')->assertDontSee('Michelle Sumual')->assertDontSee('Grace Wowor');
        $this->get('/jurusan?ukm=tidak')->assertOk()->assertViewHas('students', fn ($students) => $students->pluck('name')->contains('Andre Runtuwene')
            && ! $students->pluck('name')->contains('Bintang Mandagi') && ! $students->pluck('name')->contains('Yosua Pangemanan'));
        $gizi = Department::where('code', 'GIZ')->first();
        $this->get('/jurusan?jurusan='.$gizi->id)->assertOk()->assertDontSee('Michelle Sumual');
        $this->get('/jurusan/'.$gizi->id.'/ekspor')->assertForbidden();
        $csv = $this->get('/jurusan/'.$reviewer->assignments()->first()->department_id.'/ekspor?data=ukm')->assertOk()->streamedContent();
        $this->assertStringContainsString('Tidak aktif di UKM', $csv);
        $this->assertStringNotContainsString('Michelle', $csv);
        $this->actingAs($this->user('mahasiswa'))->get('/jurusan')->assertForbidden();
    }

    public function test_jurusan_downloads_winner_certificates_and_evidence_of_its_own_students_only(): void
    {
        Storage::fake('local');
        $poster = $this->competition('Lomba Poster Kesehatan');
        $make = function (string $userKey, bool $winner) use ($poster) {
            $user = $this->user($userKey);
            $award = $winner ? AwardResult::create(['source' => 'brudel', 'competition_id' => $poster->id, 'title' => 'Poster', 'organizer' => 'Kampus', 'field' => 'Seni', 'level' => 1, 'award' => 'Juara 1', 'awarded_on' => now(), 'status' => 'verified', 'submitted_by' => $user->id]) : null;
            $recipient = $award ? AwardRecipient::create(['award_result_id' => $award->id, 'user_id' => $user->id]) : null;
            $certificate = Certificate::create(['number' => 'TEST/'.Str::random(6), 'user_id' => $user->id, 'competition_id' => $poster->id, 'award_recipient_id' => $recipient?->id,
                'title' => 'Poster', 'award_type' => $winner ? 'Juara 1' : 'Peserta', 'issued_on' => now(), 'verification_token' => Str::random(48), 'issued_by' => $user->id]);
            Storage::disk('local')->put('certificates/'.$certificate->id.'.pdf', '%PDF-1.4 test');
            CertificateVersion::create(['certificate_id' => $certificate->id, 'version' => 1, 'path' => 'certificates/'.$certificate->id.'.pdf', 'disk' => 'local', 'uploaded_by' => $user->id, 'created_at' => now()]);

            return $certificate;
        };
        $ownWinner = $make('mahasiswa', true);
        $ownParticipant = $make('atlet3', false);
        $otherWinner = $make('anggota', true);

        $this->actingAs($this->user('jurusan'));
        $this->get('/jurusan')->assertSee($ownWinner->number)->assertDontSee($otherWinner->number);
        $this->get('/sertifikat/'.$ownWinner->id.'/unduh')->assertOk();
        $this->get('/sertifikat/'.$ownParticipant->id.'/unduh')->assertForbidden();
        $this->get('/sertifikat/'.$otherWinner->id.'/unduh')->assertForbidden();

        $teamAward = AwardResult::where('title', 'Lomba Karya Tulis Ilmiah Kesehatan Provinsi · Demo')->first();
        $badminton = AwardResult::where('title', 'Kejuaraan Badminton Kota Manado · Demo')->first();
        $pending = AwardResult::where('status', 'pending')->first();
        Storage::disk('local')->put($teamAward->evidence_path, '%PDF-1.4 demo');
        $this->get('/prestasi/'.$teamAward->id.'/bukti')->assertOk();
        $this->get('/prestasi/'.$badminton->id.'/bukti')->assertOk();
        $this->get('/prestasi/'.$pending->id.'/bukti')->assertForbidden();
    }

    public function test_iku_counts_awards_per_jurusan_and_team_awards_once_on_campus_total(): void
    {
        $iku = app(ReportService::class)->ikuByDepartment([]);
        $rows = collect($iku['rows'])->keyBy(fn ($row) => $row['department']->code);
        $this->assertSame([2, 2], [$rows['KEP']['total'], $rows['KEP']['students']]);
        $this->assertSame([1, 1, 1], [$rows['GIZ']['total'], $rows['GIZ']['students'], $rows['GIZ']['levels'][3]]);
        $this->assertSame(0, $rows['KG']['total']);
        $this->assertSame([2, 3, 1, 1], [$iku['totals']['total'], $iku['totals']['students'], $iku['totals']['levels'][2], $iku['totals']['levels'][3]]);
        $csv = $this->actingAs($this->user('pimpinan'))->get('/laporan/ekspor/iku')->assertOk()->streamedContent();
        $this->assertStringContainsString('Teknologi Laboratorium Medis', $csv);
        $this->assertStringContainsString('Total kampus', $csv);
    }

    public function test_pimpinan_dashboard_shows_reports_and_delegation_notifications_only(): void
    {
        $leader = $this->user('pimpinan');
        $leader->notify(new BrudelNotice('Usulan delegasi menunggu keputusan', 'Delegasi Puisi', '/delegasi/1'));
        $leader->notify(new BrudelNotice('Penugasan baru', 'Bukan delegasi', '/'));
        $this->actingAs($leader)->get('/')->assertOk()->assertViewIs('leadership')
            ->assertSee('Wakil Direktur III', false)->assertSee('Prestasi mahasiswa sesuai IKU per jurusan')
            ->assertSee('Usulan delegasi menunggu keputusan')->assertDontSee('Bukan delegasi')
            ->assertDontSee('Jelajahi UKM')->assertSee('Laporan &amp; IKU', false);
        $this->actingAs($this->user('admin'))->get('/')->assertViewIs('dashboard');
    }
}
