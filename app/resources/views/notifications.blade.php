<x-layout title="Notifikasi">
<div class="page-heading"><div><p class="eyebrow">PEMBERITAHUAN</p><h1>Notifikasi.</h1><p>Keputusan, undangan, pembayaran, sertifikat, dan penugasan untuk akun Anda.</p></div>
<form method="post" action="/notifikasi/baca">@csrf<button class="button outline">Tandai semua dibaca</button></form></div>
<section class="panel table-panel">
@forelse($notifications as $n)
<a class="notification-row {{ $n->read_at ? '' : 'unread' }}" href="{{ $n->data['url'] ?? '#' }}"><span class="activity-dot"></span><div><strong>{{ $n->data['title'] ?? 'Pemberitahuan' }}</strong><p>{{ $n->data['body'] ?? '' }}</p><small>{{ \App\Services\Wita::show($n->created_at, 'd M Y · H:i') }} WITA</small></div></a>
@empty<div class="empty">Belum ada notifikasi.</div>@endforelse
</section>
{{ $notifications->links() }}
</x-layout>
