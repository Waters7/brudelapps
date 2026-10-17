# Status fitur

Tanggal penilaian: 15 Oktober 2026 (WITA). Tahap kedua menambahkan seluruh modul brief di atas tahap pertama (UKM dan lomba gratis). Aplikasi berjalan lokal dan diuji otomatis, tetapi **belum siap produksi**: S3, email, queue, PWA di perangkat, dan VPS belum diverifikasi pada layanan nyata. PostgreSQL baru diuji lokal (17.11 di Windows), belum di staging Docker.

Tahap ketiga (16 Oktober 2026) menambahkan: 7 jurusan resmi dan persetujuan/pemantauan per jurusan, akun lomba otomatis, juri maksimal 5 dengan log penilaian, dashboard pimpinan dan IKU per jurusan, Jelajah Kegiatan dengan kode kehadiran, halaman depan publik, ubah kata sandi, foto profil, masuk dengan Google, dan Cloudflare Turnstile.

Tahap keempat (16 Oktober 2026) menambahkan: banner kartu UKM dengan template 1200 × 480 px; pengurus dipilih dari anggota aktif oleh Ketua/Wakil Ketua/Sekretaris; chat bantuan live ke Kemahasiswaan Direktorat (tombol mengambang kanan bawah dan halaman Bantuan); izin kegiatan untuk peminjaman ruangan atau di luar jam operasional (HIMAJU ke Kemahasiswaan Jurusan, lainnya ke Kemahasiswaan Direktorat) dengan jam operasional di Pengaturan Kampus; peran Kemahasiswaan Jurusan (gabungan admin dan koordinator jurusan) yang hanya mengelola HIMAJU jurusannya; status lembar nilai juri yang jelas; dan Ruang Organisasi per UKM/HIMAJU/BEM/MPM berisi pengumuman, obrolan live, quest berpoin, dan papan peringkat. Perbaikan: menyimpan Pengaturan Kampus sebelumnya gagal di PostgreSQL (audit dengan kunci teks); kini lulus dan diuji. Lanjutan tahap keempat: menu **Perjadin Delegasi (Pangi)** untuk Kemahasiswaan Direktorat (delegasi terpilih, cek kelengkapan data surat, ekspor JSON/CSV, daftar surat dan dokumen pendaftaran lomba yang akan dibuat di Pangi; integrasi Pangi belum dibangun); banner halaman depan bergulir (video MP4/WebM atau gambar, dikelola direktorat, dengan banner animasi bawaan); animasi memuat berlogo BRUDEL saat login; Ruang UKM bergaya aplikasi di ponsel (navigasi bawah, podium peringkat, progres level).

Hasil uji otomatis terakhir (122 tes, 17 Oktober 2026): **PostgreSQL 17.11 — 122 lulus**, termasuk 4 tes konkurensi dua proses; **SQLite `:memory:` — 118 lulus, 4 dilewati** (tes konkurensi khusus PostgreSQL); MySQL 8.4.3 lulus pada suite 112 tes sebelumnya. `PerformanceBudgetTest` mengunci anggaran query setiap menu dan mencegah N+1 di halaman organisasi besar. Rincian ada di [TESTING.md](TESTING.md). Trial lokal di PostgreSQL dengan data bervolume: `.\Start-BRUDEL.ps1 -PostgresTrial -ResetData` (lihat `TrialSeeder` di TESTING.md).

## Selesai dan diuji otomatis

