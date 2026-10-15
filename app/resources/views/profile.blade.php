<x-layout title="Profil Saya">
<div class="page-heading"><div><p class="eyebrow">IDENTITAS & PREFERENSI</p><h1>Profil saya.</h1><p>Data identitas mengikuti data resmi kampus.</p></div></div>
<section class="panel narrow">
    <div class="profile-top"><span class="avatar big">{{ mb_substr($user->name,0,1) }}</span><div><h2>{{ $user->name }}</h2><x-status value="active"/></div></div>
    <dl><dt>NIM</dt><dd>{{ $user->nim ?? 'Akun pegawai' }}</dd><dt>Email</dt><dd>{{ $user->email }}</dd><dt>Program studi</dt><dd>{{ $user->program?->name ?? 'Tidak berlaku' }}</dd><dt>Zona waktu</dt><dd>WITA · Asia/Makassar</dd>
    <dt>Peran aktif</dt><dd>@forelse($user->currentAssignments()->with('club', 'event', 'program', 'competition')->get() as $a)<span class="chip">{{ config('brudel.roles')[$a->role]['label'] ?? $a->role }} · {{ $a->scopeLabel() }} · s.d. {{ \App\Services\Wita::show($a->ends_at, 'd M Y') }}</span>@empty Mahasiswa @endforelse</dd></dl>
    @if($user->nim)
    <hr>
    <form method="post" action="/profil">@csrf
        <label class="checkbox-label"><input type="hidden" name="publication_consent" value="0"><input type="checkbox" name="publication_consent" value="1" @checked($user->publication_consent)><span>Saya menyetujui publikasi prestasi terverifikasi pada profil publik BRUDEL.</span></label>
        <p class="form-note">Profil publik hanya menampilkan nama, prodi, dan prestasi terverifikasi. NIM, dokumen persyaratan, dan bukti pembayaran tidak pernah ditampilkan. Persetujuan dapat dicabut kapan saja.</p>
        <button class="button primary">Simpan preferensi</button>
    </form>
    @if($user->publication_consent && $user->public_slug)<p class="form-note">Profil publik: <a class="text-link" href="/mahasiswa/{{ $user->public_slug }}">{{ url('/mahasiswa/'.$user->public_slug) }}</a></p>@endif
    @endif
</section>
</x-layout>
