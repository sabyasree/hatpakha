@props(['issue', 'size' => 'md'])
{{-- A small issue cover for lists (archive, /sonkha/): the issue's own cover when it has one, else a navy cover with the
     name, the fan and the issue's title (white text on navy only). Sizes: lg (archive feature), md, sm. --}}
@php
    $box = ['lg' => 'h-44 w-32', 'md' => 'aspect-[3/4] w-full', 'sm' => 'h-32 w-24'][$size] ?? 'aspect-[3/4] w-full';
@endphp
<span {{ $attributes->merge(['class' => "relative block flex-none overflow-hidden rounded-md bg-navy shadow-[3px_3px_0_#CB1B32] {$box}"]) }}>
    @if ($issue->cover_path)
        <img src="{{ asset(ltrim($issue->cover_path, '/')) }}" alt="" loading="lazy" class="absolute inset-0 h-full w-full object-cover">
    @else
        <span class="absolute inset-0 flex flex-col justify-between p-3">
            <span class="font-hand text-white {{ $size === 'sm' ? 'text-sm' : 'text-xl' }} leading-none">হাতপাখা</span>
            <span class="h-7 w-7 self-end rounded-full bg-pink" aria-hidden="true"></span>
            @if ($size !== 'sm')<span class="text-sm font-bold leading-tight text-white">{{ $issue->title }}</span>@endif
        </span>
    @endif
</span>
