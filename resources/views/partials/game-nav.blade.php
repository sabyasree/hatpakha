{{-- D177, D243, D328: the games, today's page, the board and playing with others, one tap apart. Eight games no
     longer fit a grid of tabs: the three places on top, every game in a row below that scrolls sideways on a phone
     (the one open is marked and scrolled into view).
     $on: hub | board | social | word | riddle | cross | probad | banan | ekshobde | jal | quiz --}}
@php
    use App\Support\Url;
    $top = ['hub' => ['আজকের খেলা', Url::to('/khela/aaj')], 'board' => ['সেরাদের তালিকা', Url::to('/khela/board')], 'social' => ['লড়াই ও দল', Url::to('/khela/lorai')]];
    $games = ['word' => ['শব্দ-খেলা', Url::to('/khela')], 'riddle' => ['ধাঁধা', Url::to('/khela/dhadha')],
        'cross' => ['শব্দজব্দ', \App\Support\WordGame::crosswordUrl(auth()->check() ? 'medium' : 'easy')], 'probad' => ['প্রবাদ পূরণ', Url::to('/khela/probad')]];
    if (\App\Support\WordChoice::open()) {
        $games += ['banan' => ['বানান-যাচাই', Url::to('/khela/banan')], 'ekshobde' => ['এক শব্দে প্রকাশ', Url::to('/khela/ek-shobde')], 'jal' => ['অক্ষরজাল', Url::to('/khela/akshorjal')]];
    }
    $games['quiz'] = ['পত্রিকা-কুইজ', Url::to('/khela/quiz')];
@endphp
@if (! in_array($on, ['hub', 'board', 'social'], true))
    {{-- D330 (UX panel): on a game's own page, one slim row, so the game starts above the fold --}}
    <nav aria-label="খেলা" class="mb-3 flex items-center justify-between gap-2 text-[15px] font-bold">
        <a href="{{ $top['hub'][1] }}" class="flex min-h-11 items-center gap-1 rounded-full border-2 border-navy bg-white px-4 hover:bg-peach-light"><span aria-hidden="true">←</span> আজকের খেলা</a>
        <a href="{{ $top['board'][1] }}" class="flex min-h-11 items-center px-2 text-sky-night underline">সেরাদের তালিকা</a>
    </nav>
    {{-- once a player taps inside the game, the site's bottom bar steps aside (it covered the letter keys) --}}
    <style>html[data-playing] [data-bottom-nav] { display: none; }</style>
    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">document.addEventListener('pointerdown', function (e) { if (e.target.closest && e.target.closest('[data-khela],[data-jobdo],[data-probad],[data-choice],[data-jal]')) document.documentElement.dataset.playing = '1'; });</script>
@else
<nav aria-label="খেলা" class="mb-5">
    <div class="grid grid-cols-3 overflow-hidden rounded-3xl border-2 border-navy bg-white text-center text-[15px] font-bold">
        @foreach ($top as $key => [$label, $href])
            <a href="{{ $href }}" @if ($on === $key) aria-current="page" @endif class="flex min-h-12 items-center justify-center px-1 leading-tight {{ $on === $key ? 'bg-navy text-white' : 'hover:bg-peach-light' }} {{ $loop->index ? 'border-l-2 border-navy' : '' }}">{{ $label }}</a>
        @endforeach
    </div>
    <ul class="-mx-5 mt-2 flex gap-2 overflow-x-auto px-5 pb-1 text-[15px] font-bold [scrollbar-width:none] md:mx-0 md:flex-wrap md:overflow-visible md:px-0" aria-label="সব খেলা">
        @foreach ($games as $key => [$label, $href])
            <li class="flex-none"><a href="{{ $href }}" @if ($on === $key) aria-current="page" data-nav-on @endif class="flex min-h-11 items-center whitespace-nowrap rounded-full border-2 border-navy px-4 {{ $on === $key ? 'bg-red text-white border-red' : 'bg-white hover:bg-peach-light' }}">{{ $label }}</a></li>
        @endforeach
    </ul>
</nav>
@endif
<script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">(function(){var a=document.querySelector('[data-nav-on]');if(!a)return;var u=a.closest('ul');u.scrollLeft=a.parentElement.offsetLeft-u.clientWidth/2+a.offsetWidth/2;})();</script>
@php $hpFest = \App\Support\GameFestival::current(); @endphp
@if ($hpFest)
    {{-- D331: a festival's week --}}
    <p class="-mt-1 mb-4 rounded-2xl border-2 border-red bg-white px-4 py-2 font-bold text-red">{{ $hpFest['name'] }}-এর সপ্তাহ: {{ $hpFest['line'] }} · খেললেই {{ $hpFest['name'] }}-এর স্মারক</p>
@endif
