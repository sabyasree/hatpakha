@props(['post'])
{{-- D288 (owner, UX review): a small card for "আরও আজকের লেখা". The piece's own picture when it has one; otherwise a
     typographic card in its section's tint with the title large, never the same fan tile five times. One link. --}}
@php
    $slug = $post->section?->slug;
    $tint = match (true) {
        in_array($slug, ['poetry', 'sher-o-shayari'], true) => 'bg-pink/25',
        in_array($slug, ['short-stories', 'anugolpo'], true) => 'bg-sky-tint',
        in_array($slug, ['travel', 'essay', 'prabandha'], true) => 'bg-peach-light',
        default => 'bg-paper',
    };
    $img = $post->hasCardImage();
    $debut = class_exists(\App\Support\Debut::class) && rescue(fn () => \App\Support\Debut::is($post), false, false);
@endphp
<a href="{{ $post->url() }}" class="group relative flex min-h-[10.5rem] flex-col overflow-hidden rounded-2xl border-2 border-navy {{ $img ? 'bg-paper' : $tint }} p-3 hover:shadow-[3px_3px_0_#13212C]">
    @if ($img)
        <span class="-mx-3 -mt-3 mb-2 block aspect-[4/3] overflow-hidden border-b-2 border-navy">
            <picture>
                @if ($set = \App\Support\Images::srcset('/'.$post->featured_image_path))<source type="image/webp" srcset="{{ $set }}" sizes="(max-width: 640px) 50vw, 300px">@endif
                <img src="{{ asset($post->featured_image_path) }}" alt="" loading="lazy" decoding="async" width="{{ $post->featured_image_width }}" height="{{ $post->featured_image_height }}" class="h-full w-full object-cover">
            </picture>
        </span>
    @else
        <x-fan class="pointer-events-none absolute -bottom-6 -right-6 w-24 opacity-[.12]" :colors="['#13212C', '#13212C']" :hub="null" />
    @endif
    @if ($post->section)<span class="relative text-sm font-bold text-red">{{ $post->section->name_bn }}</span>@endif
    <span class="relative mt-1 font-serif text-lg font-bold leading-snug group-hover:text-red {{ $img ? 'line-clamp-2' : 'line-clamp-3' }}">{{ $post->title }}</span>
    <span class="relative mt-auto pt-2 text-[15px] text-ink2">{{ $post->authors->first()?->publicName() }}</span>
    <span class="relative text-sm text-ink2">@if ($debut)<span class="mr-1 inline-block h-2 w-2 rounded-full bg-red align-middle" aria-hidden="true"></span>নতুন কলম · @endif{{ \App\Support\Bn::num((string) $post->reading_minutes) }} মিনিট</span>
</a>
