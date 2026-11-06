@props(['post', 'size' => 'md'])
{{-- Auto-generated title card for posts without a usable image (docs/DB-AUDIT.md: 244 posts have none, most are <800px). --}}
@php
    $slug = $post->section?->slug;
    [$bg, $fg] = match (true) {
        in_array($slug, ['poetry', 'sher-o-shayari']) => ['linear-gradient(135deg,#CB1B32,#B0162B 60%,#7A1022)', '#FFFFFF'],
        $slug === 'short-stories' => ['linear-gradient(135deg,#13212C,#1B3A52 70%,#1A86C2)', '#FFFFFF'],
        $slug === 'travel' => ['linear-gradient(135deg,#7CCBEE,#2DAAE2 50%,#1A86C2)', '#13212C'],
        $slug === 'english' => ['linear-gradient(135deg,#12304A,#1A86C2 60%,#F08D90 130%)', '#FFFFFF'],
        default => ['linear-gradient(135deg,#FBEBD8,#EFC08D 55%,#E39A6A)', '#13212C'],
    };
    $len = mb_strlen($post->title);
    $font = $len <= 6 ? 'text-5xl' : ($len <= 14 ? 'text-4xl' : ($len <= 28 ? 'text-3xl' : 'text-2xl'));
    $useImage = $post->featured_image_path && ($post->featured_image_width ?? 0) >= 1000;
@endphp
<div {{ $attributes->merge(['class' => 'relative overflow-hidden aspect-[4/3] rounded-2xl']) }} style="background: {{ $bg }}; color: {{ $fg }}">
    @if ($useImage)
        <picture>
            @if ($set = \App\Support\Images::srcset('/'.$post->featured_image_path))<source type="image/webp" srcset="{{ $set }}" sizes="(max-width: 640px) 100vw, 420px">@endif
            <img src="{{ asset($post->featured_image_path) }}" alt="" loading="lazy" decoding="async" width="{{ $post->featured_image_width }}" height="{{ $post->featured_image_height }}" class="absolute inset-0 h-full w-full object-cover">
        </picture>
    @else
        <x-fan class="absolute -right-[12%] bottom-0 w-[78%] opacity-30" :colors="[$fg === '#FFFFFF' ? '#FFFFFF' : '#CB1B32', '#EFC08D']" :hub="null" />
        <div class="absolute inset-x-4 top-3 flex justify-between text-xs font-bold"><span>{{ $post->section?->name_bn }}</span><span class="font-hand text-base opacity-90">হাতপাখা</span></div>
        <div class="absolute inset-x-4 bottom-4 font-display font-extrabold leading-none {{ $font }} line-clamp-4">{{ $post->title }}</div>
    @endif
</div>
