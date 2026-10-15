@props(['value'])
@php($key = str_starts_with((string) $value, 'payment:') ? substr($value, 8) : (string) $value)
<span class="badge status-{{ $key }}">{{ str_starts_with((string) $value, 'payment:') ? 'Pembayaran: ' : '' }}{{ config('brudel.status_labels')[$key] ?? $key }}</span>
