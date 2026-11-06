@props(['action', 'class' => 'mb-5', 'size' => 'flexible', 'theme' => 'light', 'lazy' => false])
{{-- Cloudflare Turnstile (D60): one stable data-action per form; the server checks it and our hostname.
     lazy: rendered only when the visitor starts using the form (footer form on every page). --}}
@if (\App\Support\Turnstile::enabled())
    @if ($lazy)
        <div class="cf-turnstile-lazy {{ $class }}" data-sitekey="{{ config('services.turnstile.site_key') }}" data-action="{{ $action }}"
            data-size="{{ $size }}" data-theme="{{ $theme }}"></div>
    @else
        <div class="cf-turnstile {{ $class }}" data-sitekey="{{ config('services.turnstile.site_key') }}" data-action="{{ $action }}"
            data-language="bn" data-size="{{ $size }}" data-theme="{{ $theme }}"></div>
        @once<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}"></script>@endonce
    @endif
@endif
