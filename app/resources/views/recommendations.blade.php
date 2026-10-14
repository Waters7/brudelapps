<x-layout title="Rekomendasi Fitur"><div class="page-heading"><div><p class="eyebrow">ARAH PENGEMBANGAN BRUDEL</p><h1>Lebih jauh bersama BRUDEL.</h1><p>Prioritas fitur untuk perjalanan mahasiswa, dari minat hingga rekognisi.</p></div><span class="badge status-submitted">Peta jalan pengembangan</span></div><div class="roadmap-intro"><x-icon name="spark"/><div><h2>Mulai dari alur yang paling dibutuhkan.</h2><p>Tahap pertama berfokus pada UKM dan pendaftaran lomba gratis. Fitur lanjutan di bawah adalah rekomendasi, belum tersedia untuk digunakan.</p></div></div>
@php($groups=[
['01','Fondasi & partisipasi','Tersedia pada tahap ini','accepted',[
['users','UKM & keanggotaan','Katalog, rekrutmen, motivasi, tinjauan beralasan, daftar tunggu, dan UKM Saya.'],
['trophy','Pendaftaran lomba','Lomba gratis individu/tim, draf, undangan NIM, konfirmasi anggota, dan penguncian peserta.'],
['file','Dokumen & akses','PDF privat, pembatasan pengurus per UKM/event, audit keputusan, dan ekspor CSV.'],
['globe','Web responsif & PWA','Logo resmi, ikon instalasi, halaman offline aman, dan preferensi publikasi profil.']]],
['02','Kesiapan operasional','Prioritas berikutnya','submitted',[
['user','Impor & aktivasi akun','Impor CSV resmi dengan laporan kesalahan per baris, aktivasi email, dan pemulihan kata sandi.'],
['calendar','Pengelolaan kegiatan','Form pengaturan UKM/rekrutmen, periode pengurus, pengajuan event, persetujuan kemahasiswaan, dan penugasan.'],
['check','Persetujuan prodi','Review sesuai unit; tim lintas prodi memerlukan persetujuan unit terkait.'],
['file','Pembayaran manual','Rekening resmi, nominal tersimpan, bukti transfer, verifikasi terpisah, serta kebijakan pengembalian.']]],
['03','Kompetisi & rekognisi','Direkomendasikan','draft',[
['trophy','Bagan & penilaian','Sistem gugur, unggulan, bye, rubrik berbobot 100%, konflik kepentingan, dan pengesahan hasil.'],
['clock','Live score','Operator sesuai event, penanda skor sementara/resmi, waktu pembaruan, dan deteksi konflik perubahan.'],
['users','Delegasi kampus','Usulan terpisah dari hasil seleksi, keputusan pimpinan, pembina, dan surat tugas PDF resmi.'],
['file','Sertifikat Saya','PDF per penerima, unduhan privat, verifikasi minimum, pencabutan, dan riwayat versi.']]],
['04','Dampak & pengembangan','Rekomendasi tambahan','draft',[
['spark','Portofolio prestasi','Prestasi internal/eksternal terverifikasi, enam tingkat, dan publikasi atas persetujuan mahasiswa.'],
['grid','Laporan pimpinan','Filter tahun, bidang, tingkat, prodi, UKM; penghargaan tim dihitung satu kali.'],
['calendar','Pengingat & kalender','Notifikasi tenggat melalui queue, pengingat konfirmasi tim, dan ekspor kalender kegiatan.'],
['book','Kesiapan & umpan balik','Checklist latihan delegasi, evaluasi kegiatan, aksesibilitas, dan panduan mahasiswa baru.']]]
])
@foreach($groups as [$number,$heading,$status,$color,$features])<section class="roadmap-group"><div class="section-heading"><div class="roadmap-heading"><span>{{ $number }}</span><h2>{{ $heading }}</h2></div><span class="badge status-{{ $color }}">{{ $status }}</span></div><div class="feature-grid">@foreach($features as [$icon,$name,$description])<article class="feature-card"><span class="icon-tile teal"><x-icon :name="$icon"/></span><h3>{{ $name }}</h3><p>{{ $description }}</p></article>@endforeach</div></section>@endforeach
<div class="alert warning">Status operasional: pengujian lokal dilakukan terpisah. Integrasi PostgreSQL, S3, email, queue, instalasi PWA di perangkat, dan deployment VPS harus diverifikasi sebelum produksi.</div></x-layout>
