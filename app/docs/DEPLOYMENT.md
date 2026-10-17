# Konfigurasi VPS dan operasional

**Rancangan deployment, belum diuji.** Docker tidak tersedia pada mesin pengembangan ini. Tidak ada VPS, domain, bucket, rekening atau kredensial SMTP yang diberikan; belum dilakukan deployment maupun pengiriman email. Jangan memakai database demo untuk produksi.

## Lingkungan produksi

Gunakan PHP 8.4 FPM, Nginx, PostgreSQL, Composer lockfile dan hasil build Vite. Document root harus `public/`, bukan root repository. `storage/` dan `bootstrap/cache/` perlu dapat ditulis oleh user aplikasi. Larang akses `.env`, direktori vendor dan file di luar public. Buat secret baru untuk produksi.

Konfigurasi minimum `.env`:

```dotenv
APP_NAME="BRUDEL Poltekkes Kemenkes Manado"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://DOMAIN-RESMI
APP_KEY=KUNCI-HASIL-KEY-GENERATE
APP_LOCALE=id
BRUDEL_DEMO=false
DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=brudel
DB_USERNAME=brudel
DB_PASSWORD=RAHASIA-KUAT
SESSION_DRIVER=database
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
CACHE_STORE=database
QUEUE_CONNECTION=database
BRUDEL_DOCUMENT_DISK=s3
AWS_ACCESS_KEY_ID=ISI-DARI-PENGELOLA
AWS_SECRET_ACCESS_KEY=ISI-DARI-PENGELOLA
AWS_DEFAULT_REGION=ISI-REGION
AWS_BUCKET=ISI-BUCKET-PRIVAT
MAIL_MAILER=smtp
MAIL_HOST=ISI-HOST
MAIL_PORT=587
MAIL_USERNAME=ISI-USERNAME
MAIL_PASSWORD=ISI-RAHASIA
MAIL_FROM_ADDRESS=EMAIL-RESMI
MAIL_FROM_NAME="BRUDEL"
BRUDEL_COMPETITION_ACCOUNT_DOMAIN=lomba.brudel.invalid
GOOGLE_CLIENT_ID=ISI-DARI-GOOGLE-CLOUD
GOOGLE_CLIENT_SECRET=ISI-DARI-GOOGLE-CLOUD
GOOGLE_REDIRECT_URI=https://DOMAIN-RESMI/auth/google/callback
GOOGLE_ALLOWED_DOMAIN=DOMAIN-EMAIL-KAMPUS
TURNSTILE_SITE_KEY=ISI-DARI-CLOUDFLARE
TURNSTILE_SECRET_KEY=ISI-DARI-CLOUDFLARE
PANGI_BASE_URL=ALAMAT-PANGI
PANGI_TOKEN=ISI-DARI-PENGELOLA-PANGI
```

Nilai kapital adalah placeholder, bukan kredensial nyata.

### Google, Cloudflare Turnstile, dan akun lomba

