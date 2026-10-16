<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Club;
use App\Models\Membership;
use App\Models\Program;
use App\Models\User;
use App\Services\Account\OfficerImportService;
use Illuminate\Http\UploadedFile;

class OfficerImportTest extends DemoTestCase
{
    private function csv(string $body): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('ketua.csv', "organisasi,nama,nim,prodi,jabatan,email\n".$body);
    }

    public function test_chairs_are_imported_with_accounts_programs_memberships_and_assignments(): void
    {
        $rows = implode("\n", [
            'MPM,Uji Ketua MPM,UJI0001,D-III Sanitasi,,',
            'BEM,Uji Ketua BEM,UJI0002,D-III Teknologi Laboratorium Medis,,ketua.bem@uji.ac.id',
            'HMJ Keperawatan,Uji Ketua HMJ,UJI0003,D-lll Keperawatan,,',
            'HMJ TLM,Uji Ketua Lab,UJI0004,D-III Teknologi Laboratorium Medis,,',
            'UKM Dewan Racana,Uji Pradana Putra,UJI0005,D-III Farmasi,Ketua Putra,',
            'UKM Dewan Racana,Uji Pradana Putri,UJI0006,D-III Farmasi,Ketua Putri,',
            'UKM Satgas Kegawatdaruratan Bencana,Uji Satgas,UJI0007,D-IV Promosi Kesehatan,,',
            'HMJ Kesling,Uji Salah Jurusan,UJI0008,D-III Farmasi,,',
            'UKM Antah Berantah,Uji Organisasi Asing,UJI0009,D-III Farmasi,,',
        ]);
        $this->actingAs($this->user('jurusan'))->post('/kelola/penugasan/impor-ketua', ['file' => $this->csv($rows), 'mode' => 'commit'])->assertForbidden();

        $this->actingAs($this->user('admin'))->post('/kelola/penugasan/impor-ketua', ['file' => $this->csv($rows), 'mode' => 'preview'])->assertSessionHasNoErrors()
            ->assertSessionHas('officer_import', fn ($report) => $report['errors'] === 2 && ! $report['committed']);
        $this->assertFalse(User::where('nim', 'UJI0001')->exists(), 'Pratinjau tidak menulis data.');
        $this->get('/kelola/penugasan')->assertSee('Majelis Permusyawaratan Mahasiswa (MPM)')->assertSee('Ketua Putra')->assertSee('harus mahasiswa jurusan tersebut')->assertSee('tidak dikenal');

        $this->post('/kelola/penugasan/impor-ketua', ['file' => $this->csv($rows), 'mode' => 'commit'])->assertSessionHasNoErrors()
            ->assertSessionHas('officer_import', fn ($report) => $report['assigned'] === 7 && $report['created_users'] === 7);

        $mpm = User::where('nim', 'UJI0001')->firstOrFail();
        $this->assertSame('uji0001@'.OfficerImportService::PLACEHOLDER_DOMAIN, $mpm->email);
        $this->assertNull($mpm->email_verified_at);
        $this->assertSame('ketua.bem@uji.ac.id', User::where('nim', 'UJI0002')->value('email'));
        $this->assertSame('KL', $mpm->program->department->code);
        $this->assertSame('KEP', User::where('nim', 'UJI0003')->firstOrFail()->program->department->code);
        $this->assertTrue(Program::where('name', 'D-III Keperawatan')->exists(), 'D-lll dinormalkan menjadi D-III.');
        $this->assertNull(Program::where('name', 'D-IV Promosi Kesehatan')->value('department_id'), 'Prodi tanpa padanan menunggu pemetaan.');

        $mpmClub = Club::where('name', 'Majelis Permusyawaratan Mahasiswa (MPM)')->first();
        $this->assertTrue(Membership::where('club_id', $mpmClub->id)->where('user_id', $mpm->id)->where('status', 'active')->exists());
        $this->assertTrue($mpm->leadsClub($mpmClub->id));
        $racana = Club::where('name', 'UKM Dewan Racana')->first();
        $this->assertSame(['Ketua Putra', 'Ketua Putri'], Assignment::where('club_id', $racana->id)->where('role', 'pengurus')->orderBy('position')->pluck('position')->all());
        $this->assertTrue(User::where('nim', 'UJI0006')->first()->managesOfficers($racana->id));
        $himaju = Club::where('category', 'himaju')->where('name', 'HIMAJU Teknologi Laboratorium Medis')->first();
        $this->assertTrue(User::where('nim', 'UJI0004')->first()->leadsClub($himaju->id));
        $this->assertFalse(User::where('nim', 'UJI0008')->exists());

        $this->post('/kelola/penugasan/impor-ketua', ['file' => $this->csv('MPM,Uji Ketua MPM,UJI0001,D-III Sanitasi,,'), 'mode' => 'commit'])
            ->assertSessionHas('officer_import', fn ($report) => $report['assigned'] === 0 && $report['rows'][0]['result'] === 'Sudah menjabat');

        $template = $this->get('/kelola/penugasan/template-ketua.csv')->assertOk()->streamedContent();
        $this->assertStringContainsString('Majelis Permusyawaratan Mahasiswa (MPM)', $template);
        $this->assertStringContainsString('UKM Ikatan Mahasiswa Muslim Poltekkes Manado', $template);
    }

    public function test_official_student_import_replaces_the_placeholder_email(): void
    {
        $this->actingAs($this->user('admin'))->post('/kelola/penugasan/impor-ketua', ['file' => $this->csv('BEM,Uji Ketua BEM,UJI0101,D-III Farmasi,,'), 'mode' => 'commit'])->assertSessionHasNoErrors();
        $official = UploadedFile::fake()->createWithContent('mahasiswa.csv', "nim,nama,email,prodi\nUJI0101,Uji Ketua BEM,ketua.resmi@kampus.ac.id,D-III Farmasi\n");
        $this->post('/kelola/mahasiswa/impor', ['file' => $official, 'mode' => 'commit']);
        $this->assertSame('ketua.resmi@kampus.ac.id', User::where('nim', 'UJI0101')->value('email'));
    }
}
