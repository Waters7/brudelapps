@props(['broadcast', 'full' => false])
{{-- Kemahasiswaan direktorat announcement: violet, unlike the teal announcements officers post in their own space. --}}
<article class="relative overflow-hidden rounded-2xl border border-violet-200 border-l-4 border-l-violet-600 bg-gradient-to-br from-violet-50 to-white p-5 shadow-sm">
    <div class="flex flex-wrap items-center gap-2 text-xs">
        <span class="inline-flex items-center gap-1.5 rounded-full bg-violet-600 px-3 py-1 font-bold text-white [&_.icon]:size-3.5!"><x-icon name="megaphone"/> Kemahasiswaan Direktorat</span>
        <span class="rounded-full bg-white px-2.5 py-1 font-semibold text-violet-700 ring-1 ring-violet-200">Untuk {{ $broadcast->audienceLabel() }}</span>
        <span class="text-brand-ink/50">{{ \App\Services\Wita::show($broadcast->created_at, 'd M Y H:i') }} WITA</span>
    </div>
    <h3 class="mt-3 text-lg font-bold text-brand-ink">@if($full){{ $broadcast->title }}@else<a href="/pengumuman/{{ $broadcast->id }}" class="hover:text-violet-700">{{ $broadcast->title }}</a>@endif</h3>
    <p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-brand-ink/75">{{ $full ? $broadcast->body : \Illuminate\Support\Str::limit($broadcast->body, 280) }}</p>
    <p class="mt-3 text-xs text-brand-ink/50">Ditulis oleh {{ $broadcast->author->name }}@if($broadcast->expires_at) · tampil sampai {{ \App\Services\Wita::show($broadcast->expires_at, 'd M Y') }}@endif</p>
</article>
