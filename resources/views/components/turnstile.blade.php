{{-- Cloudflare Turnstile on auth forms (D59). Shows only when the site key and secret are set. --}}
@if (\App\Support\Turnstile::enabled())
    <div class="cf-turnstile mb-5" data-sitekey="{{ config('services.turnstile.site_key') }}" data-language="bn"></div>
    @once<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}"></script>@endonce
@endif
