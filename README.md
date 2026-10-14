# BRUDEL Poltekkes Kemenkes Manado

Aplikasi tahap pertama: **UKM dan pendaftaran lomba gratis**, sesuai prioritas yang dipilih. Logo dan ikon berasal dari dua ZIP yang disertakan. Seluruh data pada instalasi lokal adalah demonstrasi.

## Membuka aplikasi

Jalankan `./Start-BRUDEL.ps1` dari PowerShell, lalu buka http://127.0.0.1:8000. Server hanya menerima koneksi lokal. Dependensi, build, dan database demo sudah disiapkan pada komputer ini.

Jika port sedang terpakai: `./Start-BRUDEL.ps1 -Port 8001`.

Login mahasiswa: `mahasiswa@demo.brudel.test`, kata sandi `BrudelDemo!2026`.

- [Instalasi dan konfigurasi](app/README.md)
- [Arsitektur, ERD, hak akses](app/docs/ARCHITECTURE.md)
- [Fitur selesai, rekomendasi, dan keterbatasan](app/docs/STATUS.md)
- [Akun demo dan skenario uji](app/docs/TESTING.md)
- [Deployment VPS dan pemulihan](app/docs/DEPLOYMENT.md)

Menu **Rekomendasi Fitur** di aplikasi juga menampilkan prioritas pengembangan. Ini belum merupakan rilis siap produksi.
