# Akun demo dan pengujian

Semua akun memakai kata sandi **BrudelDemo!2026**, hanya pada database demo lokal.

| Email | NIM | Peran / lingkup |
|---|---|---|
| mahasiswa@demo.brudel.test | DEMO001 | Bintang Mandagi; mahasiswa, anggota Karya Tulis Ilmiah |
| anggota@demo.brudel.test | DEMO002 | Michelle Sumual; mahasiswa prodi lain, calon anggota tim |
| pengurus@demo.brudel.test | DEMO003 | Andre Runtuwene; mahasiswa + pengurus Badminton saja |
| event@demo.brudel.test | — | Admin Pekan Prestasi Mahasiswa saja |
| admin@demo.brudel.test | — | Kemahasiswaan; semua UKM/event |

Nama, prodi, pembina, kegiatan dan periode adalah ilustrasi. Akun dan fixture dibuat melalui `php artisan db:seed --class=DemoSeeder` dengan `APP_ENV=local` dan `BRUDEL_DEMO=true`. Seeder produksi kosong. DemoSeeder menolak produksi.

## Jalankan tes otomatis

```sh
php artisan test --compact
npm run build
php artisan view:cache
```

PHPUnit menggunakan SQLite `:memory:` yang terisolasi dari database demo. Hasil awal: **18 tes, 105 assertions, seluruhnya lulus**. Pengujian mencakup render halaman, login/logout/nonaktif, UKM diterima/audit, duplikat, kuota UKM dan lomba secara berurutan, isolasi UKM/event/CSV, masa penugasan, larangan self-review, alasan revisi/resubmit, konfirmasi tim/roster lock, reservasi peserta/penolakan undangan, file privat dan tipe file, periode/dokumen wajib, blok alur pembayaran yang belum tersedia, constraint unik, serta filter Livewire.

## UKM dari awal sampai akhir

1. Login mahasiswa, buka Jelajahi UKM → Badminton.
2. Isi motivasi minimal 20 karakter, kirim. Periksa status Menunggu tinjauan.
3. Logout, login pengurus. Buka Tinjau Pengajuan, pilih Terima. Alternatif: Minta revisi dengan alasan, lalu mahasiswa mengirim perbaikan.
4. Login mahasiswa kembali, UKM Saya menampilkan Badminton.
5. Pengurus Badminton tidak melihat pendaftaran Paduan Suara. Mahasiswa biasa tidak dapat membuka `/tinjauan` atau `/ekspor/ukm`.

## Lomba individu

1. Mahasiswa buka Badminton Tunggal, isi motivasi, simpan draf.
2. Buka Pendaftaran Saya, kirim pendaftaran.
3. Admin event buka Tinjau Pengajuan, terima atau minta revisi.
4. Mahasiswa melihat keputusan. Percobaan kirim dua kali ditolak.

## Lomba tim

1. Mahasiswa pilih Karya Tulis Ilmiah, simpan nama tim dan motivasi.
2. Undang `DEMO002`. Kirim sebelum konfirmasi harus ditolak.
3. Login akun anggota, Pendaftaran Saya → Terima undangan.
4. Login ketua, kirim. Susunan tim kini terkunci; admin event dapat meninjau.
5. Tolak undangan pada tim draf untuk menguji pelepasan reservasi peserta. Pemilik undangan saja yang boleh menanggapi.

## PDF

Lomba Poster Kesehatan memerlukan PDF: unggah PDF ≤5 MB, kirim, unduh lewat halaman sendiri. Salin URL dokumen ke sesi mahasiswa berbeda: harus 403. Pengurus UKM yang bukan admin event juga harus 403. File HTML dan PDF >5 MB ditolak. Gunakan dokumen uji tanpa data pribadi.

## Pengujian yang masih wajib

Belum diuji: perebutan kuota dengan dua proses/koneksi PostgreSQL; race upload-submit dan konfirmasi-submit; isolasi prodi; verifikasi pembayaran; rubrik tidak lengkap; bye/pengundian; live score sementara/final; sertifikat lintas pengguna; agregasi penghargaan tim. Lima kelompok terakhir belum diimplementasikan sehingga tidak boleh dinyatakan lulus.

Sebelum produksi, jalankan tes pada database PostgreSQL khusus pengujian (jangan database produksi), uji konkurensi dua worker terhadap satu kuota tersisa dengan barrier yang sama, periksa hanya satu transaksi berhasil dan audit konsisten. Uji restore backup, link dokumen pada bucket S3 privat, SMTP, queue restart/retry, HTTPS, instalasi PWA, offline setelah logout, serta aksesibilitas keyboard dan pembaca layar.


## Pemeriksaan browser 15 Oktober 2026

Login, filter Livewire, pengajuan Badminton, penerimaan oleh pengurus, dan keanggotaan setelah login ulang telah diperiksa melalui browser. Layout desktop 1440 px serta navigasi dan rekomendasi pada viewport ponsel 390 px diperiksa. Halaman offline publik tampil saat server berhenti; koneksi kembali memerlukan login ulang setelah sesi kedaluwarsa. Instalasi PWA perangkat dan keseluruhan siklus cache belum diuji. Tinjauan tambahan memperbaiki logout akun nonaktif serta konfigurasi pcntl, timeout queue, dan pengecualian public/hot pada Docker.


Pemeriksaan kontras pada kombinasi utama: teks sekunder #586e62 pada putih 5,49:1 dan latar #f6f8f7 5,15:1; teks putih pada tombol #007f78 4,88:1; teks #233b27 pada tombol #cddc29 8,03:1. Ini pemeriksaan pasangan warna, belum audit aksesibilitas menyeluruh.

