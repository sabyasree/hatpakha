@props(['slot' => null, 'height' => 100])
{{-- Reserved-size ad slot (no layout shift, docs/SEO.md §5). Renders nothing until AdSense is configured. --}}
@if (config('services.adsense.client') && $slot)
    <div class="my-6 flex items-center justify-center" style="min-height: {{ $height }}px">
        <ins class="adsbygoogle" style="display:block; min-height: {{ $height }}px" data-ad-client="{{ config('services.adsense.client') }}" data-ad-slot="{{ $slot }}" data-ad-format="auto" data-full-width-responsive="true"></ins>
    </div>
@endif
