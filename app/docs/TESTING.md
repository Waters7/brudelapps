# Akun demo dan pengujian

Semua akun memakai kata sandi **BrudelDemo!2026**, hanya pada database demo lokal. Data dibuat oleh `php artisan db:seed --class=DemoSeeder` (hanya `APP_ENV=local/testing` dan `BRUDEL_DEMO=true`). Seeder produksi kosong.

| Email | NIM | Peran / lingkup |
|---|---|---|
| mahasiswa@demo.brudel.test | DEMO001 | Bintang Mandagi, D-III Keperawatan; anggota UKM Inovasi dan Pengabmas; profil publik aktif |
| anggota@demo.brudel.test | DEMO002 | Michelle Sumual, D-IV Gizi dan Dietetika (jurusan lain, untuk tim lintas jurusan) |
| pengurus@demo.brudel.test | DEMO003 | Andre Runtuwene; Ketua UKM Olahraga 2026/2027 |
| admin@demo.brudel.test | — | Kemahasiswaan (superadmin, semua lingkup); pengaju event Pekan Prestasi sehingga dapat melihat akun lombanya |
| event@demo.brudel.test | — | Admin event Pekan Prestasi dan Seleksi Delegasi |
| jurusan@demo.brudel.test | — | Peninjau Jurusan Keperawatan saja (menggantikan akun prodi lama) |
| juri@demo.brudel.test | — | Juri Lomba Poster Kesehatan (penugasan manual) |
| operator@demo.brudel.test | — | Operator Tenis Meja Tunggal (penugasan manual) |
| pimpinan@demo.brudel.test | — | Pimpinan, jabatan Wakil Direktur III (dashboard pimpinan, keputusan delegasi, laporan) |
| pembina@demo.brudel.test | — | Pelatih/pembina UKM Olahraga (dapat membuat kegiatan dan kode kehadiran) |
| atlet1…atlet5@demo.brudel.test | DEMO004–008 | Peserta diterima Tenis Meja (bagan 5 peserta); atlet1–3 juga anggota UKM Olahraga divisi Badminton |
| baru@demo.brudel.test | DEMO009 | Akun hasil impor yang **belum diaktivasi** |
| `lomba{id}-pengelola`, `-operator`, `-juri{n}@lomba.brudel.invalid` | — | Akun lomba otomatis; kata sandinya acak dan dilihat di Kelola Event → Akun lomba (pengaju/kemahasiswaan) |

Data demo lain: 7 jurusan resmi; 22 organisasi resmi dari migrasi (BEM, MPM, 7 HIMAJU, 13 UKM), 6 UKM di antaranya diberi deskripsi demo dan rekrutmen terbuka; setiap mahasiswa demo otomatis anggota HIMAJU jurusannya; Michelle Sumual anggota BEM terpilih; event Pekan Prestasi (6 nomor lomba: gratis/berbayar, individu/tim, gugur/rubrik, live publik/khusus login; Debat memakai 2 juri otomatis); pengajuan event UKM Olahraga yang menunggu keputusan; event seleksi delegasi; 3 kegiatan UKM Olahraga (latihan yang sedang berlangsung, persahabatan mendatang, meeting yang menunggu notulen); 2 prestasi terverifikasi (1 tim lintas jurusan, 1 individu) dan 1 menunggu verifikasi. **Tidak ada rekening atau pembayaran yang dibuat**; tambahkan rekening uji berlabel jelas lewat Pengaturan Kampus bila ingin mencoba pembayaran.

### Skenario tahap ketiga (browser)

1. **Halaman depan**: buka `/` tanpa masuk — info lomba, kegiatan terbuka (meeting internal tidak tampil), UKM yang membuka pendaftaran, tombol Masuk.
2. **Akun**: menu akun di kanan atas → Profil & foto (unggah foto), Ubah kata sandi, Keluar. Tombol Keluar juga ada di bawah sidebar.
3. **Jurusan** (`jurusan@`): menu Jurusan → mahasiswa Keperawatan beserta UKM-nya, filter "Tidak aktif di UKM", sertifikat juara dan prestasi jurusan, CSV.
4. **Akun lomba otomatis** (`admin@` atau Ketua UKM pengaju setelah event disetujui): Kelola Event → event → "Akun lomba" pada nomor lomba → tampilkan/salin/ganti kata sandi. Masuk dengan akun pengelola/operator/juri untuk melihat hak masing-masing.
5. **Juri & log**: masuk sebagai juri, isi lalu ubah nilai; di Operasional lomba lihat "Nilai per juri & log penilaian" dan unduh CSV log.
6. **Jelajah Kegiatan** (`pengurus@` atau `pembina@`): buka "Latihan rutin tunggal" → Buat kode kehadiran; masuk sebagai `atlet1@`, isi kode di Jelajah Kegiatan; kembali sebagai pelatih → Buat laporan → Laporan (cetak/PDF) dan CSV. Meeting internal wajib notulen; persahabatan wajib lawan, skor, dan catatan.
7. **Pimpinan** (`pimpinan@`): dashboard hanya berisi ringkasan laporan, tabel IKU per jurusan, dan usulan/notifikasi delegasi.
8. **Google & Turnstile**: isi `GOOGLE_*` dan `TURNSTILE_*` di `.env` (lihat DEPLOYMENT.md); tombol Google dan widget Cloudflare muncul otomatis.

