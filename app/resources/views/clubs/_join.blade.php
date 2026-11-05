{{-- How the signed-in student stands with this organization: member, invited, waiting, or able to ask to join. --}}
@if($isMember)
<section class="panel"><div class="alert success">Kamu anggota {{ $club->name }}.</div></section>
@elseif($pendingJoin?->isInvitation())
<section class="panel"><h2>Undangan bergabung</h2>
    <p>{{ $pendingJoin->creator->name }} mengundangmu bergabung dengan {{ $club->name }}.</p>
    @if($pendingJoin->message)<p class="muted prewrap">{{ $pendingJoin->message }}</p>@endif
    <form class="inline-actions" method="post" action="/undangan-organisasi/{{ $pendingJoin->id }}">@csrf<button class="button primary" name="decision" value="accepted">Terima undangan</button><button class="button outline" name="decision" value="declined">Tolak</button></form>
</section>
@elseif($pendingJoin)
<section class="panel"><h2>Permintaan bergabung</h2>
    <div class="alert warning">Permintaanmu menunggu tinjauan pengurus.</div>
    <form method="post" action="/permintaan-gabung/{{ $pendingJoin->id }}/batal">@csrf<button class="text-link plain">Batalkan permintaan</button></form>
</section>
@elseif(auth()->user()->nim && ! $joinBlocked)
<section class="panel"><h2>Minta bergabung</h2>
    <p class="muted">Pengurus atau pembina akan meninjau permintaanmu. Kamu juga bisa bergabung lewat undangan dari ketua atau pembina{{ $club->category === 'himaju' ? ', atau admin jurusan' : '' }}.</p>
    <form method="post" action="/ukm/{{ $club->id }}/minta-gabung">@csrf
        <label>Pesan untuk pengurus (opsional)<textarea name="message" rows="3" maxlength="500" placeholder="Ceritakan alasanmu ingin bergabung."></textarea></label>
        <button class="button primary full">Kirim permintaan <x-icon name="arrow"/></button>
    </form>
</section>
@elseif(auth()->user()->nim && $club->category === 'himaju')
<section class="panel"><p class="muted">{{ $joinBlocked }}</p></section>
@endif
