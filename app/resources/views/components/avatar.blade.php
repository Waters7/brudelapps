@props(['user', 'size' => 'small'])
@if($user->profile_frame === 'hkn-62-v1')
<span {{ $attributes->merge(['class' => 'avatar '.$size]) }} style="position:relative;overflow:visible;background:transparent" role="img" aria-label="{{ $user->name }}, frame Finisher HKN ke-62">
    @if($user->photo_path)
    <img src="/foto/{{ $user->id }}?v={{ substr(md5($user->photo_path), 0, 8) }}" alt="" loading="lazy" style="position:absolute;inset:12%;width:76%;height:76%;border-radius:50%;object-fit:cover">
    @else
    <span style="position:absolute;inset:12%;display:grid;place-items:center;border-radius:50%;background:#e0f2ed">{{ $user->initial() }}</span>
    @endif
    <img src="/games/hkn62/frame.svg" alt="" style="position:absolute;inset:-8%;width:116%;height:116%;max-width:none;pointer-events:none">
</span>
@else
@if($user->photo_path)
<img {{ $attributes->merge(['class' => 'avatar avatar-photo '.$size]) }} src="/foto/{{ $user->id }}?v={{ substr(md5($user->photo_path), 0, 8) }}" alt="Foto {{ $user->name }}" loading="lazy" width="36" height="36">
@else
<span {{ $attributes->merge(['class' => 'avatar '.$size]) }} aria-hidden="true">{{ $user->initial() }}</span>
@endif
@endif
