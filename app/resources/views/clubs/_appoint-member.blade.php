<section class="panel">
    <h2>Tetapkan anggota terpilih</h2>
    <p class="muted">Anggota {{ $club->name }} berasal dari hasil pemilihan, bukan rekrutmen terbuka. Setiap penetapan dicatat beserta dasarnya.</p>
    <form class="form-grid" method="post" action="/kelola/ukm/{{ $club->id }}/anggota">@csrf
        <label>NIM/email mahasiswa<input name="user" required maxlength="190"></label>
        <label>Jabatan/komisi<input name="division" maxlength="80" placeholder="Mis. Menteri Kesehatan, Komisi A"></label>
        <label class="span-2">Dasar penetapan<input name="basis" required minlength="5" maxlength="500" placeholder="Mis. Hasil Pemilihan Raya Mahasiswa 2026"></label>
        <div class="span-2"><button class="button primary">Tetapkan sebagai anggota</button></div>
    </form>
</section>