| Modul | Fitur | Tes utama |
|---|---|---|
| Akun | Impor CSV (pratinjau/impor, validasi NIM, email, prodi, duplikasi, ringkasan dan kesalahan per baris); aktivasi lewat email; lupa kata sandi; login/logout; status aktif/nonaktif dengan alasan; pembatasan percobaan login per akun | `AccountTest` |
| Keamanan akun | Menu akun (profil, ubah kata sandi, keluar) di bilah atas dan tombol Keluar di sidebar; ubah kata sandi mengakhiri sesi perangkat lain; foto profil dipotong 320×320 dan di-encode ulang (metadata hilang), hanya terlihat pengguna yang masuk; masuk dengan Google (OIDC + PKCE) hanya menautkan akun resmi yang sudah ada, opsional dibatasi domain kampus; Cloudflare Turnstile pada login, aktivasi, lupa kata sandi | `AccountSecurityTest` |
| Peran | 10 peran rangkap dengan masa berlaku dan lingkup UKM/event/jurusan/nomor lomba (termasuk pengelola lomba per nomor lomba); jabatan pimpinan Direktur/Wakil Direktur I–III; Ketua UKM mengelola pengurus UKM sendiri; tidak boleh mengubah hak sendiri; kemahasiswaan (superadmin) terakhir tidak bisa diakhiri | `AccountTest` |
| Jurusan | 7 jurusan resmi (Keperawatan, Kebidanan, Kesehatan Gigi, Gizi dan Dietetika, Kesehatan Lingkungan, Farmasi, Teknologi Laboratorium Medis); setiap prodi di bawah satu jurusan; persetujuan lomba per jurusan (tim lintas jurusan: tiap jurusan); halaman Jurusan berisi mahasiswa dan UKM yang diikuti, mahasiswa tidak aktif di UKM, unduhan sertifikat juara dan bukti prestasi, ekspor CSV — hanya untuk jurusannya sendiri | `DepartmentTest`, `ManagementTest` |
| Akun lomba otomatis | Setiap nomor lomba pada event disetujui otomatis mendapat akun pengelola lomba, operator skor & live score, dan 1–5 juri (lomba rubrik); hanya pengaju lomba dan kemahasiswaan yang dapat melihat/mengganti kata sandi (tercatat audit); akses berakhir 30 hari setelah lomba; akun yang tidak diperlukan dinonaktifkan | `CompetitionAccountTest` |
| Kegiatan UKM | Jelajah Kegiatan: pelatih/pembina/Ketua UKM membuat jadwal latihan, pertandingan persahabatan, meeting internal; kode kehadiran 6 karakter berjangka waktu; anggota aktif mengisi kode; catat hadir manual; laporan per jenis (latihan: hadir & tidak hadir; persahabatan: lawan, skor, catatan selanjutnya; meeting: wajib notulen PDF/DOCX); laporan cetak/PDF dan CSV | `ActivityTest` |
| Ruang organisasi | Halaman per organisasi untuk anggota aktif dan pengurus: pengumuman (sematkan, notifikasi), obrolan live (polling 4 detik, pengurus dapat menghapus), quest berpoin yang ditinjau pengurus, papan peringkat (poin quest + 10 poin per kehadiran kegiatan) dengan level | `ClubSpaceTest` |
| Bantuan | Tombol chat mengambang di setiap halaman dan halaman Bantuan; laporan kendala/usulan fitur ke Kemahasiswaan Direktorat; balasan tampil live; penanda belum dibaca per pesan; status selesai/buka kembali | `SupportChatTest` |
| Izin kegiatan | Kegiatan dibuat pengurus atau pembina; perlu izin bila meminjam ruangan atau di luar jam operasional (Pengaturan Kampus); HIMAJU diputuskan Kemahasiswaan Jurusan, BEM/MPM/UKM oleh Kemahasiswaan Direktorat; kegiatan menunggu izin tersembunyi dari anggota dan tidak dapat membuka kode | `ActivityPermitTest` |
| Lengkapi akun | Semua akun (kecuali akun lomba otomatis) wajib mengisi email aktif dan nomor HP aktif, serta mengganti sandi awal (tanggal lahir atau sandi dari kemahasiswaan), sebelum membuka halaman lain (/sandi-baru). Email sementara `.invalid` ditolak; nomor +62/62 dinormalkan ke 08…. Email dan HP dapat diubah di Profil; menu akun kanan atas memuat Profil, Email & nomor HP, Ubah kata sandi, Keluar, dan tetap tampil di layar HP | `AccountSetupTest`, `SiakadImportTest` |
| Hak akses Kemahasiswaan Jurusan | Menu Mahasiswa Jurusan, Verifikasi Prestasi, Laporan & IKU, dan Delegasi (lihat saja), semuanya terbatas pada mahasiswa jurusannya; filter jurusan laporan terkunci dan ekspor dibatasi; impor data, penugasan peran, dan pengaturan tetap di direktorat. Di Penugasan Peran, peran dipilih langsung per jurusan (Kemahasiswaan Jurusan Keperawatan, Kebidanan, dst.), dan direktorat dapat membuat akun staf baru (username + sandi awal yang wajib diganti) sekaligus menugaskannya | `DepartmentAccessTest` |
| Impor SIAKAD | Perintah `php artisan brudel:impor-siakad <berkas laporan_daftarmhs.xls>… [--commit]` membaca ekspor SIAKAD (tabel HTML): jurusan dari kepala berkas, NIM, nama, prodi (D3 → D-III, D4 → D-IV), email kampus lalu pribadi, tanggal lahir; NIK, alamat, dan data keluarga diabaikan dan tidak disimpan. Mahasiswa baru atau yang belum aktif mendapat kata sandi tanggal lahir 8 digit (DDMMYYYY) dan wajib menggantinya saat masuk pertama; akun yang sudah aktif tidak diubah kata sandinya. Masuk dapat memakai email, NIM, atau username. Ketua organisasi awal diimpor di server dengan `php artisan brudel:impor-pengurus <ketua.csv> [--commit]` | `SiakadImportTest`, `OfficerImportTest` |
| Master data | Menu Master Data (Kemahasiswaan Direktorat) dengan sub menu: bidang & kategori (termasuk bidang pilihan tunggal), jenis kegiatan (format laporan, internal), lokasi & ruangan (perlu izin, kapasitas), jenis lomba (template pengisi form nomor lomba), jabatan pengurus (ketua/pengelola pengurus), jenis penghargaan, dokumen pendaftaran lomba; ganti nama bidang/jabatan ikut memperbarui data, pilihan yang dipakai hanya dapat dinonaktifkan | `MasterDataTest` |
| Pengaturan sistem | Mengubah nilai `.env` yang aman dari menu: nama aplikasi, domain akun lomba, SMTP, Google SSO, Turnstile, Pangi; rahasia terenkripsi dan tidak ditampilkan; kembali ke `.env` per nilai; email uji; pemulihan server `php artisan brudel:pengaturan-sistem --reset=all`; infrastruktur (DB, APP_KEY, debug, penyimpanan, sesi, queue) hanya tampil baca | `SystemSettingsTest` |
| Impor ketua organisasi | CSV `organisasi, nama, nim, prodi, jabatan, email` di Penugasan Peran (pratinjau lalu impor, template semua organisasi); singkatan BEM/MPM/HMJ dikenali; akun dibuat dari NIM dengan email sementara yang tertimpa impor resmi mahasiswa; prodi baru dipetakan ke jurusan menurut nama; jabatan Ketua Putra/Putri; impor ulang aman | `OfficerImportTest` |
| Perjadin delegasi | Menu Kemahasiswaan Direktorat untuk delegasi terpilih: kelengkapan data surat (wajib/disarankan), pratinjau peserta dan pendamping, ekspor JSON skema `brudel.perjadin-mahasiswa.v1` (teraudit) dan CSV; tombol surat Pangi menunggu integrasi | `PerjadinTest` |
| Halaman depan | Banner bergulir otomatis dan dapat digeser (video MP4/WebM ≤ 20 MB atau gambar 1600 × 640), dikelola di menu Banner Halaman Depan; banner animasi bawaan bila belum ada slide; deretan organisasi berjalan; animasi memuat berlogo BRUDEL pada login | `LandingTest` |
| Banner UKM | Unggah banner kartu (dipotong dan di-encode ulang 1200 × 480 JPEG), template ukuran PNG, tampil di Jelajahi UKM dan profil | `OrganizationAdminTest` |
| Halaman depan | Halaman publik dengan info lomba, kegiatan mahasiswa (hanya kegiatan terbuka), UKM yang membuka pendaftaran, dan tombol Masuk; tanpa NIM/email | `ActivityTest`, `ExampleTest` |
| UKM | Profil (pembina, kontak, kegiatan, pengurus, prestasi), periode rekrutmen dengan kuota, syarat, seleksi, dokumen PDF opsional; tinjau/revisi/tolak/daftar tunggu; anggota, divisi, status aktif/nonaktif/keluar/alumni beserta riwayat; kepengurusan per periode; dashboard dan ekspor CSV UKM sendiri | `ManagementTest`, `EnrollmentTest` |
| Event | Event induk dan nomor lomba; pengajuan oleh Ketua UKM, keputusan kemahasiswaan beralasan; Ketua UKM tidak otomatis menjadi admin event; pengaturan tingkat, jadwal, kuota, syarat, format, min/maks anggota, biaya, penilaian, persetujuan prodi, live score dan akses publik | `ManagementTest` |
| Pendaftaran | Individu/tim, undangan NIM, konfirmasi, hapus anggota, batal draf, kuota, cegah peserta ganda, dokumen privat, revisi beralasan, riwayat status, persetujuan prodi per unit (tim lintas prodi), undangan kedaluwarsa dilepas terjadwal | `EnrollmentTest`, `ManagementTest` |
| Pembayaran | Biaya per tim/peserta tampil sebelum kirim dan disimpan; rekening resmi diatur kemahasiswaan; bukti transfer PDF privat; nominal harus sama dengan tagihan; status belum dibayar/menunggu/diterima/ditolak; petugas tidak memverifikasi pembayarannya sendiri; penerimaan pendaftaran menunggu pembayaran | `ManagementTest` |
| Pertandingan | Sistem gugur, unggulan, bye, jadwal/lokasi, pemenang resmi, propagasi ke babak berikutnya, koreksi beralasan dengan pemeriksaan dampak | `CompetitionTest` |
| Penilaian | Rubrik total 100%, maksimal 5 juri per nomor lomba, log setiap nilai masuk/berubah (nilai lama, baru, catatan, waktu, IP) dengan ekspor CSV, total per juri, nilai dan catatan per kriteria, rumus rekap, nilai sama (kriteria terberat atau peringkat bersama), larangan menilai diri/tim, hasil tidak dapat disahkan bila belum lengkap, kunci setelah pengesahan, buka koreksi oleh kemahasiswaan dengan audit | `CompetitionTest` |
| Live score | Skor sementara vs resmi, set/babak, status tampil seni/akademik, peringkat sementara opsional, waktu pembaruan terakhir, penanda koneksi terputus, polling 10 detik, versi optimistik untuk mencegah penimpaan, akses publik/khusus login | `CompetitionTest` |
| Delegasi | Foto profil anggota pada daftar dan detail delegasi; usulan dari peserta seleksi yang diterima, pendamping, keputusan pimpinan (tanpa konflik kepentingan), surat tugas PDF privat setelah disetujui, hasil keikutsertaan → prestasi menunggu verifikasi | `RecognitionTest` |
| Sertifikat | Sertifikat Saya, PDF per penerima, terbit hanya setelah hasil disahkan/lomba terlaksana, unduhan privat, verifikasi publik minimum, penggantian dengan versi, pencabutan beralasan | `RecognitionTest` |
| Prestasi | Enam tingkat, prestasi BRUDEL dari hasil resmi, prestasi eksternal dengan bukti dan verifikasi independen, halaman publik per bidang atas persetujuan mahasiswa tanpa NIM/dokumen | `RecognitionTest`, `PageRenderTest` |
| Laporan | Dashboard khusus pimpinan (ringkasan laporan, IKU, usulan & notifikasi delegasi saja); tabel prestasi IKU per jurusan × 6 tingkat (internal, kab/kota, provinsi, regional, nasional, internasional) dengan ekspor CSV; filter tahun, tingkat, bidang, jurusan, UKM, individu/tim; jumlah penghargaan vs mahasiswa unik; peserta vs tim; keanggotaan vs anggota unik; penghargaan tim dihitung sekali; 7 ekspor CSV sesuai hak akses dan tercatat audit | `RecognitionTest` |
| Notifikasi | Notifikasi dalam aplikasi (queue) untuk keputusan, undangan, pembayaran, sertifikat, penugasan; email aktivasi/pemulihan | `AccountTest` |
| Konkurensi | Penguncian baris PostgreSQL dibuktikan dengan dua proses bersamaan: kuota lomba terakhir, kuota UKM terakhir, pencatatan pemenang dengan versi sama, pengesahan hasil; tepat satu transaksi berhasil, audit tercatat sekali | `ConcurrencyTest` (PostgreSQL) |
| Keamanan | Otorisasi server per lingkup, CSRF, sesi terenkripsi, `no-store` untuk halaman pribadi, PDF diperiksa tipe/ukuran/tanda tangan berkas, unduhan lewat controller berotorisasi, audit tindakan penting | semua tes |

