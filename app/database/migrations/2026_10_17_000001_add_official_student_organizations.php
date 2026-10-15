<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** HIMAJU in the order given by the campus, keyed by department code. */
    private const HIMAJU = ['KEP', 'KEB', 'KG', 'FAR', 'TLM', 'KL', 'GIZ'];

    /** The thirteen official UKM: name, field, icon. */
    private const UKM = [
        ['UKM Ikatan Duta', 'Sosial', 'spark'],
        ['UKM Dewan Racana', 'Sosial', 'flag'],
        ['UKM Korps Sukarela PMI', 'Sosial', 'shield'],
        ['UKM Satgas Kegawatdaruratan Bencana', 'Sosial', 'shield'],
        ['UKM English Club', 'Akademik', 'globe'],
        ['UKM Manado Poltekkes Choir', 'Seni', 'music'],
        ['UKM Kesenian', 'Seni', 'spark'],
        ['UKM Olahraga', 'Olahraga', 'ball'],
        ['UKM Inovasi dan Pengabmas', 'Akademik', 'book'],
        ['UKM Persekutuan Mahasiswa Kristen', 'Kerohanian', 'users'],
        ['UKM Keluarga Mahasiswa Katolik', 'Kerohanian', 'users'],
        ['UKM Mahasiswa Hindu Dharma', 'Kerohanian', 'users'],
        ['UKM Ikatan Mahasiswa Muslim Poltekkes Manado', 'Kerohanian', 'users'],
    ];

    /**
     * Official student organizations of Poltekkes Kemenkes Manado: BEM and MPM, one HIMAJU per jurusan,
     * and the thirteen UKM. Existing rows with the same name keep their profile and are only classified.
     */
    public function up(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->string('category', 20)->default('ukm')->index();
            $table->foreignId('department_id')->nullable()->constrained();
            $table->string('field')->nullable()->change();
        });

        $rows = [
            ['Badan Eksekutif Mahasiswa (BEM)', 'lembaga', null, null, 'flag', 'Lembaga eksekutif mahasiswa tingkat Poltekkes Kemenkes Manado.'],
            ['Majelis Permusyawaratan Mahasiswa (MPM)', 'lembaga', null, null, 'shield', 'Lembaga legislatif mahasiswa tingkat Poltekkes Kemenkes Manado.'],
        ];
        $departments = DB::table('departments')->get()->keyBy('code');
        foreach (self::HIMAJU as $code) {
            $name = $departments[$code]->name;
            $rows[] = ['HIMAJU '.$name, 'himaju', $departments[$code]->id, null, 'book', 'Himpunan Mahasiswa Jurusan '.$name.'. Keanggotaan khusus mahasiswa Jurusan '.$name.'.'];
        }
        foreach (self::UKM as [$name, $field, $symbol]) {
            $rows[] = [$name, 'ukm', null, $field, $symbol, 'Unit Kegiatan Mahasiswa bidang '.$field.'. Profil lengkap, pembina, dan kontak resmi dilengkapi pengurus melalui Kelola UKM.'];
        }

        $now = now();
        foreach ($rows as [$name, $category, $department, $field, $symbol, $description]) {
            $existing = DB::table('clubs')->whereRaw('LOWER(name) = ?', [mb_strtolower($name)]);
            if ($existing->exists()) {
                // Keep the profile, only classify it (also after a rollback, which keeps the rows).
                $existing->update(['category' => $category, 'department_id' => $department] + ($category === 'ukm' ? [] : ['field' => null]));

                continue;
            }
            DB::table('clubs')->insert([
                'name' => $name, 'category' => $category, 'department_id' => $department, 'field' => $field, 'symbol' => $symbol,
                'description' => $description, 'mentor' => 'Belum ditetapkan', 'contact' => 'Belum diisi', 'active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    /**
     * Organization rows stay because they may already have members; only the new columns are removed.
     */
    public function down(): void
    {
        DB::table('clubs')->whereNull('field')->update(['field' => 'Akademik']);
        Schema::table('clubs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
            $table->dropIndex(['category']);
            $table->dropColumn('category');
            $table->string('field')->nullable(false)->change();
        });
    }
};
