# Status dan rekomendasi fitur

Tanggal penilaian: 15 Oktober 2026 (WITA). Prioritas pengguna: UKM dan pendaftaran lomba. Versi ini adalah tahap pertama yang dapat dijalankan lokal, bukan seluruh cakupan brief dan bukan siap produksi.

## Selesai dan diuji lokal

- Brand BRUDEL dari aset pengguna; halaman login, dashboard, katalog UKM/lomba, detail, keanggotaan, pendaftaran, tinjauan, profil, dan peta jalan.
- Laravel/Livewire/Blade/Tailwind dengan lockfile dependensi.
- Login/logout session, akun aktif/teraktivasi, pembatasan login, CSRF framework, cache pribadi no-store.
- Filter katalog interaktif dan pencarian nama.
- Rekrutmen UKM gratis, validasi periode, motivasi, larangan duplikat/anggota aktif, keputusan/revisi/alasan/daftar tunggu, penerimaan menjadi anggota.
- Akun rangkap peran dan penugasan UKM/event berjangka; penolakan tinjauan diri/tim sendiri.
- Lomba gratis individu/tim: draf, nama tim, NIM, konfirmasi/penolakan undangan, jumlah anggota, kuota, status mahasiswa, persyaratan PDF, penguncian roster, tinjauan dan revisi.
- Dokumen privat: batas 5 MB, PDF, unduhan hanya pemilik atau pengelola event terkait.
- Audit mutasi dan CSV sesuai lingkup; perlindungan formula injection CSV.
- Persetujuan publikasi disimpan, belum ada profil publik.
- SQLite demo terpisah secara konfigurasi dan seeder; data demo diberi label dan tidak di-seed oleh DatabaseSeeder produksi.

## Dibuat, belum diverifikasi operasional

- Skema migration kompatibel API PostgreSQL, row lock dan transaksi: baru diuji SQLite; belum diuji pada PostgreSQL atau beban serentak.
- Adapter S3, konfigurasi privat: belum terhubung bucket nyata atau scanner malware.
- Manifest, ikon, service worker, halaman offline: fallback offline telah diamati saat server berhenti; instalasi perangkat dan siklus upgrade cache belum diuji.
- Dockerfile, Compose, Nginx dan petunjuk VPS: belum dibangun/dijalankan dengan Docker atau dideploy.
- Konfigurasi SMTP, queue dan scheduler: belum terhubung layanan nyata.

## Prioritas 1 — sebelum penggunaan kampus

1. Impor data resmi: preview, validasi NIM/email/prodi/duplikasi, rollback, hasil per baris; aktivasi email dan pemulihan kata sandi.
2. CRUD UKM, rekrutmen, anggota/divisi, periode pengurus, event/nomor lomba, persetujuan event, penugasan dan pengaturan kampus. Saat ini data katalog/periode berasal dari seeder atau pengelolaan database oleh pengembang; belum ada UI pengaturan.
3. Review prodi termasuk tim lintas unit; isolasi akses prodi beserta tesnya. Fitur yang mewajibkan persetujuan prodi sengaja belum menerima pendaftaran.
4. Pembayaran manual: rekening resmi, nominal terkunci, bukti, verifikasi independen, kebijakan batal/refund. Lomba berbayar sengaja belum menerima pendaftaran.
5. Pembatalan draf, penggantian anggota sebelum penguncian, pemberitahuan revisi, riwayat status yang dapat dilihat mahasiswa.
6. Queue notifikasi dan ekspor besar, paginasi, sanitasi file dengan scanner, retensi dokumen lama, pemantauan error, backup/restore aktual, pengujian concurrent PostgreSQL.

## Prioritas 2 — jalannya kompetisi

Bagan gugur/seed/bye dan propagasi pemenang; rubrik bobot 100%, penugasan penilai, kelengkapan nilai, tie-break, pengesahan; live score sementara/final dan deteksi konflik update; usulan delegasi/keputusan pimpinan/surat tugas.

## Prioritas 3 — rekognisi dan laporan

Sertifikat Saya PDF resmi per penerima dengan verifikasi minimum dan riwayat pencabutan; prestasi eksternal terverifikasi dan enam tingkat; publikasi profil berdasarkan consent; laporan pimpinan dengan perhitungan penghargaan tim tidak berganda.

## Rekomendasi tambahan

Pengingat tenggat, kalender kegiatan, checklist kesiapan delegasi, evaluasi kegiatan, panduan mahasiswa baru, dan audit aksesibilitas. Prioritaskan kebutuhan operasional dan keamanan sebelum personalisasi atau rekomendasi otomatis.

## Batasan yang disengaja

Tidak ada rekening atau pembayaran fiktif; tidak ada tombol modul mendatang yang berpura-pura berfungsi. Semua kegiatan demo bersifat ilustratif. Upload PDF belum membuktikan file bebas malware. Undangan yang tidak ditanggapi masih menahan keikutsertaan di nomor yang sama sampai ditolak; pembersihan undangan kadaluarsa direncanakan. Dokumen yang diganti masih disimpan privat untuk menghindari kehilangan tanpa kebijakan retensi; job pembersihan belum ada. Ekspor sinkron dan listing belum dipaginasi.


