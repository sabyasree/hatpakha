@props(['collection', 'compact' => false])
{{-- D99: one collection as a compact card (archive page, /sonkolon/): title, short intro, three piece titles, count.
     compact (archive, 2026-11-12 redesign): title, a two-line intro and the count only. --}}
@php
    $c = $collection;
    $first = $compact ? collect() : $c->posts()->take(3);
@endphp
<a href="{{ $c->url() }}" {{ $attributes->merge(['class' => 'flex h-full flex-col rounded-3xl border-2 border-navy bg-white p-5 shadow-[4px_4px_0_#13212C] hover:bg-peach-light']) }}>
    <span class="font-hand text-lg text-red">সংকলন</span>
    <span class="block font-display text-2xl font-extrabold leading-tight">{{ $c->title }}</span>
    @if ($c->intro)<span class="mt-2 {{ $compact ? 'line-clamp-2 text-sm' : 'line-clamp-3' }} font-serif text-ink2">{{ $c->intro }}</span>@endif
    @if ($first->isNotEmpty() && ! $compact)
        <span class="mt-3 block border-t border-rule pt-3 text-sm">
            @foreach ($first as $p)<span class="block truncate font-semibold">· {{ $p->title }}</span>@endforeach
        </span>
    @endif
    <span class="mt-auto pt-4 text-sm font-bold text-red">{{ \App\Support\Bn::num((string) count((array) $c->post_ids)) }}টি লেখা →</span>
</a>
