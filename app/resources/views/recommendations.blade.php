<x-layout title="Status Fitur">
<div class="page-heading"><div><p class="eyebrow">STATUS PENGEMBANGAN BRUDEL</p><h1>Apa yang sudah bisa dipakai.</h1><p>Ringkasan jujur: fitur yang berjalan dan teruji otomatis, yang belum diverifikasi operasional, dan pekerjaan tersisa.</p></div><span class="badge status-submitted">Belum siap produksi</span></div>
@php($groups=[
['01','Berjalan & diuji otomatis','Fitur aplikasi','accepted',[
['user','Akun mahasiswa','Impor CSV dengan validasi per baris, aktivasi email, lupa kata sandi, status aktif/nonaktif, profil & persetujuan publikasi.'],
['users','UKM & keanggotaan','Profil UKM, periode rekrutmen, kuota, dokumen, revisi/daftar tunggu, anggota & divisi, status aktif/keluar/alumni, pengurus per periode, ekspor CSV.'],
['calendar','Event & nomor lomba','Pengajuan Ketua UKM, keputusan kemahasiswaan, nomor lomba dengan syarat, format, biaya, penilaian, live score, admin event berjangka.'],
['trophy','Pendaftaran individu & tim','Draf, undangan NIM, konfirmasi, hapus anggota, batal draf, kuota, persetujuan jurusan lintas jurusan, riwayat status.'],
['file','Pembayaran manual','Nominal tersimpan saat dikirim, rekening resmi, bukti transfer privat, verifikasi tanpa konflik kepentingan, kebijakan refund.'],
['check','Bagan & penilaian','Sistem gugur dengan unggulan dan bye, propagasi pemenang, koreksi beralasan; rubrik 100%, kelengkapan nilai, nilai sama, pengesahan & kunci.'],
['clock','Live score','Skor sementara vs resmi, waktu pembaruan, penanda koneksi terputus, polling berkala, versi untuk mencegah penimpaan.'],
['globe','Delegasi & surat tugas','Usulan dari hasil seleksi, keputusan pimpinan, surat tugas PDF privat, pencatatan hasil.'],
['file','Sertifikat Saya','PDF per penerima, unduhan privat, verifikasi publik minimum, penggantian & pencabutan dengan riwayat versi.'],
['spark','Prestasi & laporan','Enam tingkat IKU, prestasi per jurusan, prestasi eksternal terverifikasi, halaman publik atas persetujuan, dashboard pimpinan, penghargaan tim dihitung sekali, ekspor CSV sesuai hak.'],
['book','Jurusan','7 jurusan resmi; persetujuan lomba per jurusan; mahasiswa & UKM yang diikuti, yang tidak aktif di UKM, sertifikat juara dan prestasi jurusan sendiri.'],
['users','Akun lomba otomatis','Pengelola, operator skor/live score, dan 1–5 juri per lomba; hanya pengaju lomba dan superadmin yang melihat kata sandinya; log setiap nilai juri.'],
['calendar','Jelajah Kegiatan','Latihan, persahabatan, meeting internal; kode kehadiran dari pelatih/Ketua UKM; laporan hadir/tidak hadir, skor & catatan, notulen; cetak/PDF dan CSV.'],
['shield','Akun & keamanan','Halaman depan publik, menu akun dengan Keluar, ubah kata sandi, foto profil, masuk dengan Google, Cloudflare Turnstile.']]],
['02','Dibuat, belum diverifikasi operasional','Perlu lingkungan nyata','submitted',[
['grid','PostgreSQL produksi','Seluruh tes dan uji konkurensi dua proses lulus di PostgreSQL 17 lokal; belum dijalankan di staging Docker/VPS.'],
['globe','Google & Cloudflare','Alur Google SSO dan Turnstile diuji dengan respons tiruan; perlu kunci resmi kampus dan domain HTTPS.'],
['file','S3, email, queue','Adapter S3 privat, SMTP, notifikasi antre, dan scheduler sudah dikonfigurasi; belum tersambung layanan nyata.'],
['globe','PWA & VPS','Manifest, service worker aman, Dockerfile/Compose/Nginx tersedia; instalasi perangkat dan deployment belum diuji.']]],
['03','Pekerjaan tersisa','Rekomendasi','draft',[
['shield','Keamanan unggahan','Pemindai malware/karantina, kebijakan retensi dokumen lama, pemantauan error terpusat.'],
['calendar','Pengingat & kalender','Pengingat tenggat, konfirmasi tim, ekspor kalender, ekspor besar melalui queue.'],
['book','Aksesibilitas & panduan','Audit pembaca layar, panduan mahasiswa baru, evaluasi kegiatan, checklist kesiapan delegasi.'],
['users','Uji pengguna','Uji coba dengan data resmi, keputusan kebijakan kampus, dan pelatihan petugas tiap peran.']]],
])
@foreach($groups as [$number,$heading,$status,$color,$features])<section class="roadmap-group"><div class="section-heading"><div class="roadmap-heading"><span>{{ $number }}</span><h2>{{ $heading }}</h2></div><span class="badge status-{{ $color }}">{{ $status }}</span></div><div class="feature-grid">@foreach($features as [$icon,$name,$description])<article class="feature-card"><span class="icon-tile teal"><x-icon :name="$icon"/></span><h3>{{ $name }}</h3><p>{{ $description }}</p></article>@endforeach</div></section>@endforeach
<div class="alert warning">Jangan gunakan untuk produksi sebelum alur utama diuji dengan PostgreSQL, S3, email, queue, HTTPS, dan pemulihan backup pada lingkungan staging.</div>
</x-layout>
