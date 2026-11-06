@props(['competition'])
{{-- Who may see a competition's live score and results; shown only to those allowed to change it. --}}
@php($current = $competition->resultsLocked() ? 'locked' : $competition->live_visibility)
<section class="panel"><h2>Akses hasil lomba</h2>
    <p class="muted">Mengatur siapa yang dapat membuka live score, bagan, dan peringkat lomba ini. Data lomba tetap tampil di Jelajahi Lomba.</p>
    <form method="post" action="/lomba/{{ $competition->id }}/akses-hasil">@csrf
        @foreach(\App\Models\Competition::RESULT_ACCESS as $value => $label)
        <label class="checkbox-label"><input type="radio" name="access" value="{{ $value }}" @checked($current === $value)><span>{{ $label }}</span></label>
        @endforeach
        <button class="button primary">Simpan akses</button>
    </form>
</section>
