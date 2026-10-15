# Status fitur

Tanggal penilaian: 15 Oktober 2026 (WITA). Tahap kedua menambahkan seluruh modul brief di atas tahap pertama (UKM dan lomba gratis). Aplikasi berjalan lokal dan diuji otomatis, tetapi **belum siap produksi**: S3, email, queue, PWA di perangkat, dan VPS belum diverifikasi pada layanan nyata. PostgreSQL baru diuji lokal (17.11 di Windows), belum di staging Docker.

Hasil uji otomatis terakhir (52 tes): **PostgreSQL 17.11 — 52 lulus, 805 assertions**, termasuk 4 tes konkurensi dua proses; **SQLite `:memory:` — 48 lulus, 4 dilewati** (tes konkurensi khusus PostgreSQL), 760 assertions. Rincian ada di [TESTING.md](TESTING.md).

## Selesai dan diuji otomatis

| Modul | Fitur | Tes utama |
|---|---|---|
| Akun | Impor CSV (pratinjau/impor, validasi NIM, email, prodi, duplikasi, ringkasan dan kesalahan per baris); aktivasi lewat email; lupa kata sandi; login/logout; status aktif/nonaktif dengan alasan; pembatasan percobaan login per akun | `AccountTest` |
| Peran | 9 peran rangkap dengan masa berlaku dan lingkup UKM/event/prodi/nomor lomba; Ketua UKM mengelola pengurus UKM sendiri; admin event menugaskan penilai/operator; tidak boleh mengubah hak sendiri; kemahasiswaan terakhir tidak bisa diakhiri | `AccountTest` |
| UKM | Profil (pembina, kontak, kegiatan, pengurus, prestasi), periode rekrutmen dengan kuota, syarat, seleksi, dokumen PDF opsional; tinjau/revisi/tolak/daftar tunggu; anggota, divisi, status aktif/nonaktif/keluar/alumni beserta riwayat; kepengurusan per periode; dashboard dan ekspor CSV UKM sendiri | `ManagementTest`, `EnrollmentTest` |
| Event | Event induk dan nomor lomba; pengajuan oleh Ketua UKM, keputusan kemahasiswaan beralasan; Ketua UKM tidak otomatis menjadi admin event; pengaturan tingkat, jadwal, kuota, syarat, format, min/maks anggota, biaya, penilaian, persetujuan prodi, live score dan akses publik | `ManagementTest` |
| Pendaftaran | Individu/tim, undangan NIM, konfirmasi, hapus anggota, batal draf, kuota, cegah peserta ganda, dokumen privat, revisi beralasan, riwayat status, persetujuan prodi per unit (tim lintas prodi), undangan kedaluwarsa dilepas terjadwal | `EnrollmentTest`, `ManagementTest` |
| Pembayaran | Biaya per tim/peserta tampil sebelum kirim dan disimpan; rekening resmi diatur kemahasiswaan; bukti transfer PDF privat; nominal harus sama dengan tagihan; status belum dibayar/menunggu/diterima/ditolak; petugas tidak memverifikasi pembayarannya sendiri; penerimaan pendaftaran menunggu pembayaran | `ManagementTest` |
| Pertandingan | Sistem gugur, unggulan, bye, jadwal/lokasi, pemenang resmi, propagasi ke babak berikutnya, koreksi beralasan dengan pemeriksaan dampak | `CompetitionTest` |
| Penilaian | Rubrik total 100%, penugasan penilai, nilai dan catatan per kriteria, rumus rekap, nilai sama (kriteria terberat atau peringkat bersama), larangan menilai diri/tim, hasil tidak dapat disahkan bila belum lengkap, kunci setelah pengesahan, buka koreksi oleh kemahasiswaan dengan audit | `CompetitionTest` |
| Live score | Skor sementara vs resmi, set/babak, status tampil seni/akademik, peringkat sementara opsional, waktu pembaruan terakhir, penanda koneksi terputus, polling 10 detik, versi optimistik untuk mencegah penimpaan, akses publik/khusus login | `CompetitionTest` |
| Delegasi | Usulan dari peserta seleksi yang diterima, pendamping, keputusan pimpinan (tanpa konflik kepentingan), surat tugas PDF privat setelah disetujui, hasil keikutsertaan → prestasi menunggu verifikasi | `RecognitionTest` |
| Sertifikat | Sertifikat Saya, PDF per penerima, terbit hanya setelah hasil disahkan/lomba terlaksana, unduhan privat, verifikasi publik minimum, penggantian dengan versi, pencabutan beralasan | `RecognitionTest` |
| Prestasi | Enam tingkat, prestasi BRUDEL dari hasil resmi, prestasi eksternal dengan bukti dan verifikasi independen, halaman publik per bidang atas persetujuan mahasiswa tanpa NIM/dokumen | `RecognitionTest`, `PageRenderTest` |
| Laporan | Filter tahun, tingkat, bidang, prodi, UKM, individu/tim; jumlah penghargaan vs mahasiswa unik; peserta vs tim; keanggotaan vs anggota unik; penghargaan tim dihitung sekali; 7 ekspor CSV sesuai hak akses dan tercatat audit | `RecognitionTest` |
| Notifikasi | Notifikasi dalam aplikasi (queue) untuk keputusan, undangan, pembayaran, sertifikat, penugasan; email aktivasi/pemulihan | `AccountTest` |
| Konkurensi | Penguncian baris PostgreSQL dibuktikan dengan dua proses bersamaan: kuota lomba terakhir, kuota UKM terakhir, pencatatan pemenang dengan versi sama, pengesahan hasil; tepat satu transaksi berhasil, audit tercatat sekali | `ConcurrencyTest` (PostgreSQL) |
| Keamanan | Otorisasi server per lingkup, CSRF, sesi terenkripsi, `no-store` untuk halaman pribadi, PDF diperiksa tipe/ukuran/tanda tangan berkas, unduhan lewat controller berotorisasi, audit tindakan penting | semua tes |

