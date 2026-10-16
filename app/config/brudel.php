<?php

return [
    'demo' => env('BRUDEL_DEMO', false),
    'build' => 'LUbzwN3GmXegmr8L6NKfIF_4Ls__TPciVDCqonCeeF2I9I701DNJg8zqLrmfnciyLDTT_uRRKdbMoDbLyS1ATw',
    'timezone' => 'Asia/Makassar',
    'club_fee' => 0,
    'document_disk' => env('BRUDEL_DOCUMENT_DISK', 'local'),
    'max_upload_kb' => 5120,
    'activation_expire_minutes' => 60 * 24 * 3,

    'fields' => ['Olahraga', 'Seni', 'Akademik', 'Kerohanian', 'Sosial'],

    /**
     * Membership rules: every active student belongs to their own jurusan's HIMAJU automatically, BEM and MPM
     * members are appointed after an election, a student picks at most one UKM in each field listed here,
     * and any number of the other UKM.
     */
    'single_choice_fields' => ['Kerohanian'],

    /** Student organization categories; keys are stored in clubs.category. HIMAJU belongs to one jurusan. */
    'club_categories' => [
        'lembaga' => 'Lembaga Kemahasiswaan',
        'himaju' => 'Himpunan Mahasiswa Jurusan',
        'ukm' => 'Unit Kegiatan Mahasiswa',
    ],

    'club_symbols' => [
        'spark' => 'Bintang', 'racket' => 'Raket', 'ball' => 'Bola', 'music' => 'Musik', 'book' => 'Buku',
        'globe' => 'Globe', 'trophy' => 'Piala', 'users' => 'Komunitas', 'flag' => 'Bendera', 'shield' => 'Perisai',
    ],

    /** Six achievement levels in ascending scope. Keys are stored in the database. */
    'levels' => [
        1 => 'Internal Poltekkes',
        2 => 'Kota/Kabupaten',
        3 => 'Provinsi',
        4 => 'Regional',
        5 => 'Nasional',
        6 => 'Internasional',
    ],

    /** Default business assumptions; each can be overridden through Pengaturan Kampus. */
    'defaults' => [
        'regional_definition' => 'Regional: kegiatan lintas provinsi dalam negeri. Klasifikasi mengikuti cakupan resmi penyelenggara.',
        'refund_policy' => 'Kebijakan pembatalan dan pengembalian dana belum ditetapkan kampus. Hubungi kemahasiswaan sebelum mentransfer.',
        'certificate_prefix' => 'BRUDEL',
        // Campus operating hours (WITA, ISO weekdays 1 = Senin). Activities outside them need a permit.
        'operating_days' => '1,2,3,4,5',
        'operating_open' => '07:30',
        'operating_close' => '16:00',
    ],

    'roles' => [
        'kemahasiswaan' => ['label' => 'Kemahasiswaan Direktorat (superadmin)', 'scope' => null],
        'pengurus' => ['label' => 'Pengurus UKM', 'scope' => 'club_id'],
        'admin_event' => ['label' => 'Admin event', 'scope' => 'event_id'],
        'pengelola_lomba' => ['label' => 'Pengelola lomba', 'scope' => 'competition_id'],
        'jurusan' => ['label' => 'Kemahasiswaan Jurusan', 'scope' => 'department_id'],
        'penilai' => ['label' => 'Juri/penilai', 'scope' => 'competition_id'],
        'pembina' => ['label' => 'Pelatih/pembina', 'scope' => 'club_id'],
        'pimpinan' => ['label' => 'Pimpinan', 'scope' => null],
        'operator' => ['label' => 'Operator skor & live score', 'scope' => 'competition_id'],
    ],

    'pimpinan_positions' => ['Direktur', 'Wakil Direktur I', 'Wakil Direktur II', 'Wakil Direktur III'],

    'officer_positions' => ['Ketua', 'Ketua Putra', 'Ketua Putri', 'Wakil Ketua', 'Sekretaris', 'Bendahara', 'Koordinator Divisi', 'Anggota pengurus'],

    /** Chair positions: an organization may have one Ketua, or a Ketua Putra and a Ketua Putri (e.g. Dewan Racana). */
    'chair_positions' => ['Ketua', 'Ketua Putra', 'Ketua Putri'],

    /** Officer positions that may add or end other officers. */
    'officer_managers' => ['Ketua', 'Ketua Putra', 'Ketua Putri', 'Wakil Ketua', 'Sekretaris'],

    /** Judges per competition (generated juri accounts plus manual penilai assignments). */
    'max_judges' => 5,

    /** Domain for logins generated per competition. ".invalid" never receives mail. */
    'competition_account_domain' => env('BRUDEL_COMPETITION_ACCOUNT_DOMAIN', 'lomba.brudel.invalid'),

    'photo_max_kb' => 2048,

    'activity_code_minutes' => 120,

    'status_labels' => [
        'submitted' => 'Menunggu tinjauan',
        'accepted' => 'Diterima',
        'approved' => 'Disetujui',
        'rejected' => 'Ditolak',
        'revision' => 'Perlu revisi',
        'waitlisted' => 'Daftar tunggu',
        'draft' => 'Draf',
        'cancelled' => 'Dibatalkan',
        'confirmed' => 'Terkonfirmasi',
        'invited' => 'Menunggu konfirmasi',
        'active' => 'Aktif',
        'inactive' => 'Nonaktif',
        'left' => 'Keluar',
        'alumni' => 'Alumni',
        'program_review' => 'Menunggu persetujuan jurusan',
        'pending' => 'Menunggu verifikasi',
        'not_required' => 'Tanpa biaya',
        'unpaid' => 'Belum dibayar',
        'paid' => 'Pembayaran diterima',
        'verified' => 'Terverifikasi',
        'valid' => 'Sah',
        'revoked' => 'Dicabut',
        'completed' => 'Selesai',
        'awaiting' => 'Menunggu peserta',
        'scheduled' => 'Terjadwal',
        'live' => 'Berlangsung',
        'finished' => 'Hasil resmi',
        'waiting' => 'Menunggu tampil',
        'performing' => 'Sedang tampil',
        'done' => 'Selesai tampil',
    ],

    'membership_statuses' => ['active' => 'Aktif', 'inactive' => 'Nonaktif', 'left' => 'Keluar', 'alumni' => 'Alumni'],
];
