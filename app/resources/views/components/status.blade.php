@props(['value'])
@php($labels=['submitted'=>'Menunggu tinjauan','accepted'=>'Diterima','rejected'=>'Ditolak','revision'=>'Perlu revisi','waitlisted'=>'Daftar tunggu','draft'=>'Draf','confirmed'=>'Terkonfirmasi','invited'=>'Menunggu konfirmasi','active'=>'Aktif'])
<span class="badge status-{{ $value }}">{{ $labels[$value] ?? $value }}</span>