Semua halaman untuk setiap peran demo dirender tanpa galat (`PageRenderTest`). Pemeriksaan browser 15 Oktober 2026: login, dashboard tugas, pembuatan bagan 5 peserta (3 bye), pencatatan pemenang, papan live (polling terverifikasi), laporan, dan tampilan ponsel 375 px.

## Dibuat, belum diverifikasi operasional

- **PostgreSQL produksi**: migrasi, seluruh suite, dan penguncian baris lulus pada PostgreSQL 17.11 lokal (15 Oktober 2026). Image `postgres:17-alpine` pada Docker/VPS belum dijalankan. Perbedaan perilaku yang ditemukan dan sudah diperbaiki: pencarian mahasiswa kini `ILIKE`; zona sesi koneksi dipaksa UTC, karena server dengan zona selain UTC menggeser semua waktu; dan id prodi demo tidak lagi ditulis eksplisit agar sequence PostgreSQL benar.
- **Trial bervolume**: `TrialSeeder` diuji pada SQLite dan PostgreSQL, dan dijalankan di PostgreSQL lokal (`brudel_trial`). Pemeriksaan browser 15 Oktober 2026 meliputi dashboard tugas kemahasiswaan, laporan, persetujuan prodi, papan live Catur Cepat, dan Sertifikat Saya mahasiswa. Temuan yang sudah diperbaiki: setelah satu prodi menolak tim lintas prodi, persetujuan prodi lain dulu tetap tampil "menunggu" dan ikut dihitung di dashboard walau tidak bisa diputuskan. Kini persetujuan itu ditutup (`cancelled`, beralasan, tercatat di audit) dan dibuat ulang saat tim mengirim kembali.
- **Google dan Cloudflare**: alur Google SSO dan verifikasi Turnstile diuji dengan respons tiruan (`Http::fake`). Perlu Client ID/Secret Google dan kunci Turnstile resmi kampus, lalu dicoba di domain HTTPS.
- **Migrasi jurusan**: prodi lama dipetakan otomatis ke jurusan berdasarkan nama; diuji pada data trial PostgreSQL (5 prodi, 9 persetujuan, 1 peran). Prodi yang tidak dikenali tampil "Belum dipetakan" di Pengaturan Kampus dan harus dipilihkan jurusannya.
- **S3 privat**: adapter siap, belum tersambung bucket nyata.
- **Email**: aktivasi/pemulihan memakai notifikasi mail; demo memakai `MAIL_MAILER=log` (tautan muncul di `storage/logs/laravel.log`).
- **Queue dan scheduler**: notifikasi antre di tabel `jobs`; `Start-BRUDEL.ps1` menjalankan worker dan scheduler lokal. Belum diuji pada Supervisor/systemd atau Docker.
- **PWA**: manifest dan service worker hanya menyimpan aset publik. Instalasi di perangkat belum diuji.
- **Docker/VPS**: Dockerfile, Compose dan Nginx tersedia, belum dibangun.

