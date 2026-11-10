@props(['spot' => null, 'height' => 250, 'class' => ''])
{{-- D111: a manual ad spot. Reserved height (no layout shift, docs/SEO.md §5), labelled "বিজ্ঞাপন", requested only when the
     reader scrolls near it (the layout's script turns it into a real AdSense unit). Nothing shows off the live site,
     on pages that don't allow ads, or before the spot's slot id is set. --}}
@if ($spot && \App\Support\Ads::on() && ($slotId = \App\Support\Ads::slot($spot)))
    <aside class="my-8 {{ $class }}" aria-label="বিজ্ঞাপন">
        <div class="mb-1 text-center text-[11px] font-semibold tracking-wide text-ink2">বিজ্ঞাপন</div>
        <div class="flex items-center justify-center" style="min-height: {{ $height }}px">
            <ins class="hp-ad-pending" style="display:block; width:100%; min-height: {{ $height }}px" data-ad-client="{{ \App\Support\Ads::client() }}" data-ad-slot="{{ $slotId }}"
                @if ($spot === 'sidebar') data-ad-format="vertical" @else data-ad-format="auto" data-full-width-responsive="true" @endif></ins>
        </div>
    </aside>
@endif