## Data trial bervolume (`TrialSeeder`)

`TrialSeeder` menjalankan `DemoSeeder`, lalu menambahkan data uji coba bervolume. Semua data dibuat lewat service modul, sehingga audit, riwayat status, dan notifikasi terisi seperti pemakaian nyata. Seeder hanya berjalan pada `APP_ENV=local/testing` dengan `BRUDEL_DEMO=true`, dan menolak berjalan dua kali; mulai ulang dengan `php artisan migrate:fresh --seeder=TrialSeeder`.

| Bagian | Isi |
|---|---|
| Akun | `trial001`–`trial100@demo.brudel.test` (NIM `TRIAL001`–`TRIAL100`, 5 prodi termasuk D-III Kebidanan dan D-III Farmasi): 001–093 aktif, 094–095 dinonaktifkan dengan alasan, 096–100 belum aktivasi. `penilai2@demo.brudel.test` sebagai penilai tamu. Kata sandi sama dengan akun demo. |
| UKM | 14 pengajuan per UKM: diterima, daftar tunggu, revisi, ditolak, dan menunggu tinjauan; anggota berdivisi, sebagian nonaktif atau keluar |
| Pekan Prestasi (berjalan) | Badminton Tunggal dengan semua status tinjauan; Poster dinilai 6 dari 8 peserta dengan status tampil; Karya Tulis Ilmiah dengan undangan tim yang belum dijawab; Futsal 4 tim; Debat (berbayar + persetujuan prodi) dengan satu tim per tahap: menunggu prodi, bukti bayar menunggu verifikasi, diterima, dan revisi. Bagan Tenis Meja sengaja belum dibuat. |
| Rekening | "Bank Uji Coba 0000000000 — REKENING UJI COBA - JANGAN TRANSFER", hanya untuk alur pembayaran trial |
| Event selesai (2 bulan lalu) | Cerdas Cermat (tim, 2 penilai), Video Edukasi (individu), dan Catur Cepat (bagan 8 peserta): hasil disahkan, Juara 1–3, dan sertifikat, termasuk 1 sertifikat versi 2 dan 1 yang dicabut |
| Prestasi & delegasi | 10 prestasi eksternal (6 terverifikasi, 2 ditolak, 2 menunggu); 15 profil publik; delegasi menunggu pimpinan, delegasi selesai dengan prestasi menunggu verifikasi, dan satu draf |

### Menjalankan trial lokal di PostgreSQL

Sekali per server PostgreSQL (sebagai superuser), buat database trial yang terpisah dari database uji:

```sql
CREATE ROLE brudel_trial LOGIN PASSWORD 'BrudelTrial!2026';
CREATE DATABASE brudel_trial OWNER brudel_trial;
```

Dari folder induk (PowerShell):

```powershell
.\Start-BRUDEL.ps1 -PostgresTrial -ResetData   # pertama kali / mengulang: kosongkan brudel_trial lalu isi TrialSeeder (±40 detik)
.\Start-BRUDEL.ps1 -PostgresTrial              # berikutnya: data trial tetap, buka http://127.0.0.1:8000
```

`-PostgresTrial` menyalakan PostgreSQL lokal bila belum berjalan dan memuat `pdo_pgsql` per proses tanpa mengubah `php.ini`. Koneksi diarahkan ke `brudel_trial`; `.env` tidak diubah, dan setelah skrip selesai variabel lingkungan dikembalikan. `-ResetData` tidak menghapus PDF lama di `storage/app/private`.

## Tes otomatis

```sh
php artisan test --compact
npm run build
php artisan view:cache
```

