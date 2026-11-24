{{-- D281: a serial episode's way around the serial ($nav from App\Support\EpisodeNav). $where: 'top' (under the title)
     or 'end' (after ক্রমশ). Native <details> for the list: no script, works on old phones. --}}
@php
    use App\Support\Bn;
    use App\Support\EpisodeNav;
    $s = $post->serial;
    $isFirst = $nav['index'] === 0;
    $list = $nav['episodes'];
    if ($list->count() > 12) {   // a long serial: the first one, then the three around this one
        $around = $list->slice(max(0, $nav['index'] - 3), 7);
        $list = collect([$list->first()])->concat($around->reject(fn ($e) => $e->id === $nav['first']->id))->values();
    }
@endphp
@if ($nav['count'] > 1)
    <details class="group mt-2" @if ($where === 'end') id="sob-porbo" @endif>
        <summary class="flex min-h-12 cursor-pointer list-none items-center justify-center rounded-xl border-2 border-navy bg-white px-3 font-bold [&::-webkit-details-marker]:hidden">
            সব পর্ব ({{ Bn::num((string) $nav['count']) }}) <span class="ml-1 group-open:hidden" aria-hidden="true">▾</span><span class="ml-1 hidden group-open:inline" aria-hidden="true">▴</span>
        </summary>
        <ol class="mt-2 rounded-xl border-2 border-navy bg-white p-1">
            @foreach ($list as $k => $e)
                @if ($nav['episodes']->count() > 12 && $k === 1 && $nav['index'] > 4)<li class="px-3 py-1 text-ink2" aria-hidden="true">…</li>@endif
                @php
                    $mark = EpisodeNav::mark($nav['read'], $e->id);
                @endphp
                <li>
                    @if ($e->id === $post->id)
                        <span aria-current="page" class="flex min-h-12 items-center justify-between gap-3 rounded-lg bg-navy px-3 py-2 text-white">
                            <span><span class="block font-semibold">{{ $e->episodeLabel() }}</span><span class="block text-sm font-bold text-peach">এখন পড়ছেন</span></span></span>
                    @else
                        <a href="{{ $e->url() }}" class="flex min-h-12 items-center justify-between gap-3 border-b border-rule px-3 py-2 hover:text-red">
                            <span><span class="block font-semibold">{{ $e->episodeLabel() }}</span><span class="block text-sm text-ink2">{{ Bn::date($e->published_at) }}</span></span>
                            @if ($mark)<span class="flex-none text-sm font-bold {{ $mark === 'অর্ধেক পড়া' ? 'text-red' : 'text-ink2' }}">{{ $mark }}</span>@endif
                        </a>
                    @endif
                </li>
            @endforeach
            @if ($nav['episodes']->count() > 12)
                <li><a href="{{ $s->url() }}" class="flex min-h-12 items-center px-3 font-bold underline">সব পর্ব দেখুন →</a></li>
            @endif
            @if ($s->status === 'active')<li class="px-3 py-2 text-sm text-ink2">পরের পর্ব আসবে {{ $s->dayName() }}।</li>@endif
        </ol>
    </details>
@endif
