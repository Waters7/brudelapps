# BRUDEL Poltekkes Kemenkes Manado

Aplikasi web responsif dan PWA untuk mengelola UKM, kompetisi, seleksi delegasi, sertifikat, dan prestasi mahasiswa. Logo dan ikon berasal dari dua ZIP yang disertakan. Seluruh data pada instalasi lokal adalah demonstrasi.

## Membuka aplikasi

Jalankan `./Start-BRUDEL.ps1` dari PowerShell, lalu buka http://127.0.0.1:8000. Skrip menjalankan migrasi, worker queue (notifikasi), scheduler, dan server yang hanya menerima koneksi lokal. Dependensi, build, dan database demo sudah disiapkan pada komputer ini.

Jika port sedang terpakai: `./Start-BRUDEL.ps1 -Port 8001`. Tanpa worker: `./Start-BRUDEL.ps1 -NoWorker`.

Login mahasiswa: `mahasiswa@demo.brudel.test`, kata sandi `BrudelDemo!2026`. Akun demo untuk setiap peran ada di panduan pengujian.

- [Instalasi dan konfigurasi](app/README.md)
- [Arsitektur, ERD, hak akses](app/docs/ARCHITECTURE.md)
- [Fitur selesai, asumsi, dan keterbatasan](app/docs/STATUS.md)
- [Akun demo dan skenario uji](app/docs/TESTING.md)
- [Deployment VPS dan pemulihan](app/docs/DEPLOYMENT.md)

Menu **Status Fitur** di aplikasi merangkum hal yang sama. Ini belum merupakan rilis siap produksi.
