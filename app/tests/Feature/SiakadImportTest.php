<?php

namespace Tests\Feature;

use App\Models\Program;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class SiakadImportTest extends DemoTestCase
{
    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        array_map('unlink', array_filter($this->files, 'is_file'));
        parent::tearDown();
    }

    /**
     * A file shaped like SIAKAD "laporan_daftarmhs.xls": preface rows, a header, data, and a repeated header.
     *
     * @param  list<list<string>>  $rows  NIM, Nama, Program Studi, Tanggal Lahir, Email Kampus, Email Pribadi
     */
    private function export(string $department, array $rows): string
    {
        $header = '<tr><th>No.</th><th>NIM</th><th>Nama</th><th>NIK</th><th>JK</th><th>Program Studi</th><th>Tempat Lahir</th><th>Tanggal Lahir</th><th>Alamat</th><th>Email Kampus</th><th>Email Pribadi</th><th>Nama Ibu</th></tr>';
        $body = '';
        foreach ($rows as $i => [$nim, $name, $program, $birth, $campus, $personal]) {
            $body .= '<tr><td>'.($i + 1)."</td><td>'{$nim}</td><td>{$name}</td><td>'7100000000000000</td><td>Perempuan</td><td>{$program}</td><td>MANADO</td><td>{$birth}</td><td>Jl. Contoh</td><td>{$campus}</td><td>{$personal}</td><td>IBU CONTOH</td></tr>";
            if ($i === 0) {
                $body .= $header;
            }
        }
        $html = "<!DOCTYPE html>\r\n<html><body><table><tr><td>Jurusan</td><td>:</td><td>{$department}</td><td></td><td>Periode Masuk</td><td>:</td><td>Periode 2026 Ganjil</td></tr>"
            ."<tr><td>Status Mahasiswa</td><td>:</td><td>Aktif</td></tr></table><table>{$header}{$body}</table></body></html>";
        $path = storage_path('framework/testing/siakad-'.uniqid().'.xls');
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, $html);

        $this->files[] = $path;

        return $path;
    }

    public function test_siakad_export_creates_students_with_birth_date_passwords(): void
    {
        $file = $this->export('Keperawatan', [
            ['SIA0001', 'MAHASISWA UJI SATU', 'D3 - Keperawatan', '9 Agustus 2005', '', 'uji.satu@contoh.test'],
            ['SIA0002', 'MAHASISWA UJI DUA', 'D4 - Promosi Kesehatan', '21 Desember 2004', 'uji.dua@kampus.test', 'uji.satu@contoh.test'],
            ['SIA0003', 'MAHASISWA UJI TIGA', 'prof - Pendidikan Profesi Ners', '', '', 'uji.satu@contoh.test'],
            ['DEMO001', 'Bintang Mandagi', 'D3 - Keperawatan', '1 Januari 2004', '', 'bintang.baru@contoh.test'],
        ]);
        $unknown = $this->export('Jurusan Antah', [['SIA0099', 'ASING', 'D3 - Entah', '1 Mei 2005', '', '']]);
        $before = User::where('nim', 'DEMO001')->first()->password;

        $this->artisan('brudel:impor-siakad', ['files' => [$file]])->assertSuccessful();
        $this->assertFalse(User::where('nim', 'SIA0001')->exists(), 'Pratinjau tidak menulis data.');
        $this->artisan('brudel:impor-siakad', ['files' => [$file, $unknown], '--commit' => true])
            ->expectsOutputToContain('3 akun baru')->expectsOutputToContain('Jurusan Antah')->assertSuccessful();

        $first = User::where('nim', 'SIA0001')->firstOrFail();
        $this->assertTrue(Hash::check('09082005', $first->password));
        $this->assertTrue($first->must_change_password);
        $this->assertSame(['uji.satu@contoh.test', 'D-III Keperawatan', 'KEP'], [$first->email, $first->program->name, $first->program->department->code]);
        $second = User::where('nim', 'SIA0002')->firstOrFail();
        $this->assertSame('uji.dua@kampus.test', $second->email, 'Email kampus diutamakan.');
        $this->assertSame('KEP', Program::where('name', 'D-IV Promosi Kesehatan')->first()->department->code);
        $third = User::where('nim', 'SIA0003')->firstOrFail();
        $this->assertSame('sia0003@siakad.brudel.invalid', $third->email, 'Email pribadi milik mahasiswa lain diganti email sementara.');
        $this->assertFalse($third->must_change_password, 'Tanpa tanggal lahir: aktivasi lewat email.');
        $this->assertSame('Pendidikan Profesi Ners', $third->program->name);
        $this->assertFalse(User::where('nim', 'SIA0099')->exists());

        $demo = User::where('nim', 'DEMO001')->first();
        $this->assertSame($before, $demo->password, 'Akun yang sudah aktif tidak diubah kata sandinya.');
        $this->assertSame('mahasiswa@demo.brudel.test', $demo->email);
        $this->assertTrue(User::where('nim', 'SIA0001')->first()->memberships()->whereHas('club', fn ($q) => $q->where('category', 'himaju'))->exists());
        $this->assertDatabaseMissing('users', ['name' => 'IBU CONTOH']);
    }

    public function test_first_login_with_nim_and_birth_date_requires_a_new_password(): void
    {
        $file = $this->export('Gizi', [['SIA0101', 'MAHASISWA GIZI UJI', 'D3 - Gizi', '05-03-2006', '', 'gizi.uji@contoh.test']]);
        $this->artisan('brudel:impor-siakad', ['files' => [$file], '--commit' => true])->assertSuccessful();

        $this->post('/login', ['email' => 'SIA0101', 'password' => 'salah123'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => 'SIA0101', 'password' => '05032006'])->assertRedirect('/sandi-baru');
        $this->get('/')->assertRedirect('/sandi-baru');
        $this->get('/sandi-baru')->assertOk()->assertSee('MAHASISWA GIZI UJI')->assertSee('gizi.uji@contoh.test')->assertSee('Kata sandi baru');
        $contact = ['email' => 'gizi.uji@contoh.test', 'phone' => '0812 3456 7890'];
        $this->post('/sandi-baru', $contact + ['password' => 'pendek', 'password_confirmation' => 'pendek'])->assertSessionHasErrors('password');
        $this->post('/sandi-baru', $contact)->assertSessionHasErrors('password');
        $this->post('/sandi-baru', ['phone' => '12345'] + $contact + ['password' => 'SandiGizi2026', 'password_confirmation' => 'SandiGizi2026'])->assertSessionHasErrors('phone');
        $this->post('/sandi-baru', ['email' => 'sia0101@siakad.brudel.invalid'] + $contact + ['password' => 'SandiGizi2026', 'password_confirmation' => 'SandiGizi2026'])->assertSessionHasErrors('email');
        $this->post('/sandi-baru', ['email' => 'admin@demo.brudel.test'] + $contact + ['password' => 'SandiGizi2026', 'password_confirmation' => 'SandiGizi2026'])->assertSessionHasErrors('email');
        $this->post('/sandi-baru', $contact + ['password' => 'SandiGizi2026', 'password_confirmation' => 'SandiGizi2026'])->assertRedirect('/');

        $student = User::where('nim', 'SIA0101')->first();
        $this->assertFalse($student->must_change_password);
        $this->assertNotNull($student->email_verified_at);
        $this->assertSame('081234567890', $student->phone);
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.setup_completed', 'subject_id' => $student->id]);
        $this->get('/')->assertOk();
        $this->post('/logout');
        $this->post('/login', ['email' => 'SIA0101', 'password' => '05032006'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => 'gizi.uji@contoh.test', 'password' => 'SandiGizi2026'])->assertRedirect('/');
    }
}
