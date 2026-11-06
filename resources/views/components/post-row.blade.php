@props(['post', 'line' => true, 'meta' => null])
{{-- One piece as a compact row (mobile UX review): picture or initial, title, writer, a first line to invite the tap. --}}
@php
    $p = $post;
    $first = $line ? \Illuminate\Support\Str::limit(trim(html_entity_decode(strip_tags(\App\Support\Excerpt::lines((string) $p->body_html, 1)), ENT_QUOTES | ENT_HTML5)), 90) : null;
@endphp
<a href="{{ $p->url() }}" {{ $attributes->merge(['class' => 'flex items-center gap-4 py-3 hover:bg-peach-light/60']) }}>
    @if ($p->hasCardImage())
        <img src="{{ asset($p->featured_image_path) }}" alt="" loading="lazy" width="88" height="88" class="h-[88px] w-[88px] flex-none rounded-xl border-2 border-navy object-cover">
    @else
        <span class="flex h-[88px] w-[88px] flex-none items-center justify-center rounded-xl border-2 border-navy bg-peach-light font-display text-3xl font-extrabold text-red" aria-hidden="true">{{ mb_substr($p->title, 0, 1) }}</span>
    @endif
    <span class="min-w-0">
        <span class="block font-serif text-[19px] font-bold leading-snug">{{ $p->title }}</span>
        @if ($first && $first !== $p->title)<span class="mt-0.5 line-clamp-2 font-serif text-[15px] leading-snug text-ink2">{{ $first }}</span>@endif
        <span class="mt-0.5 block text-sm text-ink2">{{ $meta ?? (($p->authors->first()?->publicName() ?? '').($p->published_at ? ' · '.\App\Support\Bn::num((string) $p->published_at->year) : '')) }}</span>
    </span>
</a>
