{{-- D243: today's games in one place (with a game designer and a UX designer): what is done, what is left, today's
     points and who is ahead this month. A guest sees which games are open and which need an account. --}}
@php
    use App\Support\Bn;
    use App\Support\GameScores;
    use App\Support\Url;
    use App\Support\WordGame;
    $day = now('Asia/Kolkata')->toDateString();
    $got = $today['games'] ?? [];
    $cards = [
        ['word', 'শব্দ-খেলা', 'এলোমেলো অক্ষর সাজিয়ে পাঁচটা শব্দ', Url::to('/khela'), 100, true, ['শ', 'ব্দ']],
        ['riddle', 'ধাঁধা', 'রোজ দুটো: সহজ আর কঠিন', Url::to('/khela/dhadha'), 100, true, ['ধাঁ', 'ধা']],
        ['cross', 'শব্দজব্দ', 'ছোট শব্দছক, তিন ধাপ', WordGame::crosswordUrl($me ? 'medium' : 'easy'), 100, true, ['ছ', 'ক']],
        ['probad', 'প্রবাদ পূরণ', 'চারটে প্রবাদ, একটা করে শব্দ নেই', Url::to('/khela/probad'), 100, false, ['প্র', 'বা']],
    ];
    if (\App\Support\WordChoice::open()) {   // D328
        $cards[] = ['banan', 'বানান-যাচাই', 'পাঁচটা শব্দ: চারটে বানানের কোনটা ঠিক?', Url::to('/khela/banan'), 100, true, ['বা', 'ন']];
        $cards[] = ['ekshobde', 'এক শব্দে প্রকাশ', 'পাঁচটা কথা, এক শব্দে বলুন', Url::to('/khela/ek-shobde'), 100, false, ['এ', 'ক']];
        $cards[] = ['jal', 'অক্ষরজাল', 'ষোলোটা অক্ষর, তিন মিনিট, যত শব্দ পান', Url::to('/khela/akshorjal'), 100, false, ['জা', 'ল']];
    }
    // D330: today's three on top, the rest below
    $three = GameScores::suggest($me);
    $byKey = collect($cards)->keyBy(0);
    $top = collect($three)->map(fn ($k) => $byKey[$k] ?? null)->filter()->values()->all();
    $others = collect($cards)->reject(fn ($c) => in_array($c[0], $three, true))->values()->all();
    $isDoneKey = fn ($k) => (bool) (collect($today['groups'] ?? [])->firstWhere('key', $k)['done'] ?? false);
    $top = collect($top)->sortBy(fn ($c) => $isDoneKey($c[0]) ? 1 : 0)->values()->all();   // the ones still to play first
    $startAt = collect($top)->first(fn ($c) => ! $isDoneKey($c[0]));
    $doneCount = collect($today['groups'] ?? [])->where('done', true)->count();
    $quiz = \App\Support\WeeklyQuiz::live();
    $quizDone = $me && $quiz && \App\Support\WeeklyQuiz::played($me, $day);
    $groups = collect($today['groups'] ?? [])->keyBy('key');
    $lead = $month['rows'][0] ?? null;
    $wk = GameScores::board('sweek', $me, 1);   // D329
    $wlead = $wk['rows'][0] ?? null;
    $mine = $month['mine'] ?? null;