## Asumsi bisnis sebagai pengaturan

| Asumsi | Nilai awal | Tempat mengubah |
|---|---|---|
| Aturan keanggotaan organisasi | HIMAJU wajib dan otomatis sesuai jurusan; BEM/MPM ditetapkan dari hasil pemilihan; maksimal satu UKM kerohanian; UKM lain bebas | Kebijakan tetap; bidang pilihan tunggal di `brudel.single_choice_fields` |
| Biaya pendaftaran UKM | Gratis; biaya lomba tidak berlaku untuk UKM | Kebijakan tetap (tidak ada alur pembayaran UKM) |
| Definisi tingkat regional | Lintas provinsi dalam negeri | Pengaturan Kampus |
| Kebijakan pembatalan/refund | Belum ditetapkan kampus (teks peringatan) | Pengaturan Kampus |
| Rekening resmi | Tidak ada (lomba berbayar terkunci sampai diisi) | Pengaturan Kampus |
| Awalan nomor sertifikat | `BRUDEL` | Pengaturan Kampus |
| Penanganan nilai sama | Kriteria berbobot terbesar | Per nomor lomba |
| Penguncian susunan tim | Saat pendaftaran dikirim | Tetap (dibuka kembali hanya lewat revisi) |
| Hasil gugur | Juara 1, finalis 2, semifinal bersama 3, babak sebelumnya bersama | Tetap |
| Juri per nomor lomba | Maksimal 5 (akun otomatis + penugasan manual); bawaan 3 untuk lomba rubrik | `config/brudel.php` (`max_judges`), formulir nomor lomba |
| Masa berlaku akun lomba | Sampai 30 hari setelah lomba selesai | `Competition::staffUntil()` |
| Domain login akun lomba | `lomba.brudel.invalid` (tidak menerima email) | `BRUDEL_COMPETITION_ACCOUNT_DOMAIN` |
| Kehadiran kegiatan UKM | Hanya anggota aktif UKM penyelenggara; kode berlaku 120 menit (5–480) | `config/brudel.php` (`activity_code_minutes`) |
| Foto profil | Terlihat oleh pengguna yang sudah masuk, tidak di profil publik | Tetap |
| Google SSO | Hanya akun yang sudah ada; domain opsional | `GOOGLE_ALLOWED_DOMAIN` |

