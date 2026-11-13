{{-- D177: the three daily games and the leaderboard, one tap apart. $on: word | riddle | cross | board --}}
@php
    use App\Support\Url;
    $tabs = ['word' => ['শব্দ-খেলা', Url::to('/khela')], 'riddle' => ['ধাঁধা', Url::to('/khela/dhadha')], 'cross' => ['শব্দজব্দ', Url::to('/khela/shobdojobdo')], 'board' => ['লিডারবোর্ড', Url::to('/khela/board')]];
@endphp
<nav aria-label="খেলা" class="mb-5 grid grid-cols-4 overflow-hidden rounded-full border-2 border-navy bg-white text-center text-sm font-bold">
    @foreach ($tabs as $key => [$label, $href])
        <a href="{{ $href }}" @if ($on === $key) aria-current="page" @endif class="flex min-h-11 items-center justify-center px-1 leading-tight {{ $on === $key ? 'bg-navy text-white' : 'hover:bg-peach-light' }} {{ $loop->first ? '' : 'border-l-2 border-navy' }}">{{ $label }}</a>
    @endforeach
</nav>
