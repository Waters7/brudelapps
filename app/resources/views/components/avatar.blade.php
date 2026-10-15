@props(['user', 'size' => 'small'])
@if($user->photo_path)
<img {{ $attributes->merge(['class' => 'avatar avatar-photo '.$size]) }} src="/foto/{{ $user->id }}?v={{ substr(md5($user->photo_path), 0, 8) }}" alt="Foto {{ $user->name }}" loading="lazy" width="36" height="36">
@else
<span {{ $attributes->merge(['class' => 'avatar '.$size]) }} aria-hidden="true">{{ $user->initial() }}</span>
@endif
