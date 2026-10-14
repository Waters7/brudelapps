@props(['name'=>'grid'])
<svg {{ $attributes->merge(['class'=>'icon']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
@switch($name)
@case('grid') <rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/> @break
@case('users') <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m20 0v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/><circle cx="9" cy="7" r="4"/> @break
@case('trophy') <path d="M8 3h8v6a4 4 0 0 1-8 0V3ZM8 5H4v3a4 4 0 0 0 4 4m8-7h4v3a4 4 0 0 1-4 4m-4 1v5m-5 3h10m-8-3h6v3"/> @break
@case('file') <path d="M14 2H5v20h14V7Zm0 0v5h5M8 12h8m-8 4h6"/> @break
@case('arrow') <path d="M4 12h16m-6-6 6 6-6 6"/> @break
@case('calendar') <rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18m-14 4h2m4 0h2"/> @break
@case('check') <path d="m5 12 4 4L19 6"/> @break
@case('clock') <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/> @break
@case('search') <circle cx="10" cy="10" r="6"/><path d="m15 15 6 6"/> @break
@case('logout') <path d="M9 21H3V3h6m6 5 4 4-4 4m-8-4h12"/> @break
@case('user') <circle cx="12" cy="8" r="4"/><path d="M4 22v-2a8 8 0 0 1 16 0v2"/> @break
@case('racket') <ellipse cx="14" cy="8" rx="6" ry="7" transform="rotate(35 14 8)"/><path d="m10 14-7 8m2-5 3 3M12 3l6 8m-8-4 6 8m-7-5 9-6"/> @break
@case('music') <path d="M9 18V5l11-2v13M9 8l11-2"/><ellipse cx="6" cy="18" rx="3" ry="3"/><ellipse cx="17" cy="16" rx="3" ry="3"/> @break
@case('book') <path d="M12 5v16M2 3c4-1 7 0 10 2 3-2 6-3 10-2v16c-4-1-7 0-10 2-3-2-6-3-10-2Z"/> @break
@case('ball') <circle cx="12" cy="12" r="10"/><path d="m12 7 5 4-2 6H9l-2-6Zm0 0V2m5 9 4-3m-6 9 3 3m-9-3-3 3m1-9L3 8"/> @break
@case('globe') <circle cx="12" cy="12" r="10"/><ellipse cx="12" cy="12" rx="4" ry="10"/><path d="M2 12h20"/> @break
@case('menu') <path d="M3 6h18M3 12h18M3 18h18"/> @break
@default <path d="m12 3 2.7 6.3L21 12l-6.3 2.7L12 21l-2.7-6.3L3 12l6.3-2.7ZM20 2v4m-2-2h4"/>
@endswitch
</svg>