## Keterbatasan yang diketahui

- Unggahan hanya diperiksa tipe, ekstensi, ukuran dan tanda tangan `%PDF-`; belum ada pemindai malware/karantina.
- Dokumen lama yang diganti tetap tersimpan privat; belum ada kebijakan retensi.
- Ekspor CSV berjalan sinkron (streaming); belum ada ekspor besar melalui queue.
- Penguncian roster bertahap (mis. dikunci saat diterima) belum dapat dikonfigurasi.
- Pemilihan peserta delegasi tanpa seleksi BRUDEL hanya untuk kemahasiswaan, memakai NIM.
- Belum ada audit aksesibilitas menyeluruh; baru pemeriksaan kontras pasangan warna utama.

## Pekerjaan tersisa sebelum produksi

1. Jalankan ulang seluruh suite, termasuk grup `pgsql`, pada PostgreSQL staging (`postgres:17-alpine`), lalu uji alur utama lewat browser.
2. Sambungkan S3 privat, SMTP resmi (SPF/DKIM/DMARC), worker queue dan scheduler; uji restore backup.
3. Pemindai malware dan kebijakan retensi dokumen; logging terpusat.
4. Uji instalasi PWA, HTTPS, cache setelah logout, dan aksesibilitas pembaca layar.
5. Impor data resmi kampus, isi rekening resmi dan kebijakan refund, tetapkan petugas tiap peran, hapus akun demo.
