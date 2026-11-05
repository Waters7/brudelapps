@props(['items', 'action' => null])
{{-- Dosen and tendik accompanying an activity or competition; $action is set only for those who may edit the list. --}}
<section class="panel">
    <h2>Dosen & tendik pendamping <span class="count-pill">{{ $items->count() }}</span></h2>
    <ul class="plain-list">
        @forelse($items as $companion)
        <li class="list-row"><span>{{ $companion->name }}<small>{{ $companion->kindLabel() }}@if($action && $companion->identifier) · {{ $companion->identifier }}@endif</small></span>
            @if($action)<form method="post" action="/pendamping/{{ $companion->id }}/hapus">@csrf<button class="text-link plain">Hapus</button></form>@endif</li>
        @empty
        <li class="muted">Belum ada pendamping.</li>
        @endforelse
    </ul>
    @if($action)
    <form class="form-grid" method="post" action="{{ $action }}">@csrf
        <label>Nama lengkap dan gelar<input name="name" required minlength="3" maxlength="120" value="{{ old('name') }}"></label>
        <label>Status<select name="kind" required>@foreach(\App\Models\Companion::KINDS as $value => $label)<option value="{{ $value }}" @selected(old('kind') === $value)>{{ $label }}</option>@endforeach</select></label>
        <label>NIP/NIDN (opsional)<input name="identifier" maxlength="40" value="{{ old('identifier') }}"></label>
        <div class="span-2"><button class="button outline">Tambah pendamping</button></div>
    </form>
    @endif
</section>
