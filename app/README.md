# BRUDEL · Poltekkes Kemenkes Manado

**Bakat, Rekognisi, Unjuk Diri, dan Eksplorasi Lomba**  
Sportif Berkompetisi, Unggul Berprestasi.

Mengelola organisasi kemahasiswaan (BEM, MPM, HIMAJU, UKM), kompetisi, seleksi delegasi, sertifikat, dan prestasi mahasiswa: akun dari impor resmi, UKM dan keanggotaan, event dan nomor lomba, pendaftaran individu/tim, persetujuan prodi, pembayaran manual, bagan gugur, rubrik penilaian, live score, delegasi dan surat tugas, sertifikat, prestasi, serta laporan. Baca [status](docs/STATUS.md) untuk fitur yang selesai, yang belum diverifikasi operasional, dan pekerjaan tersisa. Aplikasi belum siap produksi.

## Teknologi

Laravel 13.36.0, Livewire 4.4.7, Blade, Tailwind CSS 4, Vite, PHP 8.4. PostgreSQL untuk target VPS, SQLite untuk demo lokal dan tes awal. S3 privat disediakan melalui adapter Flysystem. Versi dependensi terperinci terkunci dalam `composer.lock` dan `package-lock.json`.

## Instalasi lokal baru

Persyaratan: PHP 8.4 dengan curl, fileinfo, mbstring, XML/DOM, PDO SQLite dan/atau PostgreSQL, zip, intl; Composer 2; Node 22.12+ beserta npm. Versi PHP 8.4 mengikuti paket pada lockfile.

```sh
composer install
npm ci --ignore-scripts
cp .env.example .env
php artisan key:generate
```

Untuk PostgreSQL: buat database `brudel` dan pengguna khusus, lalu isi `DB_*`. Untuk demo SQLite: ubah `.env` menjadi `DB_CONNECTION=sqlite`, hapus `DB_DATABASE` atau set path absolut file SQLite kosong yang dibuat sendiri; set `BRUDEL_DEMO=true` dan `BRUDEL_DOCUMENT_DISK=local`. Jangan masukkan `.env` ke version control.

```sh
php artisan migrate
# Hanya untuk demo lokal, tidak untuk produksi:
php artisan db:seed --class=DemoSeeder
npm run build
php artisan serve --host=127.0.0.1 --port=8000
```

Notifikasi dan email dikirim melalui queue, jadi jalankan juga `php artisan queue:work` dan `php artisan schedule:work` (atau gunakan `Start-BRUDEL.ps1`).

Pada komputer ini semuanya telah diinstal. Dari folder induk jalankan `./Start-BRUDEL.ps1`: skrip menjalankan migrasi, worker queue, scheduler, dan server. PHP Laragon ditemukan pada `C:\laragon\bin\php\php-8.4.25-Win32-vs17-x64\php.exe`; Node pada `C:\laragon\bin\nodejs\node-v22`. Server lokal hanya terikat ke loopback.

## Demo

Login `mahasiswa@demo.brudel.test` / `BrudelDemo!2026`. Akun untuk kesembilan peran (kemahasiswaan, pengurus, admin event, prodi, penilai, pembina, pimpinan, operator) berada di [panduan pengujian](docs/TESTING.md). Data demo sengaja diberi label; tidak ada rekening atau pembayaran yang dibuat; DemoSeeder menolak produksi dan DatabaseSeeder tidak memuat fixture.

Menu **Status Fitur** di aplikasi merangkum fitur yang berjalan, yang belum diverifikasi operasional, dan pekerjaan tersisa.

## Validasi

```sh
php artisan test --compact
npm run build
php artisan view:cache
```

102 tes lulus di PostgreSQL 17.11 (termasuk uji konkurensi dua proses); di SQLite 98 lulus dan 4 tes khusus PostgreSQL dilewati (16 Oktober 2026). Hasil, cara menjalankan di PostgreSQL, trial lokal dengan `TrialSeeder`, serta batas pengujian dijelaskan pada [TESTING.md](docs/TESTING.md). S3 nyata, email, Docker/VPS dan instalasi PWA perangkat belum diverifikasi.

## Dokumentasi

- [Arsitektur, ERD, matriks peran, alur](docs/ARCHITECTURE.md)
- [Status fitur, asumsi yang dapat diubah, keterbatasan](docs/STATUS.md)
- [Akun demo dan pengujian](docs/TESTING.md)
- [VPS, S3, email, queue, scheduler, backup/restore](docs/DEPLOYMENT.md)

Referensi implementasi: [Laravel installation](https://laravel.com/framework/docs/13.x/installation), [Livewire installation](https://livewire.laravel.com/docs/4.x/installation). Brand dan ikon dari ZIP pengguna; ikon navigasi ditulis sebagai SVG sederhana.