- **Google SSO**: buat OAuth Client (Web application) di Google Cloud Console dengan *Authorized redirect URI* `https://DOMAIN-RESMI/auth/google/callback`. Google hanya menautkan akun yang sudah ada di BRUDEL (hasil impor), tidak pernah membuat akun baru. `GOOGLE_ALLOWED_DOMAIN` membatasi ke email kampus; kosongkan bila mahasiswa memakai Gmail pribadi yang terdaftar. Biarkan kosong untuk menyembunyikan tombol.
- **Cloudflare Turnstile**: buat widget untuk domain resmi; isi site key dan secret key. Widget otomatis muncul di login, aktivasi, dan lupa kata sandi. Kosongkan keduanya untuk menonaktifkan (mis. lokal). Server memerlukan akses keluar ke `challenges.cloudflare.com`.
- **Banner halaman depan**: video slide maksimal 20 MB. Pastikan `upload_max_filesize` ≥ 20M dan `post_max_size` ≥ 25M di PHP, serta `client_max_body_size 25m` di Nginx. File disajikan publik dengan cache 1 hari; URL berisi versi sehingga perubahan langsung terlihat.
- **Pangi (perjadin mahasiswa)**: menu Perjadin Delegasi sudah menyiapkan data; `PANGI_BASE_URL` dan `PANGI_TOKEN` disediakan untuk integrasi berikutnya dan belum dipakai untuk mengirim data.
- **Superadmin pertama**: setelah migrasi, jalankan `php artisan brudel:buat-admin admin` (kata sandi ditanyakan tersembunyi, minimal 10 karakter berisi huruf dan angka; tambahkan `--email=` untuk pemulihan). Akun masuk dengan username dan menjadi Kemahasiswaan Direktorat selama 5 tahun. Perintah yang sama mengatur ulang kata sandi bila lupa. Jangan menulis kata sandi admin di repository atau berkas yang di-commit.
- **Impor mahasiswa SIAKAD**: unggah berkas `laporan_daftarmhs.xls` ke server (di luar repository), jalankan pratinjau `php artisan brudel:impor-siakad /path/*.xls`, lalu ulangi dengan `--commit`. Kata sandi awal = tanggal lahir DDMMYYYY dan wajib diganti saat masuk pertama (halaman /sandi-baru); karena tanggal lahir mudah ditebak, umumkan agar mahasiswa segera masuk dan mengganti kata sandi, atau aktifkan masuk dengan Google. Hapus berkas ekspor dari server setelah impor karena memuat NIK dan data keluarga.
- **Ketua organisasi awal**: setelah impor SIAKAD, jalankan `php artisan brudel:impor-pengurus /path/ketua.csv` (pratinjau) lalu ulangi dengan `--commit`. Kolom CSV: organisasi, nama, nim, prodi, jabatan, email (sama dengan unggahan "Impor ketua" di /kelola/penugasan). Akun dicocokkan lewat NIM sehingga ketua memakai akun SIAKAD-nya. Paket data awal (15 ekspor SIAKAD, CSV ketua 2026, dan `impor-data-awal.sh` yang menjalankan keduanya berurutan) disimpan di `data-privat/data-awal/` pada komputer pengembang, di luar repository.
- **Pengaturan Sistem**: nama aplikasi, domain akun lomba, SMTP, Google SSO, Turnstile, dan Pangi dapat diubah dari menu Pengaturan Sistem; nilainya disimpan di tabel `settings` (rahasia terenkripsi `APP_KEY`) dan menimpa `.env`. Database, `APP_KEY`, debug, penyimpanan, sesi, dan queue tetap hanya di `.env`. Bila pengaturan menu membuat login gagal (mis. kunci Turnstile salah), jalankan `php artisan brudel:pengaturan-sistem --reset=all` di server. Merotasi `APP_KEY` membuat rahasia dari menu tidak terbaca (kembali ke `.env`); isi ulang setelah rotasi.
- **Master Data**: daftar pilihan (bidang, jenis kegiatan, lokasi, jenis lomba, jabatan, penghargaan, dokumen pendaftaran) diisi migrasi dengan nilai awal yang sama dengan sebelumnya; lengkapi lokasi kampus dan jenis lomba resmi setelah deploy.
- **Data awal ketua organisasi**: unggah CSV di Penugasan Peran → Impor ketua & pengurus organisasi (pratinjau dulu). Jangan simpan CSV berisi nama/NIM mahasiswa di repository. Ketua tanpa email baru dapat aktivasi setelah impor resmi mahasiswa (NIM sama) mengisi email kampusnya. Periksa prodi "Belum dipetakan" di Pengaturan Kampus setelah impor.
- **Akun lomba otomatis**: kata sandinya dienkripsi dengan `APP_KEY`. Merotasi `APP_KEY` membuat kata sandi tersimpan tidak terbaca; setelah rotasi, ganti kata sandi akun lomba lewat halaman Akun lomba.
- **Jurusan**: migrasi `2026_10_16_000001` membuat 7 jurusan dan memetakan prodi lama berdasarkan nama. Periksa Pengaturan Kampus setelah migrasi; prodi yang "Belum dipetakan" harus dipilihkan jurusannya sebelum persetujuan lomba per jurusan dipakai. Jangan menjalankan konfigurasi placeholder. Untuk hosting non-Docker, ganti `DB_HOST` sesuai jaringan privat.

## Opsi Docker Compose

Dockerfile membangun frontend dan runtime PHP. Target `web` menyalin aset publik ke Nginx; target `runtime` digunakan aplikasi, queue, scheduler. Compose menyediakan PostgreSQL dengan volume bernama. DB tidak diekspos ke host. Nginx terikat hanya `127.0.0.1:8080` dan memerlukan reverse proxy HTTPS pada VPS. Jangan mengekspos FPM ke internet.

```sh
docker compose build
docker compose up -d db
docker compose run --rm app php artisan key:generate --show
# Simpan hasilnya sebagai APP_KEY di .env tanpa membagikannya.
docker compose run --rm app php artisan migrate --force
docker compose up -d app web queue scheduler
```

`key:generate --show` hanya perlu untuk kunci produksi baru. Jangan merotasi kunci tanpa rencana migrasi data terenkripsi. Jangan jalankan DemoSeeder. `migrate --force` hanya setelah backup dan pemeriksaan migrasi pada staging. Pada deploy berikutnya bangun image baru, jalankan migrasi yang aman, recreate worker; hindari menghapus volume PostgreSQL.

