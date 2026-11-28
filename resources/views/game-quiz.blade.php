{{-- D328: the weekly magazine quiz. Five questions on the week's pieces (drafted by the AI, approved at the desk);
     every answer links to its piece. Open to all; points with an account, once a week. --}}
@php
    use App\Support\Bn;
    use App\Support\Url;
    $today = now('Asia/Kolkata')->toDateString();
    $ends = $quiz ? \Illuminate\Support\Carbon::parse($quiz['week'])->addDays(6) : null;
    $payload = $quiz ? ['mode' => 'quiz', 'game' => 'quiz', 'key' => 'quiz', 'id' => $quiz['week'], 'date' => $today, 'no' => null, 'title' => 'হাতপাখা পত্রিকা-কুইজ, '.Bn::date(\Illuminate\Support\Carbon::parse($quiz['week'])).'-এর সপ্তাহ',
        'url' => Url::to('/khela/quiz'), 'points' => (int) round(100 / max(1, count($quiz['questions']))), 'whole' => 100, 'label' => 'প্রশ্ন', 'report' => false, 'showPrompt' => true, 'again' => 'লেখাগুলো পড়ে দেখুন, পরের রবিবার আবার',
        'items' => array_map(fn ($q) => ['p' => $q['q'], 'a' => base64_encode($q['options'][$q['answer']]), 'o' => $q['options'], 'n' => $q['why'] ?? '',
            'l' => isset($posts[$q['post_id']]) ? ['u' => $posts[$q['post_id']]->url(), 't' => $posts[$q['post_id']]->title] : null], $quiz['questions'])] : null;
    $recent = $quiz ? $posts : \App\Models\Post::published()->whereDoesntHave('authors', fn ($q) => $q->where('is_desk', true))->latest('published_at')->limit(5)->get();
@endphp
<x-layouts.app :seo="$seo">
    <section class="mx-auto max-w-[560px] px-5 pb-12 pt-6" @if ($quiz) data-choice @endif @auth data-score-url="{{ route('game.score') }}" data-score-token="{{ csrf_token() }}" @endauth>
        @include('partials.game-nav', ['on' => 'quiz'])
        <p class="font-hand text-xl text-red">প্রতি রবিবার (ভারতের সময়) পাঁচটা প্রশ্ন</p>
        <h1 class="font-display text-4xl font-extrabold leading-tight">পত্রিকা-কুইজ</h1>
        @if ($quiz)
            <p class="mt-2 text-ink2">এ সপ্তাহে হাতপাখায় যা বেরিয়েছে, তা থেকে পাঁচটা প্রশ্ন। পড়ে থাকলে সহজ; না পড়লে প্রতিটি উত্তরের পরে লেখাটার লিঙ্ক থাকবে। সব ঠিক হলে ১০০ পয়েন্ট। চলবে {{ Bn::date($ends) }} পর্যন্ত।</p>
            @if ($played)<p class="mt-2 rounded-2xl bg-sky-tint p-3 font-bold text-sky-night">এ সপ্তাহের কুইজের পয়েন্ট আপনার খাতায় আগেই উঠেছে। আবার খেলতে পারেন, পয়েন্ট আর বাড়বে না।</p>@endif
            <details class="mt-4 rounded-2xl border-2 border-navy bg-white p-4">
                <summary class="flex min-h-11 cursor-pointer items-center justify-between font-bold">আগে লেখাগুলো পড়ে নিতে চান? <span aria-hidden="true">▾</span></summary>
                <ul class="mt-2 divide-y divide-rule">
                    @foreach ($posts as $p)<li><a href="{{ $p->url() }}" class="flex min-h-11 items-center py-1 font-serif text-lg font-bold underline">{{ $p->title }}</a></li>@endforeach
                </ul>
            </details>
            @include('partials.choice-board', ['count' => count($quiz['questions'])])
            <script type="application/json" data-c-data>{!! json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
        @else
            <div class="mt-5 rounded-3xl border-2 border-navy bg-peach-light p-5 shadow-[4px_4px_0_#13212C]">
                <p class="font-hand text-2xl text-red">এ সপ্তাহের কুইজ আসছে</p>
                <p class="mt-1 text-lg">প্রতি রবিবার নতুন পাঁচটা প্রশ্ন আসে, আগের সাত দিনের লেখা থেকে। ততক্ষণ লেখাগুলো পড়ে রাখুন: যিনি পড়েছেন, তিনিই জিতবেন।</p>
                <ul class="mt-3 divide-y divide-rule">
                    @foreach ($recent as $p)<li><a href="{{ $p->url() }}" class="flex min-h-11 items-center py-1 font-serif text-lg font-bold underline">{{ $p->title }}</a></li>@endforeach
                </ul>
                <a href="{{ Url::to('/khela/aaj') }}" class="mt-4 flex h-12 items-center justify-center rounded-full bg-red px-6 font-bold text-white shadow-[3px_3px_0_#13212C]">আজকের অন্য খেলা খেলুন</a>
            </div>
        @endif
        <x-ad-slot spot="list" :height="250" class="!mt-16" />
    </section>
    @if ($quiz)
        <script src="{{ asset('js/hp-after.js') }}?v=5" defer></script>
        <script src="{{ asset('js/hp-choice.js') }}?v=4" defer></script>
    @endif
</x-layouts.app>
