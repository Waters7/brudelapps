# BRUDEL Run — HKN ke-62

Halaman `/games/hkn-62` (menu **HKN ke-62 · Run**, wajib masuk dengan akun pribadi aktif). Dibuat 7 November 2026.

## Aturan

- Side scrolling, lari otomatis, lompat dengan Spasi, ↑, sentuhan pada arena, atau tombol Lompat. P/Escape untuk jeda; pindah tab otomatis menjeda.
- Jarak **virtual** 62 km, sekitar 108 detik dengan jawaban langsung benar, ditambah waktu berpikir. Bukan data aktivitas fisik atau prestasi akademik.
- Tiga nyawa. Laju 9/11/13 unit per tick di tiga tahap. Empat trivia di km 12/26/40/54, masing-masing 12 detik. Salah/timeout mengurangi satu nyawa; tabrakan juga mengurangi nyawa dan menahan laju 1,5 detik. Penjelasan tampil 3 detik setelah jawaban, dihitung dalam waktu peringkat. Jeda tidak masuk waktu lomba, batas sesi satu jam.
- Setiap akun yang pertama kali finis mendapat **62 poin mini game BRUDEL**, badge **Finisher 62 KM — HKN ke-62**, dan hak memakai frame profil HKN. Hadiah satu kali per akun/event, termasuk bila respons jaringan dicoba ulang.
- Main ulang memperbaiki waktu terbaik. Peringkat diurutkan menurut waktu, lalu waktu pencatatan, lalu ID hadiah untuk pemecah seri. Lintasan dan trivia sama untuk semua pemain; waktu menjawab ikut menentukan peringkat. Pemain lain memakai alias, tanpa nama/NIM/email.
- Banner HKN Run tampil sebagai sorotan pertama halaman depan; tautan bermain tetap mewajibkan login akun pribadi. Dashboard menampilkan akses game dan total poin milik pengguna (mini game + misi UKM berstatus approved), dengan rincian sumber. Hadiah juga ditampilkan di Profil. Poin mini game dicatat di `game_rewards`, terpisah dari poin quest/kehadiran per organisasi agar tidak diduplikasi ke setiap organisasi yang diikuti.
- Frame bisa dipasang/dilepas dari Profil setelah finis. Tampilan avatar menggunakan lapisan SVG; berkas foto asli tidak diubah. Warna, tema, dan aksen frame mengikuti logo HKN yang diberikan pengguna; PNG logo asli disimpan tanpa perubahan.

## Pemasangan

Jalankan migrasi baru `2026_11_07_000001_create_hkn_game_tables` pada database target dengan proses rilis biasa (backup lebih dahulu). Migrasi menambah `game_runs`, `game_rewards`, dan kolom opsional `users.profile_frame`. Migrasi `2026_11_07_000002_add_hkn_rules_versions` memisahkan peringkat aturan v2, mempertahankan hadiah lama dan menolak percobaan lama yang belum selesai. Pemain lama harus finis lagi untuk masuk peringkat v2 tanpa memperoleh poin tambahan. Aset game sudah berupa JS/CSS/SVG di `public/games/hkn62`, tanpa dependensi frontend tambahan. Jalankan build Vite untuk perubahan banner halaman depan. Bersihkan cache tampilan/rute pada rilis.

Pengujian browser dilakukan pada database demo terpisah. Migrasi **belum** diterapkan ke database operasional/VPS oleh pekerjaan ini. Kode halaman Profil memakai tabel hadiah baru, sehingga migrasi wajib dijalankan sebelum membuka versi baru di database target.

## Validasi hadiah

Server menerbitkan token percobaan milik akun yang sedang masuk, mencatat waktu mulai, lalu menyimulasikan ulang lompatan, waktu trivia, dan jawaban dengan aturan integer 60 tick/detik. Jarak, nyawa, poin, dan badge dari browser tidak dipercaya. Sesi kedaluwarsa, token akun lain, catatan tidak urut, finis terlalu cepat, atau lintasan gagal ditolak. Membuka percobaan baru membatalkan percobaan lama yang belum selesai.

Pemberian hadiah memakai transaksi, kunci baris pengguna, dan keunikan `(user_id, game)`; pengiriman ulang tidak menggandakan poin. Semua endpoint mutasi memakai autentikasi, CSRF, dan pembatasan permintaan. Akun lomba otomatis tidak ikut memperoleh hadiah. Pemakaian frame diperiksa kembali di server.

Ini validasi hasil permainan, bukan bukti pemain manusia: bot masih dapat menyusun lompatan sah. Jangan menggunakan mini game ini sebagai dasar hadiah bernilai tinggi tanpa pengawasan tambahan. Durasi peringkat adalah waktu simulasi aktif, bukan latensi jaringan. Log percobaan hanya menyimpan seed/waktu/hasil, bukan seluruh rekaman tombol. Token selesai tetap dipertahankan untuk retry; kebijakan retensi dapat ditambahkan saat operasional.

## Pengujian

- `node tests/hkn-engine.cjs` memeriksa lima seed, kalah karena tabrakan, kondisi terminal, dan kesamaan hasil engine JS/PHP. Set `HKN_TEST_PHP` ke lokasi PHP jika tidak ada di PATH. Fixture hasilnya ada di `tests/Fixtures/hkn-replays.json`.
- `php vendor/bin/phpunit tests/Feature/HknGameTest.php`: akses akun, validasi waktu/replay, hadiah sekali saja, privasi peringkat, sesi pengganti/kedaluwarsa, dan frame terkunci/pasang/lepas.
- `PerformanceBudgetTest` dan `PageRenderTest`: regresi menu/avatar/profil untuk berbagai peran.
- Pratinjau HTML lokal berlabel latihan tidak memanggil API dan tidak memberi hadiah. Pengujian antarmuka berhadiah menggunakan halaman Laravel yang terautentikasi.

Engine JS dan PHP harus selalu diperbarui bersama dan lulus uji parity. Perubahan aturan lomba memerlukan kenaikan RULES_VERSION dan best_rules_version; identitas event tetap agar hadiah tidak diberikan dua kali.

## Sumber trivia

- CDC Clean Hands: https://www.cdc.gov/clean-hands/about/index.html
- CDC Clean Hands FAQ: https://www.cdc.gov/clean-hands/faq/index.html
- WHO Physical activity: https://www.who.int/news-room/fact-sheets/detail/physical-activity
- CDC Antibiotic Use: https://www.cdc.gov/antibiotic-use/about/index.html

Bank soal bersama: `public/games/hkn62/trivia.json`, dibaca browser dan server. Jawaban/penjelasan bersifat edukasi kesehatan umum.
