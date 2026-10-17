<x-layout title="Bantuan">
<div class="page-heading"><div><p class="eyebrow">BANTUAN BRUDEL</p><h1>{{ auth()->user()->isStudentAffairs() || auth()->user()->assigned('jurusan') ? 'Kotak masuk bantuan.' : 'Hubungi kemahasiswaan.' }}</h1><p>Percakapan langsung dengan Kemahasiswaan Direktorat atau Kemahasiswaan Jurusan Anda. Pesan baru tampil otomatis tanpa memuat ulang halaman; tombol bantuan juga tersedia di pojok kanan bawah setiap halaman.</p></div></div>
<livewire:support-chat :page="true" />
</x-layout>