@endphp
<x-layouts.app :seo="$seo">
    <section class="mx-auto max-w-[560px] px-5 pb-12 pt-6">
        @include('partials.game-nav', ['on' => 'hub'])
        <p class="font-hand text-2xl text-red">রোজ তিনটে খেলায় পুরো দিন</p>
        <h1 class="font-display text-4xl font-extrabold leading-tight">আজকের খেলা</h1>

        @if ($me)
            <a href="{{ Url::to('/account/profile') }}#games" class="mt-4 grid grid-cols-3 items-center rounded-3xl border-2 border-navy bg-white px-2 py-3 text-center shadow-[4px_4px_0_#13212C]">
                @php
                    $weekDays = GameScores::weekDays($me);
                @endphp
                @if ($streak['now'] >= 2)
                    <span><span class="block font-display text-3xl font-extrabold leading-none">{{ Bn::num((string) $streak['now']) }}</span><span class="text-sm font-bold text-ink2">দিন টানা খেলছেন</span></span>
                @else
                    {{-- D330: no "০ দিন টানা": the week's days instead --}}
                    <span><span class="block font-display text-3xl font-extrabold leading-none">{{ Bn::num((string) count(array_filter($weekDays))) }}</span><span class="text-sm font-bold text-ink2">দিন এ সপ্তাহে</span></span>
                @endif
                <span class="border-x-2 border-rule"><span class="block whitespace-nowrap font-display text-2xl font-extrabold leading-none text-red">{{ Bn::num((string) $today['total']) }}<span class="text-base text-navy"> / {{ Bn::num((string) GameScores::DAY_MAX) }}</span></span><span class="text-sm font-bold text-ink2">আজকের পয়েন্ট</span></span>
                <span><span class="block font-display text-xl font-extrabold leading-tight">{{ $level['name'] }}</span><span class="text-sm font-bold text-ink2">আপনার স্তর</span></span>
            </a>
            <div class="mt-3 rounded-2xl border-2 border-navy bg-white px-3 py-2">@include('partials.game-week', ['days' => $weekDays])</div>
            @if (now('Asia/Kolkata')->day <= 7)
                @include('partials.game-month', ['user' => $me])
            @endif
            @if ($streak['mend'])
                {{-- D330: an offer, never "your streak is lost" --}}
                <p class="mt-3 rounded-2xl bg-sky-tint px-4 py-3 font-bold text-sky-night">কাল খেলা হয়নি। আজ {{ Bn::num((string) GameScores::MEND_GAMES) }}টা খেলা খেললেই টানা {{ Bn::num((string) $streak['now']) }} দিনের ধারা জোড়া লাগবে।</p>
            @endif
        @else
            <p class="mt-3 text-lg text-ink2">অ্যাকাউন্ট ছাড়াও সহজ খেলাগুলো খেলা যায়। পয়েন্ট জমাতে, সেরাদের তালিকায় নাম তুলতে আর খেতাব পেতে অ্যাকাউন্ট লাগে (বিনামূল্যে)।</p>
        @endif

        <div class="mt-5 flex items-end justify-between gap-2">
            <h2 class="font-display text-2xl font-extrabold leading-tight">আজকের তিন <span class="block text-base font-bold text-ink2">@if ($doneCount >= 3 && ($today['total'] ?? 0) >= GameScores::DAY_MAX)আজকের দিন পূর্ণ ✓ পুরো ৩০০; আরও খেললে শুধু মজা @elseif ($doneCount >= 3)আজকের দিন পূর্ণ ✓ আরও খেললে সেরা তিনটে বদলাতে পারে @elseif ($doneCount)আর {{ Bn::num((string) (3 - $doneCount)) }}টা খেললেই পুরো দিন @else সাত-আট মিনিটে পুরো দিন @endif</span></h2>
            @if ($startAt && $doneCount < 3)<a href="{{ $startAt[3] }}" class="flex h-12 flex-none items-center rounded-full bg-red px-5 text-lg font-bold text-white shadow-[3px_3px_0_#13212C]">{{ $doneCount ? 'পরেরটা খেলুন →' : 'শুরু করুন →' }}</a>@endif
        </div>
        <ul class="mt-3 grid gap-3">
            @foreach ($top as [$key, $name, $line, $href, $max, $open, $tiles])
                @php
                    $g = $groups[$key] ?? null;
                    $isDone = (bool) ($g['done'] ?? false);
                    $needsAccount = ! $me && ! $open;
                    // ধাঁধা and শব্দজব্দ have more than one part a day: say how much is still to be had
                    $more = $isDone && $g['points'] < 100 && in_array($key, ['riddle', 'cross'], true)
                        && count(array_intersect_key($got, array_flip(GameScores::GROUPS[$key][1]))) < count(GameScores::GROUPS[$key][1]);
                @endphp
                <li>
                    <a href="{{ $href }}" class="flex min-h-[5.5rem] items-center gap-3 rounded-3xl border-2 border-navy p-3 {{ $isDone && ! $more ? 'bg-peach-light' : 'bg-white shadow-[4px_4px_0_#13212C]' }}">
                        <span class="flex flex-none gap-0.5" aria-hidden="true">@foreach ($tiles as $t)<span class="flex h-11 w-10 items-center justify-center rounded-xl border-2 border-navy bg-paper font-serif text-lg font-bold {{ $loop->even ? '-rotate-3' : 'rotate-2' }}">{{ $t }}</span>@endforeach</span>
                        <span class="min-w-0 flex-1 leading-tight">
                            <span class="block font-display text-[22px] font-extrabold">{{ $name }}@if (in_array($key, ['banan', 'ekshobde', 'jal'], true) && $day < '2026-12-15') <span class="ms-1 rounded-full bg-red px-2 py-0.5 align-middle font-sans text-sm font-bold text-white">নতুন</span>@endif</span>
                            <span class="block text-base text-ink2">@if ($isDone)হয়ে গেছে · {{ Bn::num((string) $g['points']) }} পয়েন্ট@if ($more) · আরও বাকি@endif @elseif ($needsAccount)বিনামূল্যে অ্যাকাউন্ট খুললে খেলা যাবে @else{{ $line }}@if (! $me && in_array($key, ['riddle', 'cross'], true))। সহজটা সবার জন্য খোলা@endif @endif</span>
                        </span>
                        @if ($isDone && ! $more)
                            <span class="flex h-12 w-12 flex-none items-center justify-center rounded-full bg-navy text-2xl font-extrabold text-white" aria-hidden="true">✓</span>
                        @elseif ($needsAccount)
                            <span class="flex h-12 flex-none items-center gap-1.5 rounded-full border-2 border-navy bg-white px-4 font-bold">@include('partials.lock-icon') সাইন ইন</span>
                        @else
                            <span class="flex h-12 flex-none items-center rounded-full bg-red px-5 font-bold text-white">খেলুন</span>
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>
        <h2 class="mt-7 font-display text-xl font-extrabold">আরও খেলা <span class="text-base font-bold text-ink2">· যেটা ইচ্ছে</span></h2>
        <ul class="mt-3 grid gap-3">
            @foreach ($others as [$key, $name, $line, $href, $max, $open, $tiles])
                @php
                    $g = $groups[$key] ?? null;
                    $isDone = (bool) ($g['done'] ?? false);
                    $needsAccount = ! $me && ! $open;
                    // ধাঁধা and শব্দজব্দ have more than one part a day: say how much is still to be had
                    $more = $isDone && $g['points'] < 100 && in_array($key, ['riddle', 'cross'], true)
                        && count(array_intersect_key($got, array_flip(GameScores::GROUPS[$key][1]))) < count(GameScores::GROUPS[$key][1]);
                @endphp
                <li>
                    <a href="{{ $href }}" class="flex min-h-[5.5rem] items-center gap-3 rounded-3xl border-2 border-navy p-3 {{ $isDone && ! $more ? 'bg-peach-light' : 'bg-white shadow-[4px_4px_0_#13212C]' }}">
                        <span class="flex flex-none gap-0.5" aria-hidden="true">@foreach ($tiles as $t)<span class="flex h-11 w-10 items-center justify-center rounded-xl border-2 border-navy bg-paper font-serif text-lg font-bold {{ $loop->even ? '-rotate-3' : 'rotate-2' }}">{{ $t }}</span>@endforeach</span>
                        <span class="min-w-0 flex-1 leading-tight">
                            <span class="block font-display text-[22px] font-extrabold">{{ $name }}@if (in_array($key, ['banan', 'ekshobde', 'jal'], true) && $day < '2026-12-15') <span class="ms-1 rounded-full bg-red px-2 py-0.5 align-middle font-sans text-sm font-bold text-white">নতুন</span>@endif</span>
                            <span class="block text-base text-ink2">@if ($isDone)হয়ে গেছে · {{ Bn::num((string) $g['points']) }} পয়েন্ট@if ($more) · আরও বাকি@endif @elseif ($needsAccount)বিনামূল্যে অ্যাকাউন্ট খুললে খেলা যাবে @else{{ $line }}@if (! $me && in_array($key, ['riddle', 'cross'], true))। সহজটা সবার জন্য খোলা@endif @endif</span>
                        </span>
                        @if ($isDone && ! $more)
                            <span class="flex h-12 w-12 flex-none items-center justify-center rounded-full bg-navy text-2xl font-extrabold text-white" aria-hidden="true">✓</span>
                        @elseif ($needsAccount)
                            <span class="flex h-12 flex-none items-center gap-1.5 rounded-full border-2 border-navy bg-white px-4 font-bold">@include('partials.lock-icon') সাইন ইন</span>
                        @else
                            <span class="flex h-12 flex-none items-center rounded-full bg-red px-5 font-bold text-white">খেলুন</span>
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>
        {{-- D328: the weekly quiz, then playing with others --}}
        <a href="{{ Url::to('/khela/quiz') }}" class="mt-3 flex min-h-[5.5rem] items-center gap-3 rounded-3xl border-2 border-navy p-3 {{ $quizDone ? 'bg-peach-light' : 'bg-sky-tint shadow-[4px_4px_0_#13212C]' }}">
            <span class="flex flex-none gap-0.5" aria-hidden="true"><span class="flex h-11 w-10 rotate-2 items-center justify-center rounded-xl border-2 border-navy bg-paper font-serif text-lg font-bold">কু</span><span class="flex h-11 w-10 -rotate-3 items-center justify-center rounded-xl border-2 border-navy bg-paper font-serif text-lg font-bold">ইজ</span></span>
            <span class="min-w-0 flex-1 leading-tight">
                <span class="block font-display text-[22px] font-extrabold">পত্রিকা-কুইজ <span class="text-base font-bold text-red">সাপ্তাহিক</span>@if ($day < '2026-12-15') <span class="ms-1 rounded-full bg-red px-2 py-0.5 align-middle font-sans text-xs font-bold text-white">নতুন</span>@endif</span>
                <span class="block text-base text-ink2">@if ($quizDone)এ সপ্তাহে হয়ে গেছে @elseif ($quiz)এ সপ্তাহের লেখা থেকে পাঁচটা প্রশ্ন @else নতুন কুইজ আসবে রবিবার @endif</span>
            </span>
            @if ($quizDone)<span class="flex h-12 w-12 flex-none items-center justify-center rounded-full bg-navy text-2xl font-extrabold text-white" aria-hidden="true">✓</span>
            @elseif ($quiz)<span class="flex h-12 flex-none items-center rounded-full bg-red px-5 font-bold text-white">খেলুন</span>@endif
        </a>
        <p class="mt-3 rounded-2xl bg-white px-4 py-3 text-ink2"><b class="text-navy">পয়েন্টের নিয়ম:</b> প্রতিটি খেলায় দিনে ১০০ পর্যন্ত। দিনের সেরা তিনটে খেলা মিলে ৩০০। সপ্তাহে সেরা ছ'দিন গোনা হয়। যেগুলো ভালো লাগে, সেগুলোই খেলুন।</p>

        <div class="mt-6 grid grid-cols-2 gap-3">
            <a href="{{ Url::to('/khela/lorai') }}" class="rounded-3xl border-2 border-navy bg-white p-4 shadow-[4px_4px_0_#13212C]">
                <span class="block font-display text-xl font-extrabold">সাপ্তাহিক লড়াই</span>
                <span class="mt-1 block text-ink2">দশ প্রশ্ন খেলে বন্ধুদের লিঙ্ক পাঠান</span>
            </a>
            <a href="{{ Url::to('/khela/dol') }}" class="rounded-3xl border-2 border-navy bg-white p-4 shadow-[4px_4px_0_#13212C]">
                <span class="block font-display text-xl font-extrabold">পাড়ার দল</span>
                <span class="block mt-1 text-ink2">স্কুল, ক্লাব, পরিবার: দল বেঁধে পয়েন্ট</span>
            </a>
        </div>

        {{-- who is ahead this month --}}
        <div class="mt-6 rounded-3xl border-2 border-navy bg-sky-tint p-4">
            @if ($lead)
                <p class="font-hand text-2xl text-red">এ মাসে এগিয়ে</p>
                <p class="flex items-center gap-2 font-serif text-xl font-bold"><span class="flex h-8 w-8 flex-none items-center justify-center rounded-full bg-red font-sans text-base font-extrabold text-white">১</span> {{ $lead['name'] }} <span class="font-sans text-base font-extrabold">· {{ Bn::num(number_format($lead['points'])) }}</span></p>
                @if ($mine && $mine['rank'] > 1)<p class="mt-1 text-lg">আপনি {{ Bn::num((string) $mine['rank']) }} নম্বরে@if ($mine['gap'])। আর {{ Bn::num(number_format($mine['gap'])) }} পয়েন্ট পেলে {{ Bn::num((string) ($mine['rank'] - 1)) }} নম্বরে@endif।</p>
                @elseif ($mine && $mine['rank'] === 1)<p class="mt-1 text-lg font-bold">আপনিই সবার আগে।</p>@endif
            @else
                <p class="font-hand text-2xl text-red">এ মাসে এখনও কেউ খেলেননি</p>
                <p class="text-lg">প্রথম নামটা আপনার হোক।</p>
            @endif
            @if ($wlead)
                <p class="mt-3 border-t-2 border-dashed border-navy pt-3 text-lg"><b>এ সপ্তাহে এগিয়ে:</b> {{ $wlead['name'] }} · {{ Bn::num(number_format($wlead['points'])) }}@if (($wk['mine']['rank'] ?? 0) > 1)। আপনি {{ Bn::num((string) $wk['mine']['rank']) }} নম্বরে@endif</p>
                <p class="text-sm text-ink2">প্রতি রবিবার সপ্তাহের সেরা তিন জন খেতাব পান।</p>
            @endif
            <a href="{{ Url::to('/khela/board') }}" class="mt-3 flex h-12 items-center justify-center rounded-full border-2 border-navy bg-white px-6 font-bold">কারা এগিয়ে, পুরো তালিকা দেখুন</a>
        </div>

        @guest
            <div class="mt-6 rounded-3xl border-2 border-navy bg-peach-light p-4">
                <p class="text-lg font-bold">অ্যাকাউন্ট খুললে সব খেলাই খোলে, পয়েন্ট জমে, লিডারবোর্ডে নাম ওঠে।</p>
                <a href="{{ Url::to('/register') }}?next={{ rawurlencode('/khela/aaj/') }}" class="mt-3 flex h-14 items-center justify-center rounded-full bg-red px-6 text-lg font-bold text-white shadow-[3px_3px_0_#13212C]">বিনামূল্যে অ্যাকাউন্ট খুলুন</a>
                <p class="mt-2 text-center"><a href="{{ Url::to('/login') }}?next={{ rawurlencode('/khela/aaj/') }}" class="inline-flex min-h-11 items-center font-bold text-sky-night underline">আগে থেকে আছে? সাইন ইন</a></p>
            </div>
        @endguest

        {{-- D331: today's talk, open to those who have played today (so answers spoil nothing); the AI reads every message --}}
        @php
            $played = $me && \App\Support\GameCommunity::playedToday($me);
            $talk = $played ? \App\Support\GameCommunity::today($me) : [];
        @endphp
        <div id="adda" class="mt-8 scroll-mt-24 rounded-3xl border-2 border-navy bg-white p-4">
            <h2 class="font-display text-2xl font-extrabold">আজকের আড্ডা</h2>
            @if (session('adda'))<p class="mt-2 rounded-xl bg-sky-tint px-3 py-2 font-bold text-sky-night" role="status">{{ session('adda') }}</p>@endif
            @if (! $me)
                <p class="mt-1 text-ink2">আজকের খেলা নিয়ে গল্প: কোনটা কঠিন লাগল, কোন শব্দটা মনে পড়ছিল না। সাইন ইন করে আজকের একটা খেলা খেললেই আড্ডা খোলে।</p>
            @elseif (! $played)
                <p class="mt-1 text-ink2">আজকের একটা খেলা খেললেই আড্ডা খুলবে। (যাতে কেউ না খেলে উত্তর জেনে না ফেলেন।)</p>
            @else
                <form method="POST" action="{{ route('game.talk') }}" class="mt-2">@csrf
                    <label for="hp-adda" class="sr-only">আড্ডায় লিখুন</label>
                    <textarea id="hp-adda" name="body" rows="2" maxlength="300" required class="w-full rounded-2xl border-2 border-navy bg-paper p-3 text-lg" placeholder="আজকের খেলা কেমন লাগল?"></textarea>
                    <button class="mt-2 flex h-12 items-center justify-center rounded-full bg-red px-6 font-bold text-white">লিখুন</button>
                </form>
                <ul class="mt-3 divide-y divide-rule">
                    @forelse ($talk as $t)
                        <li class="py-2"><p class="text-sm font-bold text-ink2">{{ $t['name'] }} · {{ Bn::num($t['at']) }}@if ($t['waiting']) · <span class="text-red">শুধু আপনি দেখছেন</span>@endif</p><p class="text-lg">{{ $t['body'] }}</p></li>
                    @empty
                        <li class="py-2 text-ink2">আজ এখনও কেউ লেখেননি। প্রথম কথাটা আপনার হোক।</li>
                    @endforelse
                </ul>
            @endif
        </div>

        <p class="mt-6 font-bold">নতুন খেলা আসে রোজ রাত বারোটায় (ভারতের সময়)।</p>
        <a href="{{ Url::to('/khela/purono') }}" class="mt-2 flex min-h-11 w-fit items-center font-bold text-sky-night underline">কোনো দিন বাদ গেছে? পুরোনো খেলা, অনুশীলনের জন্য →</a>
        {{-- one ad, under everything a player taps --}}
        <x-ad-slot spot="list" :height="250" class="!mt-12" />
    </section>
</x-layouts.app>
