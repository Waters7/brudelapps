<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Master data maintained by kemahasiswaan direktorat: choice lists that used to be fixed in code. The seeded
     * rows are the lists the application already used, so behavior stays the same after migrating.
     */
    public function up(): void
    {
        Schema::create('master_options', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40);
            $table->string('code', 80);
            $table->string('label', 120);
            $table->json('meta')->nullable();
            $table->unsignedSmallInteger('position')->default(1);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['type', 'code']);
            $table->index(['type', 'active', 'position']);
        });

        $rows = [
            'field' => [
                ['olahraga', 'Olahraga', []], ['seni', 'Seni', []], ['akademik', 'Akademik', []],
                ['kerohanian', 'Kerohanian', ['single_choice' => true]], ['sosial', 'Sosial', []],
            ],
            'activity_type' => [
                ['latihan', 'Jadwal latihan', ['report' => 'attendance', 'internal_only' => false]],
                ['persahabatan', 'Pertandingan persahabatan', ['report' => 'match', 'internal_only' => false]],
                ['meeting', 'Meeting internal', ['report' => 'minutes', 'internal_only' => true]],
            ],
            'officer_position' => [
                ['ketua', 'Ketua', ['chair' => true, 'manager' => true]], ['ketua_putra', 'Ketua Putra', ['chair' => true, 'manager' => true]],
                ['ketua_putri', 'Ketua Putri', ['chair' => true, 'manager' => true]], ['wakil_ketua', 'Wakil Ketua', ['chair' => false, 'manager' => true]],
                ['sekretaris', 'Sekretaris', ['chair' => false, 'manager' => true]], ['bendahara', 'Bendahara', ['chair' => false, 'manager' => false]],
                ['koordinator_divisi', 'Koordinator Divisi', ['chair' => false, 'manager' => false]], ['anggota_pengurus', 'Anggota pengurus', ['chair' => false, 'manager' => false]],
            ],
            'award' => [
                ['juara_1', 'Juara 1', ['rank' => 1]], ['juara_2', 'Juara 2', ['rank' => 2]], ['juara_3', 'Juara 3', ['rank' => 3]],
                ['harapan_1', 'Juara Harapan 1', ['rank' => 4]], ['harapan_2', 'Juara Harapan 2', ['rank' => 5]], ['harapan_3', 'Juara Harapan 3', ['rank' => 6]],
                ['finalis', 'Finalis', ['rank' => 7]],
            ],
            'registration_document' => [
                ['surat_pengantar', 'Surat pengantar/rekomendasi kampus untuk pendaftaran', ['per_student' => false]],
                ['keterangan_aktif', 'Surat keterangan mahasiswa aktif', ['per_student' => true]],
                ['ktm', 'Kartu tanda mahasiswa (KTM)', ['per_student' => true]],
                ['pernyataan', 'Surat pernyataan keaslian karya atau kesediaan mengikuti lomba', ['per_student' => true]],
            ],
        ];
        $now = now();
        foreach ($rows as $type => $options) {
            foreach ($options as $index => [$code, $label, $meta]) {
                DB::table('master_options')->insert([
                    'type' => $type, 'code' => $code, 'label' => $label, 'meta' => json_encode($meta), 'position' => $index + 1,
                    'active' => true, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('master_options');
    }
};