Reverse proxy harus mempertahankan Host dan mengatur `X-Forwarded-Proto: https`. Nginx internal mempercayai header ini karena port hanya loopback; jangan membuka port internal ke pihak lain. Konfigurasikan TLS domain resmi dan batasi trafik masuk. Uji redirect, session cookie secure, URL asset HTTPS, dan unduhan sebelum membuka akses pengguna.

Jika image dibangun pada CI, tambahkan `php artisan config:cache` pada tahap rilis setelah environment final tersedia, bukan saat build tanpa secret. Migrasi dilakukan satu kali per rilis. Sesi/cache berbasis PostgreSQL memungkinkan beberapa proses. Log Laravel masih lokal container; sambungkan kanal stderr atau logging terpusat dan atur retensi.

## S3

Aktifkan Block Public Access dan enkripsi bucket. Identitas aplikasi hanya boleh membaca/menulis prefix dokumen yang diperlukan. Tidak ada ACL publik atau symlink storage publik untuk dokumen. Aplikasi menggunakan download streaming dari server setelah otorisasi; tidak mengembalikan path bucket kepada mahasiswa lain.

Gunakan bucket berbeda untuk staging dan produksi. Uji unggah PDF, unduh sebagai penerima, larangan unduh akun lain, kegagalan storage, dan pemulihan versi. Retensi dokumen lama, pemindaian malware, antivirus/karantina dan kebijakan penghapusan perlu diselesaikan sebelum produksi. Upload PDF hanya validasi tipe/ekstensi/ukuran, bukan pemindaian malware.

## Email, queue, scheduler

SMTP harus memakai akun resmi dan TLS sesuai provider, dengan SPF/DKIM/DMARC domain kampus. `MAIL_MAILER=log` pada demo tidak mengirim keluar (tautan tertulis di log). Email aktivasi akun dan pemulihan kata sandi dikirim melalui queue; tautan aktivasi berlaku 3 hari, pemulihan 60 menit, satu tautan aktif per email. `APP_URL` wajib berisi domain HTTPS resmi karena dipakai untuk membuat tautan. Pengiriman email nyata belum diuji.

Notifikasi dalam aplikasi (keputusan, undangan, pembayaran, sertifikat, penugasan) juga melalui queue, sehingga worker wajib berjalan.

Queue menggunakan tabel Laravel `jobs` dan `failed_jobs`; Compose menjalankan worker dengan timeout 60 detik (lebih kecil dari retry_after 90 detik), tiga percobaan. Pada VPS tanpa Docker gunakan Supervisor/systemd untuk `php artisan queue:work --sleep=3 --tries=3 --timeout=60`; restart worker setiap deploy. Tinjau `queue:failed` sebelum retry, jangan mengulang side effect tanpa idempotensi.

Scheduler pada Compose menggunakan `schedule:work`; tanpa Docker gunakan cron tiap menit `php /path/brudel/artisan schedule:run`. Jangan menjalankan keduanya sekaligus. Pekerjaan terjadwal: `brudel:expire-invitations` (tiap jam, melepas undangan tim yang tidak ditanggapi setelah pendaftaran ditutup) dan `queue:prune-failed` (harian, 30 hari).

Rekening resmi, kebijakan pembatalan/pengembalian dana, definisi regional, dan awalan nomor sertifikat diatur dari menu Pengaturan Kampus oleh kemahasiswaan setelah deploy, bukan dari `.env`.

## Backup dan pemulihan

1. Jadwalkan `pg_dump --format=custom` ke tujuan backup terenkripsi dengan retensi kampus; simpan secret melalui mekanisme aman, bukan argumen shell.
2. Aktifkan versioning S3, backup konfigurasi infrastruktur, dan simpan APP_KEY di pengelola secret dengan kontrol akses terpisah.
3. Uji pemulihan berkala ke database staging kosong menggunakan `pg_restore --no-owner`, jangan langsung menimpa produksi. Cocokkan versi schema dan image aplikasi.
4. Uji jumlah record, FK, login, status pendaftaran, dokumen dan audit. Catat lama pemulihan serta rentang kehilangan data; RPO/RTO ditetapkan kampus.
5. Pada insiden, hentikan mutasi, backup keadaan terkini, pulihkan staging dan verifikasi sebelum mengalihkan trafik. Pertahankan audit dan versi dokumen.

Jangan mengklaim backup berhasil sampai restore diuji. Volume Compose sendiri bukan backup.

## Gerbang sebelum go-live

Selesaikan fitur operasional pada STATUS.md, audit akses setiap peran, uji race PostgreSQL, uji bucket dan email, install PWA pada perangkat, cek cache setelah logout, verifikasi tidak ada akun demo, scan dependency/image, matikan debug, uji pemulihan, tetapkan admin dan prosedur koreksi. Rilis produksi memerlukan data resmi dan keputusan kebijakan dari kampus.

