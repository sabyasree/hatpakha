@props(['post', 'size' => 'md'])
{{-- Auto-generated title card for posts without a usable image (docs/DB-AUDIT.md: 244 posts have none, most are <800px). --}}
@php
    $slug = $post->section?->slug;
    [$bg, $fg] = match (true) {
        in_array($slug, ['poetry', 'sher-o-shayari']) => ['linear-gradient(135deg,#CB1B32,#B0162B 60%,#7A1022)', '#FFFFFF'],
        $slug === 'short-stories' => ['linear-gradient(135deg,#13212C,#12304A 70%,#1A86C2)', '#FFFFFF'],
        $slug === 'travel' => ['linear-gradient(135deg,#7CCBEE,#2DAAE2 50%,#1A86C2)', '#13212C'],
        $slug === 'english' => ['linear-gradient(135deg,#12304A,#1A86C2 60%,#F08D90 130%)', '#FFFFFF'],
        default => ['linear-gradient(135deg,#FBEBD8,#EFC08D 55%,#E39A6A)', '#13212C'],
    };
    $len = mb_strlen($post->title);
    // Title size follows the CARD's width (container query units), so it fits in a 2-column phone grid and a wide hero alike.
    $font = $len <= 6 ? 'text-[min(15cqw,3.4rem)]' : ($len <= 14 ? 'text-[min(11.5cqw,2.6rem)]' : ($len <= 28 ? 'text-[min(9cqw,2rem)]' : 'text-[min(7.5cqw,1.6rem)]'));
    $useImage = $post->hasCardImage();
@endphp
<div {{ $attributes->merge(['class' => '@container relative overflow-hidden aspect-[4/3] rounded-2xl']) }} style="background: {{ $bg }}; color: {{ $fg }}">
    @if ($useImage)
        <picture>
            @if ($set = \App\Support\Images::srcset('/'.$post->featured_image_path))<source type="image/webp" srcset="{{ $set }}" sizes="(max-width: 640px) 100vw, 420px">@endif
            <img src="{{ asset($post->featured_image_path) }}" alt="" loading="lazy" decoding="async" width="{{ $post->featured_image_width }}" height="{{ $post->featured_image_height }}" class="absolute inset-0 h-full w-full object-cover">
        </picture>
    @else
        <x-fan class="absolute -right-[12%] bottom-0 w-[78%] opacity-30" :colors="[$fg === '#FFFFFF' ? '#FFFFFF' : '#CB1B32', '#EFC08D']" :hub="null" />
        <div class="absolute inset-x-[6cqw] top-[4cqw] flex justify-between gap-2 text-[min(4.2cqw,.8rem)] font-bold"><span>{{ $post->section?->name_bn }}</span><span class="font-hand text-[min(5.5cqw,1rem)] opacity-90">হাতপাখা</span></div>
        <div class="absolute inset-x-[6cqw] bottom-[6cqw] font-display font-extrabold leading-[1.05] {{ $font }} line-clamp-3">{{ $post->title }}</div>
    @endif
</div>
