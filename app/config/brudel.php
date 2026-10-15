<?php

return [
    'demo' => env('BRUDEL_DEMO', false),
    'timezone' => 'Asia/Makassar',
    'multiple_clubs' => env('BRUDEL_MULTIPLE_CLUBS', true),
    'club_fee' => 0,
    'document_disk' => env('BRUDEL_DOCUMENT_DISK', 'local'),
    'max_upload_kb' => 5120,
    'activation_expire_minutes' => 60 * 24 * 3,

    'fields' => ['Olahraga', 'Seni', 'Akademik'],

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
    ],

    'roles' => [
        'kemahasiswaan' => ['label' => 'Pengelola kemahasiswaan', 'scope' => null],
        'pengurus' => ['label' => 'Pengurus UKM', 'scope' => 'club_id'],
        'admin_event' => ['label' => 'Admin event', 'scope' => 'event_id'],
        'prodi' => ['label' => 'Jurusan/prodi', 'scope' => 'program_id'],
        'penilai' => ['label' => 'Penilai', 'scope' => 'competition_id'],
        'pembina' => ['label' => 'Pelatih/pembina', 'scope' => 'club_id'],
        'pimpinan' => ['label' => 'Pimpinan', 'scope' => null],
        'operator' => ['label' => 'Operator pertandingan', 'scope' => 'competition_id'],
    ],

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
        'program_review' => 'Menunggu persetujuan prodi',
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
