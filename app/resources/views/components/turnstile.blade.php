@if(\App\Rules\Turnstile::enabled())
<div class="turnstile-box"><div class="cf-turnstile" data-sitekey="{{ config('services.turnstile.site_key') }}" data-language="id" data-theme="light"></div></div>
@once<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>@endonce
@endif
