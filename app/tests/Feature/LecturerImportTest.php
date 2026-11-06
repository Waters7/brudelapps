<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Lecturer;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use ZipArchive;

/**
 * Dosen from the Laporan DUK workbook: closed accounts that kemahasiswaan direktorat opens one by one or in bulk.
 */
class LecturerImportTest extends DemoTestCase
{
    private const NIP = '198001012005012001';

    private const SECOND_NIP = '198502022010012002';

    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        array_map('unlink', array_filter($this->files, 'is_file'));
        parent::tearDown();
    }

    /**
     * A workbook shaped like the DUK report: a title, two header rows, then one row per employee.
     *
     * @param  list<array{0: string, 1: string, 2: string, 3: string, 4?: string, 5?: string, 6?: string}>  $people  name, NIP, position, placement, personal email, official email, structural position
     */
    private function workbook(array $people): string
    {
        $cell = fn (string $column, int $line, string $value) => '<c r="'.$column.$line.'" t="inlineStr"><is><t>'.htmlspecialchars($value).'</t></is></c>';
        $line = fn (int $number, array $cells) => '<row r="'.$number.'">'.implode('', array_map(fn ($column, $value) => $cell($column, $number, $value), array_keys($cells), $cells)).'</row>';
        $rows = $line(1, ['A' => 'LAPORAN DAFTAR URUT KEPANGKATAN']);
        $rows .= $line(7, ['A' => 'No', 'C' => 'Nama', 'F' => 'NIK', 'G' => 'NIP Baru', 'H' => 'Pangkat', 'J' => 'Jabatan', 'AD' => 'e-mail', 'AH' => 'Jabatan Struktural / Penugasan', 'AI' => 'Penempatan Prodi / Unit']);
        $rows .= $line(8, ['H' => 'Golongan', 'J' => 'Nama', 'AD' => 'e-mail Pribadi', 'AE' => 'e-mail Kemenkes']);
        foreach ($people as $index => $person) {
            $rows .= $line(9 + $index, ['A' => (string) ($index + 1), 'C' => $person[0], 'F' => "'7100000000000000", 'G' => "'".$person[1], 'H' => 'III/c', 'J' => $person[2],
                'AD' => $person[4] ?? '', 'AE' => $person[5] ?? '', 'AH' => $person[6] ?? '', 'AI' => $person[3]]);
        }
        $path = storage_path('framework/testing/duk-'.uniqid().'.xlsx');
        @mkdir(dirname($path), 0777, true);
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        $zip->addFromString('xl/workbook.xml', '<workbook xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="LAPORAN DUK" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships><Relationship Id="rId1" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<worksheet><sheetData>'.$rows.'</sheetData></worksheet>');
        $zip->close();
        $this->files[] = $path;

        return $path;
    }

    private function import(string $file): void
    {
        $this->artisan('brudel:impor-dosen', ['file' => $file, '--commit' => true])->assertSuccessful();
    }

    private function lecturer(string $nip = self::NIP): User
    {
        return User::where('username', $nip)->firstOrFail();
    }

    public function test_only_dosen_are_imported_and_their_accounts_stay_closed(): void
    {
        $file = $this->workbook([
            ['Dr. DOSEN UJI, M.Kes', self::NIP, 'Dosen Lektor (JFT)', 'Keperawatan', 'pribadi.uji@contoh.test', 'resmi.uji@kemkes.go.id', 'Ketua Jurusan'],
            ['ASISTEN UJI, S.ST', self::SECOND_NIP, 'Asisten Ahli (JFT)', 'Gizi', 'asisten.uji@contoh.test'],
            ['TENDIK UJI', '197001012000012003', 'Penata Layanan Operasional (JFU)', 'Keperawatan', 'tendik.uji@contoh.test'],
            ['PENSIUN UJI', '196001012000012004', 'Dosen Lektor (JFT)', 'Pensiun', 'pensiun.uji@contoh.test'],
        ]);

        $this->artisan('brudel:impor-dosen', ['file' => $file])->expectsOutputToContain('2 dosen: 2 akun baru')->assertSuccessful();
        $this->assertSame(0, Lecturer::count(), 'Pratinjau tidak menulis data.');
        $this->artisan('brudel:impor-dosen', ['file' => $file, '--commit' => true])->expectsOutputToContain('1 pegawai bukan dosen, 1 pensiun')->assertSuccessful();

        $lecturer = $this->lecturer();
        $this->assertSame(2, Lecturer::count());
        $this->assertFalse($lecturer->active);
        $this->assertTrue($lecturer->must_change_password);
        $this->assertNull($lecturer->nim, 'Dosen bukan akun mahasiswa.');
        $this->assertTrue(Hash::check(self::NIP, $lecturer->password));
        $this->assertSame('resmi.uji@kemkes.go.id', $lecturer->email, 'Email Kemenkes diutamakan.');
        $this->assertSame(['KEP', 'Ketua Jurusan', 'III/c'], [$lecturer->lecturer->department->code, $lecturer->lecturer->structural_position, $lecturer->lecturer->rank]);
        $this->assertSame(Department::where('code', 'GIZ')->value('id'), $this->lecturer(self::SECOND_NIP)->lecturer->department_id);
    }

    public function test_a_dosen_signs_in_only_after_the_super_admin_activates_the_account(): void
    {
        $this->import($this->workbook([['DOSEN UJI', self::NIP, 'Dosen Lektor (JFT)', 'Keperawatan', 'dosen.uji@contoh.test']]));
        $lecturer = $this->lecturer();

        $this->post('/login', ['email' => self::NIP, 'password' => self::NIP])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => 'dosen.uji@contoh.test', 'password' => self::NIP])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->actingAs($this->user('admin'))->get('/')->assertSee('Dosen menunggu aktivasi');
        $this->post('/kelola/master/dosen/aktifkan', ['lecturers' => [$lecturer->id]])->assertSessionHasNoErrors();
        $this->assertTrue($lecturer->fresh()->active);
        $this->post('/logout');

        $this->post('/login', ['email' => self::NIP, 'password' => self::NIP])->assertRedirect('/sandi-baru');
        $this->get('/')->assertRedirect('/sandi-baru');
        $this->post('/sandi-baru', ['email' => 'dosen.uji@contoh.test', 'phone' => '081234567890', 'password' => 'SandiDosen2026', 'password_confirmation' => 'SandiDosen2026'])->assertRedirect('/');
        $this->get('/')->assertOk()->assertSee('Ruang dosen');
        $this->post('/logout');

        $this->post('/login', ['email' => self::NIP, 'password' => self::NIP])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => 'dosen.uji@contoh.test', 'password' => 'SandiDosen2026'])->assertRedirect('/');
    }

    public function test_only_the_super_admin_opens_the_list_and_activates(): void
    {
        $this->import($this->workbook([['DOSEN UJI', self::NIP, 'Dosen Lektor (JFT)', 'Keperawatan', 'dosen.uji@contoh.test']]));
        $id = $this->lecturer()->id;
        foreach (['jurusan', 'pengurus', 'mahasiswa'] as $role) {
            $this->actingAs($this->user($role))->get('/kelola/master/dosen')->assertForbidden();
            $this->post('/kelola/master/dosen/aktifkan', ['lecturers' => [$id]])->assertForbidden();
        }
        $this->assertFalse($this->lecturer()->active);
    }

    public function test_bulk_activation_touches_only_waiting_dosen(): void
    {
        $this->import($this->workbook([
            ['DOSEN SATU', self::NIP, 'Dosen Lektor (JFT)', 'Keperawatan', 'satu.uji@contoh.test'],
            ['DOSEN DUA', self::SECOND_NIP, 'Dosen Lektor (JFT)', 'Direktorat / Unit', 'dua.uji@contoh.test'],
        ]));
        $student = $this->user('anggota');
        $student->forceFill(['active' => false])->save();

        $this->actingAs($this->user('admin'))->get('/kelola/master/dosen')->assertOk()->assertSee('DOSEN SATU')->assertSee('Direktorat / Unit')
            ->assertViewHas('counts', fn ($counts) => $counts['waiting'] === 2);
        $this->get('/kelola/master/dosen?jurusan=unit')->assertSee('DOSEN DUA')->assertDontSee('DOSEN SATU');
        $this->post('/kelola/master/dosen/aktifkan', ['lecturers' => [$this->lecturer()->id, $this->lecturer(self::SECOND_NIP)->id, $student->id]])->assertSessionHasNoErrors();

        $this->assertTrue($this->lecturer()->active);
        $this->assertTrue($this->lecturer(self::SECOND_NIP)->active);
        $this->assertFalse($student->fresh()->active, 'Hanya akun dosen yang diaktifkan dari menu ini.');
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.activated_by_admin', 'subject_id' => $this->lecturer()->id]);
    }

    public function test_importing_again_makes_no_duplicates_and_leaves_signed_in_dosen_alone(): void
    {
        $file = $this->workbook([['DOSEN UJI', self::NIP, 'Dosen Lektor (JFT)', 'Keperawatan', 'dosen.uji@contoh.test']]);
        $this->import($file);
        $lecturer = $this->lecturer();
        $lecturer->forceFill(['active' => true, 'email' => 'aktif.uji@contoh.test', 'password' => 'SandiDosen2026', 'email_verified_at' => now(), 'must_change_password' => false])->save();
        $password = $lecturer->fresh()->password;

        $this->import($this->workbook([['NAMA BARU', self::NIP, 'Dosen Lektor Kepala (JFT)', 'Farmasi', 'dosen.uji@contoh.test']]));

        $this->assertSame(1, User::where('username', self::NIP)->count());
        $this->assertSame(1, Lecturer::count());
        $fresh = $lecturer->fresh();
        $this->assertSame(['DOSEN UJI', 'aktif.uji@contoh.test', $password, true], [$fresh->name, $fresh->email, $fresh->password, $fresh->active]);
        $this->assertSame('Dosen Lektor Kepala (JFT)', $fresh->lecturer->position, 'Data kepegawaian tetap mengikuti DUK.');
    }

    public function test_a_shared_email_falls_back_and_a_staff_account_is_linked_not_duplicated(): void
    {
        $staff = $this->user('jurusan');
        $this->import($this->workbook([
            ['DOSEN SATU', self::NIP, 'Dosen Lektor (JFT)', 'Keperawatan', 'bersama.uji@contoh.test'],
            ['DOSEN DUA', self::SECOND_NIP, 'Dosen Lektor (JFT)', 'Keperawatan', 'bersama.uji@contoh.test'],
            ['STAF JURUSAN, S.Kep', '197505052000012005', 'Dosen Lektor (JFT)', 'Keperawatan', $staff->email],
        ]));

        $this->assertSame('bersama.uji@contoh.test', $this->lecturer()->email);
        $this->assertSame(self::SECOND_NIP.'@dosen.brudel.invalid', $this->lecturer(self::SECOND_NIP)->email);

        $linked = $staff->fresh();
        $this->assertSame('197505052000012005', $linked->username);
        $this->assertNotNull($linked->lecturer);
        $this->assertTrue($linked->active, 'Akun staf yang sudah aktif tidak dinonaktifkan.');
        $this->assertTrue(Hash::check('BrudelDemo!2026', $linked->password), 'Sandi akun staf tidak diganti NIP.');
    }

    public function test_a_file_that_is_not_the_duk_report_is_refused(): void
    {
        $path = storage_path('framework/testing/bukan-'.uniqid().'.xlsx');
        file_put_contents($path, 'bukan excel');
        $this->files[] = $path;

        $this->expectException(ValidationException::class);
        $this->withoutExceptionHandling()->artisan('brudel:impor-dosen', ['file' => $path])->run();
    }
}
