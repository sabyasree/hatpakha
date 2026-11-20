{{-- D177, D243: the daily games, today's page and the leaderboard, one tap apart.
     $on: hub | word | riddle | cross | probad | board --}}
@php
    use App\Support\Url;
    $tabs = ['hub' => ['আজকের খেলা', Url::to('/khela/aaj')], 'word' => ['শব্দ-খেলা', Url::to('/khela')], 'riddle' => ['ধাঁধা', Url::to('/khela/dhadha')],
        'cross' => ['শব্দজব্দ', \App\Support\WordGame::crosswordUrl(auth()->check() ? 'medium' : 'easy')], 'probad' => ['প্রবাদ পূরণ', Url::to('/khela/probad')], 'board' => ['কারা এগিয়ে', Url::to('/khela/board')]];
@endphp
<nav aria-label="খেলা" class="mb-5 grid grid-cols-3 overflow-hidden rounded-3xl border-2 border-navy bg-white text-center text-[15px] font-bold">
    @foreach ($tabs as $key => [$label, $href])
        <a href="{{ $href }}" @if ($on === $key) aria-current="page" @endif class="flex min-h-12 items-center justify-center px-1 leading-tight {{ $on === $key ? 'bg-navy text-white' : 'hover:bg-peach-light' }} {{ $loop->index % 3 ? 'border-l-2 border-navy' : '' }} {{ $loop->index >= 3 ? 'border-t-2 border-navy' : '' }}">{{ $label }}</a>
    @endforeach
</nav>
