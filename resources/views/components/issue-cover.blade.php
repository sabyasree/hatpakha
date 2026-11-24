@props(['issue', 'size' => 'md'])
{{-- A small issue cover for lists (archive, /sonkha/), the same design as the issue's own cover (D277). Sizes: lg (archive feature), md, sm. --}}
@php
    $box = ['lg' => 'h-44 w-32', 'md' => 'aspect-[1/1.41] w-full', 'sm' => 'h-32 w-24'][$size] ?? 'aspect-[1/1.41] w-full';
@endphp
<span {{ $attributes->merge(['class' => "relative block flex-none overflow-hidden rounded-md shadow-[3px_3px_0_#CB1B32] {$box}"]) }}>
    {{-- D277: the issue's own cover, small (no cover lines) --}}
    @include('partials.issue-cover', ['c' => \App\Support\IssueCover::data($issue), 'fill' => true])
</span>
