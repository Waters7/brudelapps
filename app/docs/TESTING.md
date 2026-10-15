# Akun demo dan pengujian

Semua akun memakai kata sandi **BrudelDemo!2026**, hanya pada database demo lokal. Data dibuat oleh `php artisan db:seed --class=DemoSeeder` (hanya `APP_ENV=local/testing` dan `BRUDEL_DEMO=true`). Seeder produksi kosong.

| Email | NIM | Peran / lingkup |
|---|---|---|
| mahasiswa@demo.brudel.test | DEMO001 | Bintang Mandagi, D-III Keperawatan; anggota UKM Karya Tulis Ilmiah; profil publik aktif |
| anggota@demo.brudel.test | DEMO002 | Michelle Sumual, D-IV Gizi (prodi lain, untuk tim lintas prodi) |
| pengurus@demo.brudel.test | DEMO003 | Andre Runtuwene; Ketua UKM Badminton 2026/2027 |
| admin@demo.brudel.test | — | Pengelola kemahasiswaan (semua lingkup) |
| event@demo.brudel.test | — | Admin event Pekan Prestasi dan Seleksi Delegasi |
| prodi@demo.brudel.test | — | Peninjau prodi D-III Keperawatan saja |
| juri@demo.brudel.test | — | Penilai Lomba Poster Kesehatan |
| operator@demo.brudel.test | — | Operator Tenis Meja Tunggal |
| pimpinan@demo.brudel.test | — | Pimpinan (keputusan delegasi, laporan) |
| pembina@demo.brudel.test | — | Pelatih/pembina UKM Badminton |
| atlet1…atlet5@demo.brudel.test | DEMO004–008 | Peserta diterima Tenis Meja (bagan 5 peserta) |
| baru@demo.brudel.test | DEMO009 | Akun hasil impor yang **belum diaktivasi** |

Data demo lain: 6 UKM, event Pekan Prestasi (6 nomor lomba: gratis/berbayar, individu/tim, gugur/rubrik, live publik/khusus login), pengajuan event UKM Badminton yang menunggu keputusan, event seleksi delegasi, 2 prestasi terverifikasi (1 tim, 1 individu) dan 1 menunggu verifikasi. **Tidak ada rekening atau pembayaran yang dibuat**; tambahkan rekening uji berlabel jelas lewat Pengaturan Kampus bila ingin mencoba pembayaran.

## Tes otomatis

```sh
php artisan test --compact
npm run build
php artisan view:cache
```

Hasil 15 Oktober 2026: **47 tes, 751 assertions, lulus** pada SQLite `:memory:`.

| Skenario wajib brief | Tes |
|---|---|
| Isolasi akses antar-UKM/event/prodi | `EnrollmentTest::test_ukm_scope…`, `test_event_scope…`, `ManagementTest::test_club_officers_only…`, `test_program_approval_isolation…`, `RecognitionTest::test_exports_follow_assignment_scope` |
| Pendaftaran ganda | `EnrollmentTest::test_duplicate_ukm…`, `test_pending_invitee…`, `test_database_prevents_duplicate_participant…` |
| Perebutan kuota terakhir | `EnrollmentTest::test_last_ukm_place…`, `test_individual_flow_and_quota_validation` (berurutan; konkurensi PostgreSQL belum diuji) |
| Konfirmasi anggota tim | `EnrollmentTest::test_team_needs_confirmation…`, `ManagementTest::test_leader_can_remove_member…`, `test_unanswered_invitations_expire…` |
| Verifikasi pembayaran | `ManagementTest::test_program_approval_isolation_and_manual_payment_flow` |
| Penilaian lengkap | `CompetitionTest::test_rubric_requires_100_percent…`, `test_tie_break…`, `test_reopening_results…` |
| Bagan dengan bye | `CompetitionTest::test_bracket_with_byes…`, `test_seed_order…` |
| Live score sementara/final | `CompetitionTest::test_live_score_versions…`, `test_auth_only_live_score…` |
| Unduhan sertifikat milik pengguna lain | `RecognitionTest::test_certificates_are_private…` |
| Penghitungan prestasi tim | `RecognitionTest::test_team_award_counts_once…`, `test_external_achievement…` |
| Akun, aktivasi, impor, peran | `AccountTest` |
| Semua halaman tiap peran | `PageRenderTest` |

## Menguji per peran (browser)

Jalankan `./Start-BRUDEL.ps1` dari folder induk (menjalankan migrasi, worker queue, scheduler, dan server di http://127.0.0.1:8000).

1. **Mahasiswa** (`mahasiswa@`): Jelajahi UKM → daftar; Jelajahi Lomba → Karya Tulis Ilmiah → simpan draf → undang `DEMO002` → kirim setelah dikonfirmasi; Sertifikat Saya; Prestasi Saya → ajukan prestasi eksternal dengan PDF.
2. **Anggota** (`anggota@`): Pendaftaran Saya → terima undangan; Notifikasi.
3. **Ketua UKM** (`pengurus@`): Kelola UKM → Badminton: buka rekrutmen, ubah status/divisi anggota (alasan wajib), tambah pengurus; Kelola Event → ajukan lomba UKM.
4. **Kemahasiswaan** (`admin@`): Kelola Event → putuskan "Badminton Open Internal"; tugaskan admin event; Mahasiswa & Impor → pratinjau/impor CSV; Penugasan Peran; Pengaturan Kampus → rekening uji; Verifikasi Prestasi.
5. **Admin event** (`event@`): Tinjau Pengajuan; Operasional lomba Tenis Meja → buat bagan → catat pemenang → sahkan hasil → tetapkan penghargaan; Kelola Sertifikat → terbitkan PDF per penerima.
6. **Prodi** (`prodi@`): Debat Bahasa Inggris memerlukan persetujuan prodi dan berbayar. Daftarkan tim lintas prodi, lalu prodi Keperawatan hanya dapat memutuskan unitnya.
7. **Penilai** (`juri@`): Penilaian Juri → isi nilai Poster setelah ada peserta diterima.
8. **Operator** (`operator@`): Operator Live Score → perbarui skor; buka `/live/{id}` di jendela lain (publik untuk Tenis Meja) untuk melihat pembaruan berkala.
9. **Pimpinan** (`pimpinan@`): Delegasi → putuskan usulan; Laporan dengan filter.

### Aktivasi akun (demo)

Buka `/aktivasi`, isi `baru@demo.brudel.test`. Dengan `MAIL_MAILER=log`, tautan tertulis di `storage/logs/laravel.log` (notifikasi diproses worker queue). Buka tautan, buat kata sandi ≥10 karakter berisi huruf dan angka.

### Impor CSV

```csv
nim;nama;email;prodi;status
2026001;Nama Mahasiswa;nama@kampus.ac.id;KEP;aktif
```

Prodi diisi kode (`KEP`, `GIZ`, `KESLING`) atau nama prodi. Gunakan "Pratinjau validasi" sebelum "Impor baris valid".

## Pengujian yang masih wajib sebelum produksi

Jalankan ulang seluruh tes pada database PostgreSQL khusus pengujian, lalu uji konkurensi dua worker terhadap satu kuota tersisa, penerimaan anggota terakhir, pengesahan hasil, dan penetapan pemenang secara bersamaan. Uji bucket S3 privat, SMTP, restart/retry queue, scheduler, HTTPS, instalasi PWA, cache setelah logout, restore backup, serta aksesibilitas keyboard dan pembaca layar.