Hasil 16 Oktober 2026 (107 tes):

| Database | Hasil |
|---|---|
| PostgreSQL 17.11 (database uji `brudel_test`) | **107 lulus**, termasuk 4 tes konkurensi dua proses |
| SQLite `:memory:` (bawaan `phpunit.xml`) | **103 lulus, 4 dilewati, 1.532 assertions**; tes konkurensi grup `pgsql` hanya berjalan di PostgreSQL |

Tes tahap ketiga: `DepartmentTest` (7 jurusan, isolasi jurusan, unduhan sertifikat juara/bukti prestasi jurusan sendiri, IKU per jurusan, dashboard pimpinan), `CompetitionAccountTest` (akun otomatis, hanya pengaju/superadmin, ganti kata sandi, lingkup pengelola, juri maks. 5, log nilai), `AccountSecurityTest` (ubah kata sandi, foto, Google SSO dengan respons tiruan, Turnstile), `ActivityTest` (kode kehadiran, anggota saja, laporan per jenis, halaman depan).

Temuan khusus PostgreSQL yang sudah diperbaiki dan dikunci tes:

- Pencarian mahasiswa: `LIKE` peka huruf besar/kecil, kini memakai `ILIKE`.
- Zona waktu: koneksi kini memaksa zona sesi UTC. Sebelumnya, server yang zonanya bukan UTC (mesin ini `Asia/Kuala_Lumpur`) menggeser semua `timestampTz` sebanyak selisih zonanya (`CompetitionTest::test_times_are_stored_in_utc…`).
- `DemoSeeder` tidak lagi menulis id prodi eksplisit. Id eksplisit membuat sequence PostgreSQL tertinggal, sehingga "tambah prodi" gagal (`ManagementTest::test_student_affairs_can_add_a_program…`). Tes tidak lagi mengandalkan id tetap, karena sequence PostgreSQL tidak ikut di-rollback antar tes.

Jangan menjalankan dua suite bersamaan pada satu checkout: `Storage::fake('local')` memakai folder `storage/framework/testing` yang sama sehingga berkas tes saling terhapus.

### Menjalankan tes di PostgreSQL

Gunakan database khusus pengujian, jangan database demo atau produksi; setiap tes menghapus dan membuat ulang seluruh tabel. Sekali per server PostgreSQL (sebagai superuser):

```sql
CREATE ROLE brudel_test LOGIN PASSWORD 'BrudelUji!2026';
CREATE DATABASE brudel_test OWNER brudel_test;
```

Kata sandi di atas hanya untuk database uji lokal yang mendengarkan `127.0.0.1`. Ekstensi `pdo_pgsql` harus aktif juga untuk proses anak yang dibuat tes konkurensi. Jika `php.ini` tidak ingin diubah, buat folder berisi `pgsql.ini` dengan baris `extension=pdo_pgsql`, lalu arahkan `PHP_INI_SCAN_DIR` ke folder itu. Dari folder `app` (PowerShell):

```powershell
$env:PHP_INI_SCAN_DIR = 'C:\lokasi\folder-pgsql-ini'
$env:DB_CONNECTION = 'pgsql'; $env:DB_HOST = '127.0.0.1'; $env:DB_PORT = '5432'
$env:DB_DATABASE = 'brudel_test'; $env:DB_USERNAME = 'brudel_test'; $env:DB_PASSWORD = 'BrudelUji!2026'
php artisan test --compact                 # seluruh suite
php artisan test --compact --group=pgsql   # hanya tes konkurensi
```

Variabel lingkungan ini menimpa `DB_CONNECTION=sqlite` dan `DB_DATABASE=:memory:` di `phpunit.xml`; tanpa variabel tersebut tes kembali ke SQLite.

Mesin pengembangan memakai PostgreSQL 17.11 portabel (biner Windows EnterpriseDB) di `C:\laragon\bin\postgresql\postgresql-17.11` dengan data di `C:\laragon\data\postgresql-17`. Cluster ini memakai locale `builtin` C.UTF-8 dan autentikasi scram-sha-256, hanya mendengarkan `127.0.0.1:5432`, dan tidak didaftarkan sebagai service Windows. Nyalakan sebelum tes dan matikan setelahnya:

```powershell
& 'C:\laragon\bin\postgresql\postgresql-17.11\bin\pg_ctl.exe' -D 'C:\laragon\data\postgresql-17' -l 'C:\laragon\data\postgresql-17\server.log' -w start
& 'C:\laragon\bin\postgresql\postgresql-17.11\bin\pg_ctl.exe' -D 'C:\laragon\data\postgresql-17' stop
```

