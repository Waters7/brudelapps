<x-layout title="Pengaturan Sistem">
<div class="page-heading"><div><p class="eyebrow">KEMAHASISWAAN DIREKTORAT</p><h1>Pengaturan sistem.</h1><p>Mengganti nilai <code>.env</code> yang aman diubah dari aplikasi. Nilai tersimpan di database, langsung berlaku, dan tercatat di audit. Kata sandi dan token disimpan terenkripsi dan tidak pernah ditampilkan kembali.</p></div></div>

<div class="alert warning">Database, <code>APP_KEY</code>, mode debug, penyimpanan berkas, sesi, dan queue tetap diatur di server: mengubahnya dari web dapat membuat akun admin yang dibobol mengambil alih server.</div>

<form method="post" action="/kelola/sistem">@csrf
@foreach(collect(\App\Services\Campus\SystemSettings::FIELDS)->groupBy('group', true) as $group => $fields)
<section class="panel">
    <h2>{{ $group }}</h2>
    <div class="form-grid">
    @foreach($fields as $key => $field)
        @php($current = $state[$key])
        <div class="system-field">
            <label>{{ $field['label'] }} <small class="muted">({{ $field['env'] }}{{ $current['overridden'] ? ' · diatur dari menu ini' : ' · dari .env' }})</small>
            @if($field['type'] === 'secret')
                <input type="password" name="settings[{{ $key }}]" autocomplete="new-password" maxlength="500" placeholder="{{ $current['set'] ? 'Tersimpan · isi untuk mengganti' : 'Belum diisi' }}">
            @elseif($field['type'] === 'select')
                <select name="settings[{{ $key }}]">@foreach($field['options'] as $value => $label)<option value="{{ $value }}" @selected((string) $current['value'] === (string) $value)>{{ $label }}</option>@endforeach</select>
            @else
                <input type="{{ ['number' => 'number', 'email' => 'email', 'url' => 'url'][$field['type']] ?? 'text' }}" name="settings[{{ $key }}]" value="{{ $current['value'] }}" maxlength="500">
            @endif
            </label>
            @if($field['help'] ?? null)<p class="form-note">{{ $field['help'] }}</p>@endif
            @if($current['overridden'])<label class="checkbox-label"><input type="checkbox" name="reset[]" value="{{ $key }}"><span>Kembalikan ke nilai .env</span></label>@endif
        </div>
    @endforeach
    </div>
    @if($group === 'Email')<p class="muted">Simpan dulu, lalu kirim email uji ke alamat Anda.</p>@endif
</section>
@endforeach
<div class="submit-bar"><span class="muted">Perubahan berlaku untuk permintaan berikutnya dan tercatat di audit.</span><button class="button primary">Simpan pengaturan sistem</button></div>
</form>

<section class="panel">
    <h2>Uji email</h2>
    <form class="inline-form" method="post" action="/kelola/sistem/uji-email">@csrf<button class="button outline">Kirim email uji ke {{ auth()->user()->email }}</button></form>
</section>

<section class="panel">
    <h2>Diatur di server (.env)</h2>
    <dl class="system-readonly">@foreach(\App\Services\Campus\SystemSettings::SERVER_ONLY as $env => $configKey)@php($value = config($configKey))<dt>{{ $env }}</dt><dd>{{ is_bool($value) ? ($value ? 'true' : 'false') : $value }}</dd>@endforeach</dl>
    <p class="muted">Pemulihan dari server bila pengaturan menu ini membuat login gagal: <code>php artisan brudel:pengaturan-sistem --reset=all</code> (atau nama pengaturan tertentu).</p>
</section>
</x-layout>