Semua halaman untuk setiap peran demo dirender tanpa galat (`PageRenderTest`). Pemeriksaan browser 15 Oktober 2026: login, dashboard tugas, pembuatan bagan 5 peserta (3 bye), pencatatan pemenang, papan live (polling terverifikasi), laporan, dan tampilan ponsel 375 px.

## Dibuat, belum diverifikasi operasional

- **PostgreSQL produksi**: migrasi, seluruh suite, dan penguncian baris lulus pada PostgreSQL 17.11 lokal (15 Oktober 2026). Image `postgres:17-alpine` pada Docker/VPS belum dijalankan. Satu perbedaan perilaku ditemukan dan diperbaiki: pencarian mahasiswa memakai `LIKE` yang peka huruf besar/kecil di PostgreSQL; kini memakai `whereLike` (`ILIKE`).
- **S3 privat**: adapter siap, belum tersambung bucket nyata.
- **Email**: aktivasi/pemulihan memakai notifikasi mail; demo memakai `MAIL_MAILER=log` (tautan muncul di `storage/logs/laravel.log`).
- **Queue dan scheduler**: notifikasi antre di tabel `jobs`; `Start-BRUDEL.ps1` menjalankan worker dan scheduler lokal. Belum diuji pada Supervisor/systemd atau Docker.
- **PWA**: manifest dan service worker hanya menyimpan aset publik. Instalasi di perangkat belum diuji.
- **Docker/VPS**: Dockerfile, Compose dan Nginx tersedia, belum dibangun.

## Asumsi bisnis sebagai pengaturan

| Asumsi | Nilai awal | Tempat mengubah |
|---|---|---|
| Mahasiswa boleh mengikuti beberapa UKM | Ya | `BRUDEL_MULTIPLE_CLUBS` |
| Biaya pendaftaran UKM | Gratis; biaya lomba tidak berlaku untuk UKM | Kebijakan tetap (tidak ada alur pembayaran UKM) |
| Definisi tingkat regional | Lintas provinsi dalam negeri | Pengaturan Kampus |
| Kebijakan pembatalan/refund | Belum ditetapkan kampus (teks peringatan) | Pengaturan Kampus |
| Rekening resmi | Tidak ada (lomba berbayar terkunci sampai diisi) | Pengaturan Kampus |
| Awalan nomor sertifikat | `BRUDEL` | Pengaturan Kampus |
| Penanganan nilai sama | Kriteria berbobot terbesar | Per nomor lomba |
| Penguncian susunan tim | Saat pendaftaran dikirim | Tetap (dibuka kembali hanya lewat revisi) |
| Hasil gugur | Juara 1, finalis 2, semifinal bersama 3, babak sebelumnya bersama | Tetap |

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