### Tes konkurensi (`ConcurrencyTest`, grup `pgsql`)

Setiap tes menjalankan dua proses PHP terpisah (`php artisan invoke-serialized-closure`, worker bawaan driver concurrency Laravel), masing-masing dengan koneksi dan transaksi sendiri. Tes menahan baris pertama dalam urutan kunci service sampai kedua worker terlihat menunggu di `pg_stat_activity`. Ini membuktikan kedua transaksi benar-benar tumpang tindih. Setelah lock dilepas, tepat satu transaksi harus berhasil dan yang lain ditolak dengan pesan validasi. Audit, riwayat status, dan notifikasi juga harus tercatat tepat sekali.

| Skenario | Baris yang ditahan | Yang kalah menerima |
|---|---|---|
| Kuota lomba terakhir (`EnrollmentService::submit`) | `competitions` | "Kuota lomba sudah penuh." |
| Kuota UKM terakhir (`EnrollmentService::reviewClub`) | `recruitments` | "Kuota UKM sudah penuh." |
| Pemenang dicatat bersamaan dengan versi sama (`BracketService::recordWinner`) | `competitions` | "Hasil sudah resmi. Gunakan koreksi beralasan." |
| Pengesahan hasil bersamaan (`ResultService::publish`) | `competitions` | "Hasil sudah disahkan." |

Uji mutasi 15 Oktober 2026: setelah `lockForUpdate()` dihapus sementara dari `submit` dan `publish`, kedua tes gagal karena tidak ada worker yang tertahan. Pada `publish`, kedua transaksi bahkan lolos pemeriksaan "sudah disahkan" dan bertabrakan di indeks unik `competition_results`. Kode kemudian dikembalikan.

Data uji perlu di-commit agar terlihat oleh proses lain. Karena itu `ConcurrencyTest` tidak memakai transaksi per tes, dan migrasi ulang dilakukan sebelum dan sesudah setiap tes.

| Skenario wajib brief | Tes |
|---|---|
| Isolasi akses antar-UKM/event/jurusan | `EnrollmentTest::test_ukm_scope…`, `test_event_scope…`, `ManagementTest::test_club_officers_only…`, `test_program_approval_isolation…`, `RecognitionTest::test_exports_follow_assignment_scope` |
| Pendaftaran ganda | `EnrollmentTest::test_duplicate_ukm…`, `test_pending_invitee…`, `test_database_prevents_duplicate_participant…` |
| Perebutan kuota terakhir | Berurutan: `EnrollmentTest::test_last_ukm_place…`, `test_individual_flow_and_quota_validation`. Dua proses bersamaan (PostgreSQL): `ConcurrencyTest::test_last_competition_place…`, `test_last_club_place…` |
| Pemenang dan pengesahan bersamaan | `ConcurrencyTest::test_concurrent_winner_entries…`, `test_concurrent_publication…` (PostgreSQL) |
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
3. **Ketua UKM** (`pengurus@`): Kelola UKM → UKM Olahraga: buka rekrutmen, ubah status/divisi anggota (alasan wajib), tambah pengurus; Kelola Event → ajukan lomba UKM.
4. **Kemahasiswaan** (`admin@`): Kelola Event → putuskan "Badminton Open Internal"; tugaskan admin event; Mahasiswa & Impor → pratinjau/impor CSV; Penugasan Peran; Pengaturan Kampus → rekening uji; Verifikasi Prestasi.
5. **Admin event** (`event@`): Tinjau Pengajuan; Operasional lomba Tenis Meja → buat bagan → catat pemenang → sahkan hasil → tetapkan penghargaan; Kelola Sertifikat → terbitkan PDF per penerima.
6. **Jurusan** (`jurusan@`): Debat Bahasa Inggris memerlukan persetujuan jurusan dan berbayar. Daftarkan tim lintas jurusan, lalu Jurusan Keperawatan hanya dapat memutuskan unitnya.
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

Jalankan ulang seluruh suite (termasuk grup `pgsql`) pada PostgreSQL staging dengan image `postgres:17-alpine` dari `compose.yaml`; pengujian di atas memakai PostgreSQL 17.11 Windows lokal. Uji bucket S3 privat, SMTP, restart/retry queue, scheduler, HTTPS, instalasi PWA, cache setelah logout, restore backup, serta aksesibilitas keyboard dan pembaca layar.
