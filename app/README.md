# BRUDEL · Poltekkes Kemenkes Manado

**Bakat, Rekognisi, Unjuk Diri, dan Eksplorasi Lomba**  
Sportif Berkompetisi, Unggul Berprestasi.

Tahap pertama yang dapat dijalankan: UKM dan pendaftaran lomba gratis individu/tim. Baca [status](docs/STATUS.md) untuk fitur tersedia, yang belum diuji, dan yang belum dibangun. Aplikasi belum siap produksi.

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

Pada komputer ini semuanya telah diinstal. Dari folder induk jalankan `./Start-BRUDEL.ps1`. PHP Laragon ditemukan pada `C:\laragon\bin\php\php-8.4.25-Win32-vs17-x64\php.exe`. Server lokal hanya terikat ke loopback.

## Demo dan rekomendasi

Login `mahasiswa@demo.brudel.test` / `BrudelDemo!2026`. Akun pengurus, anggota tim, dan admin berada di [panduan pengujian](docs/TESTING.md). Data demo sengaja diberi label; DemoSeeder menolak produksi dan DatabaseSeeder tidak memuat fixture.

Menu **Rekomendasi Fitur** menampilkan tahapan: fondasi & partisipasi → kesiapan operasional → kompetisi & rekognisi → dampak & pengembangan. Pembayaran, persetujuan prodi, penilaian, live score, delegasi, sertifikat dan prestasi belum aktif.

## Validasi

```sh
php artisan test --compact
npm run build
php artisan view:cache
```

18 tes / 105 assertions lulus di SQLite pada pemeriksaan awal. Hasil serta batas pengujian dijelaskan pada [TESTING.md](docs/TESTING.md). Penguncian PostgreSQL, S3 nyata, email, Docker/VPS dan instalasi PWA perangkat belum diverifikasi.

## Dokumentasi

- [Arsitektur, ERD, matriks peran, alur](docs/ARCHITECTURE.md)
- [Status dan peta jalan](docs/STATUS.md)
- [Akun demo dan pengujian](docs/TESTING.md)
- [VPS, S3, email, queue, scheduler, backup/restore](docs/DEPLOYMENT.md)

Referensi implementasi: [Laravel installation](https://laravel.com/framework/docs/13.x/installation), [Livewire installation](https://livewire.laravel.com/docs/4.x/installation). Brand dan ikon dari ZIP pengguna; ikon navigasi ditulis sebagai SVG sederhana.

