<?php

/**
 * The "Cara Penggunaan" page. A section shows to the roles it lists, or to everyone when it lists none;
 * "mahasiswa" stands for any student account, the other names are assignment roles.
 */
return [
    'sections' => [
        'akun' => [
            'title' => 'Akun dan notifikasi',
            'roles' => [],
            'steps' => [
                'Klik nama Anda di kanan atas untuk membuka Profil & foto, Email & nomor HP, dan Ubah kata sandi.',
                'Jika lupa kata sandi, pilih lupa kata sandi di halaman masuk lalu ikuti tautan yang dikirim ke email Anda.',
                'Ikon lonceng memberi tahu keputusan, undangan, dan tugas baru. Dashboard menampilkan pekerjaan yang menunggu Anda.',
            ],
        ],
        'bergabung' => [
            'title' => 'Bergabung dengan organisasi',
            'roles' => ['mahasiswa'],
            'steps' => [
                'Buka Jelajahi UKM lalu pilih organisasi.',
                'Saat rekrutmen dibuka, kirim formulirnya. Di luar masa rekrutmen, gunakan Minta bergabung.',
                'Undangan dari pengurus muncul di UKM Saya. Terima atau tolak dari sana.',
                'Setelah aktif, masuk ke Ruang organisasi untuk pengumuman, obrolan, quest, dan peringkat.',
            ],
        ],
        'calon-pengurus' => [
            'title' => 'Mengajukan diri menjadi pengurus BEM, MPM, atau HIMAJU',
            'roles' => ['mahasiswa'],
            'steps' => [
                'Rekrutmen pengurus yang sedang dibuka tampil di UKM Saya dan di profil organisasinya.',
                'Baca kriteria dari pengurus lama, pilih jabatan, lalu tulis pernyataanmu dan klik Ajukan diri.',
                'Pantau hasilnya di UKM Saya. Pengurus lama memeriksa setiap kriteria sebelum memutuskan.',
                'Jika diterima, kamu menjadi pengurus saat masa jabatan baru dimulai.',
            ],
        ],
        'lomba' => [
            'title' => 'Mendaftar lomba',
            'roles' => ['mahasiswa'],
            'steps' => [
                'Buka Jelajahi Lomba, pilih lomba, lalu simpan draf pendaftaran.',
                'Lengkapi anggota tim, dokumen, dan bukti bayar bila diminta, lalu kirim pendaftaran dari Pendaftaran Saya.',
                'Pantau statusnya di Pendaftaran Saya. Pertandingan dapat diikuti di Live Score.',
                'Sertifikat dan prestasi yang sudah diverifikasi tersimpan di Sertifikat Saya dan Prestasi Saya.',
            ],
        ],
        'organisasi' => [
            'title' => 'Mengelola organisasi',
            'roles' => ['pengurus', 'pembina', 'jurusan'],
            'steps' => [
                'Kelola UKM memuat rekrutmen, status anggota, dan daftar pengurus.',
                'Di Ruang organisasi, tab Anggota dipakai untuk menambah anggota langsung, mengundang, dan menjawab permintaan bergabung.',
                'Tab Beranda dan Quest dipakai untuk pengumuman dan tantangan anggota.',
            ],
        ],
        'proposal' => [
            'title' => 'Mengajukan proposal dan LPJ',
            'roles' => ['pengurus', 'pembina', 'jurusan'],
            'steps' => [
                'Buka Proposal & LPJ, lalu pilih organisasi dan jenis dokumen: proposal kegiatan, LPJ kegiatan, atau LPJ kepengurusan.',
                'Pilih tujuan: Kemahasiswaan Jurusan atau Kemahasiswaan Direktorat. HIMAJU hanya dapat memilih jurusannya sendiri atau Direktorat.',
                'Untuk LPJ kegiatan pilih kegiatannya, dan untuk LPJ kepengurusan isi periodenya. Unggah dokumen lalu kirim.',
                'Pantau tahap validasi di halaman dokumen. Jika statusnya Perlu revisi, baca catatan peninjau lalu gunakan Kirim perbaikan.',
                'Perbaikan kembali ke peninjau yang sama sebagai versi baru sampai diterima atau ditolak.',
            ],
        ],
        'rekrutmen-pengurus' => [
            'title' => 'Membuka rekrutmen pengurus baru BEM, MPM, atau HIMAJU',
            'roles' => ['pengurus', 'jurusan'],
            'steps' => [
                'Ketua, Wakil Ketua, atau Sekretaris membuka Kelola UKM, memilih organisasi, lalu Rekrutmen pengurus.',
                'Susun kriteria, jabatan yang dibuka, jadwal pendaftaran, dan masa jabatan baru.',
                'Saat kandidat masuk, centang kriteria yang terpenuhi lalu pilih Terima atau Tolak. Penolakan wajib disertai catatan.',
                'Kandidat yang diterima menjadi pengurus saat masa jabatan dimulai. Pemegang jabatan Ketua, Wakil Ketua, atau Sekretaris sebelumnya berakhir pada saat yang sama.',
            ],
        ],
        'event' => [
            'title' => 'Mengajukan event dan menunjuk tim lomba',
            'roles' => ['pengurus', 'jurusan'],
            'steps' => [
                'Ketua organisasi atau admin jurusan membuka Kelola Event & Lomba, membuat event, menambah nomor lomba, lalu mengajukannya ke kemahasiswaan.',
                'Pembuat event menjadi penanggung jawab. Penanggung jawab dapat diganti dari halaman event.',
                'Setelah disetujui, penanggung jawab menunjuk operator, pengelola lomba, juri, dan admin event di panel Tim penyelenggara.',
                'Akun lomba otomatis juga dibagikan oleh penanggung jawab dari halaman event.',
            ],
        ],
        'operasional' => [
            'title' => 'Menjalankan lomba',
            'roles' => ['admin_event', 'pengelola_lomba'],
            'steps' => [
                'Tinjau pendaftar di Tinjau Pengajuan dan periksa bukti bayar di Verifikasi Pembayaran.',
                'Di Operasional lomba, atur unggulan, buat bagan atau susun rubrik, lalu tugaskan operator dan juri.',
                'Setelah penilaian lengkap, sahkan hasil. Penghargaan dan sertifikat dikelola setelah hasil disahkan.',
            ],
        ],
        'operator' => [
            'title' => 'Mencatat skor dan live score',
            'roles' => ['operator'],
            'steps' => [
                'Buka Operator Skor & Live lalu pilih lomba dan pertandingan.',
                'Mulai pertandingan, perbarui skor, lalu selesaikan untuk mencatat pemenang.',
            ],
        ],
        'juri' => [
            'title' => 'Menilai sebagai juri',
            'roles' => ['penilai'],
            'steps' => [
                'Buka Penilaian Juri lalu pilih lomba dan peserta.',
                'Isi nilai setiap kriteria. Setiap perubahan tercatat, dan nilai terkunci setelah hasil disahkan.',
            ],
        ],
        'jurusan' => [
            'title' => 'Tugas admin jurusan',
            'roles' => ['jurusan'],
            'steps' => [
                'Menu Jurusan menampilkan mahasiswa jurusan Anda beserta organisasi dan prestasinya.',
                'Tinjau dokumen yang ditujukan ke jurusan Anda di Proposal & LPJ, dan pengajuan kegiatan di Izin Kegiatan.',
                'Verifikasi prestasi mahasiswa jurusan Anda di Verifikasi Prestasi.',
                'Pada lomba yang terkait jurusan Anda, panel Akses hasil lomba mengatur siapa yang dapat melihat hasil.',
            ],
        ],
        'kemahasiswaan' => [
            'title' => 'Tugas kemahasiswaan direktorat',
            'roles' => ['kemahasiswaan'],
            'steps' => [
                'Putuskan pengajuan event di Kelola Event & Lomba dan dokumen di Proposal & LPJ.',
                'Atur peran di Penugasan Peran. Ketua dan pengurus dapat diganti dari halaman organisasi.',
                'Kelola mahasiswa, dosen, dan tendik di Master Data. Aktivasi dosen dan tendik dilakukan dari sana.',
                'Audit Log mencatat setiap keputusan penting.',
            ],
        ],
        'pimpinan' => [
            'title' => 'Memantau sebagai pimpinan',
            'roles' => ['pimpinan'],
            'steps' => [
                'Dashboard pimpinan dan Laporan & IKU merangkum capaian kemahasiswaan.',
                'Pengajuan delegasi diputuskan di menu Delegasi.',
            ],
        ],
    ],
];
