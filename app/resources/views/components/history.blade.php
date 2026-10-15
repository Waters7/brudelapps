@props(['items'])
@if($items->isNotEmpty())
<details class="history">
    <summary>Riwayat status ({{ $items->count() }})</summary>
    <ol>
        @foreach($items as $item)
            <li>
                <span class="history-time">{{ \App\Services\Wita::show($item->created_at, 'd M Y · H:i') }} WITA</span>
                <span>@if($item->from_status)<x-status :value="$item->from_status"/> → @endif<x-status :value="$item->to_status"/></span>
                @if($item->reason)<small>{{ $item->reason }}</small>@endif
            </li>
        @endforeach
    </ol>
</details>
@endif
