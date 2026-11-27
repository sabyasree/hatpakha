{{-- D328: সাপ্তাহিক লড়াই. Ten questions a week (spelling, এক শব্দে প্রকাশ, proverbs). Without a code: play, then get a
     link to send. With a friend's code: play the same ten and see everyone on that link. No account needed; not part
     of the daily points. Runs in the browser (public/js/hp-choice.js). --}}
@php
    use App\Support\Bn;
    use App\Support\Url;
    $code = $challenge['code'] ?? null;
    $open = ! $challenge || $challenge['open'];
    $played = ! empty($played);
    $own = ! empty($own);
    $max = \App\Support\GameSocial::QUESTIONS * \App\Support\GameSocial::POINTS;
    $payload = ['mode' => 'lorai', 'game' => 'lorai', 'key' => 'lorai-'.($code ?: 'new'), 'id' => $week, 'date' => $week, 'no' => null, 'code' => $code,
        'title' => 'হাতপাখা সাপ্তাহিক লড়াই', 'shareLine' => 'আপনি কত পান? একই দশটা প্রশ্ন খেলুন: ', 'url' => $code ? Url::to('/khela/lorai/'.$code) : Url::to('/khela/lorai'),
        'post' => $code ? route('game.lorai.answer', $code) : route('game.lorai.store'),
        'points' => \App\Support\GameSocial::POINTS, 'label' => 'প্রশ্ন', 'report' => false, 'showPrompt' => true, 'needName' => ! auth()->check(),
        'plays' => $challenge['plays'] ?? null,
        'items' => array_map(fn ($q) => ['p' => $q['prompt'], 'h' => ['banan' => '', 'ekshobde' => 'এক শব্দে কী বলে?', 'probad' => 'ফাঁকা জায়গায় কোন শব্দ বসবে?'][$q['kind']] ?? '',
            'sp' => $q['kind'] !== 'banan', 'a' => base64_encode($q['answer']), 'o' => $q['options'], 'n' => $q['note']], $questions)];
    $kinds = ['banan' => 'বানান', 'ekshobde' => 'এক শব্দে', 'probad' => 'প্রবাদ'];
    $ends = \App\Support\GameSocial::weekEnds($week);
@endphp
<x-layouts.app :seo="$seo">
    <section class="mx-auto max-w-[560px] px-5 pb-12 pt-6" @if ($open) data-choice @endif>
        @php
            $me = $challenge ? collect($challenge['plays'])->firstWhere('mine', true) : null;
            $invited = $maker && ! $own && ! $played && $open;   // a guest straight from WhatsApp: the name box first
        @endphp
        @unless ($invited)
            @include('partials.game-nav', ['on' => 'social'])
            <div class="mb-4 grid grid-cols-2 gap-2 font-bold">
                <a href="{{ Url::to('/khela/lorai') }}" aria-current="page" class="flex min-h-12 items-center justify-center rounded-full bg-navy text-white">সাপ্তাহিক লড়াই</a>
                <a href="{{ Url::to('/khela/dol') }}" class="flex min-h-12 items-center justify-center rounded-full border-2 border-navy bg-white">পাড়ার দল</a>
            </div>
        @endunless

        @if ($own)
            <p class="font-hand text-2xl text-red">আপনার লড়াই</p>
            <h1 class="font-display text-3xl font-extrabold leading-tight">লিঙ্ক তৈরি, এবার বন্ধুদের পাঠান</h1>
            <p class="mt-2 text-lg">আপনি দশটা প্রশ্নে পেয়েছেন <b>{{ Bn::num((string) ($me['score'] ?? 0)) }} / {{ Bn::num((string) $max) }}</b>।</p>
        @elseif ($maker && $played && $me)
            @php($diff = $me['score'] - $maker['score'])
            <p class="font-hand text-2xl text-red">লড়াইয়ের ফল</p>
            <h1 class="font-display text-3xl font-extrabold leading-tight">{{ $diff > 0 ? 'আপনি জিতেছেন!' : ($diff === 0 ? 'সমান সমান!' : 'এবার '.$maker['name'].' এগিয়ে') }}</h1>
            <p class="mt-2 text-lg">{{ $diff === 0 ? 'দুজনেই '.Bn::num((string) $me['score']).'।' : 'আপনি '.Bn::num((string) $me['score']).', '.$maker['name'].' '.Bn::num((string) $maker['score']).'।' }}</p>
        @elseif ($maker)
            <p class="font-hand text-2xl text-red">হাতপাখার সাপ্তাহিক লড়াই</p>
            <h1 class="font-display text-3xl font-extrabold leading-tight">{{ $maker['name'] }} আপনাকে লড়াইয়ে ডেকেছেন</h1>
            <p class="mt-2 text-lg">উনি দশটা প্রশ্নে পেয়েছেন <b>{{ Bn::num((string) $maker['score']) }} / {{ Bn::num((string) $max) }}</b>। একই দশটা প্রশ্ন এবার আপনার। দেখা যাক কে জেতে!</p>
        @else
            <p class="font-hand text-2xl text-red">বন্ধুদের সঙ্গে</p>
            <h1 class="font-display text-4xl font-extrabold leading-tight">সাপ্তাহিক লড়াই</h1>
            <p class="mt-2 text-lg">দশটা প্রশ্ন: বানান, এক শব্দে প্রকাশ আর প্রবাদ। খেলা শেষে একটা লিঙ্ক পাবেন; বন্ধু, পরিবার বা অফিসের গ্রুপে পাঠান। ওঁরা একই দশটা প্রশ্ন খেলবেন, সবার পয়েন্ট এক জায়গায় দেখা যাবে।</p>
        @endif
        @unless ($invited)
            <p class="mt-2 text-ink2">প্রশ্ন বদলায় প্রতি রবিবার (ভারতের সময়)। এ সপ্তাহের লড়াই চলবে {{ Bn::date($ends) }} পর্যন্ত। অ্যাকাউন্ট লাগে না। এই লড়াইয়ের পয়েন্ট রোজকার হিসেবে যোগ হয় না।</p>
        @endunless

        @if ($played || ! $open)
            {{-- the scores on this link; the one who made it gets the link to send, a friend a link of their own --}}
            <div class="mt-5 rounded-3xl border-2 border-navy bg-peach-light p-5 shadow-[4px_4px_0_#13212C]">
                @if (! $open)<p class="text-lg font-bold">এই লড়াইয়ের সপ্তাহ শেষ হয়ে গেছে।</p>@endif
                <h2 class="font-display text-2xl font-extrabold">{{ $own ? 'আপনার লড়াই' : 'এই লড়াইয়ে যাঁরা খেলেছেন' }}</h2>
                <ol class="mt-2 divide-y divide-rule">
                    @foreach ($challenge['plays'] as $i => $p)
                        <li class="flex items-center gap-3 py-2 {{ $p['mine'] ? 'font-extrabold' : '' }}">
                            <span class="flex h-8 w-8 flex-none items-center justify-center rounded-full {{ $i === 0 ? 'bg-red text-white' : 'border-2 border-navy' }}">{{ Bn::num((string) ($i + 1)) }}</span>
                            <span class="min-w-0 flex-1 truncate">{{ $p['name'] }}@if ($p['started']) <span class="text-sm font-normal text-ink2">(ডেকেছেন)</span>@endif @if ($p['mine'])· আপনি@endif</span>
                            <span>{{ Bn::num((string) $p['score']) }}</span>
                        </li>
                    @endforeach
                </ol>
                @if (count($challenge['plays']) < 2 && $own)<p class="mt-2 text-ink2">এখনও কেউ খেলেননি। লিঙ্কটা পাঠান, যিনি খেলবেন তাঁর পয়েন্ট এখানে উঠবে।</p>
                @else<p class="mt-1 text-sm text-ink2">পয়েন্ট সমান হলে যিনি কম সময়ে শেষ করেছেন, তিনি আগে।</p>@endif
                @if ($open && $own)
                    @php($link = Url::to('/khela/lorai/'.$challenge['code']))
                    <div class="mt-4 rounded-2xl border-2 border-navy bg-white p-4">
                        <p class="text-lg font-extrabold">লিঙ্কটা বন্ধুদের পাঠান</p>
                        <input readonly value="{{ $link }}" aria-label="লড়াইয়ের লিঙ্ক" class="mt-2 h-12 w-full rounded-xl border-2 border-navy bg-paper px-3 text-base" data-lorai-url>
                        <div class="mt-2 grid gap-2">
                            <a href="https://wa.me/?text={{ rawurlencode('হাতপাখার লড়াই: আমি পেয়েছি '.Bn::num((string) ($me['score'] ?? 0)).' / '.Bn::num((string) $max).'।'.(($t = \App\Support\GameSocial::teaser($week)) ? "\n".$t : '')."\nবাকি প্রশ্ন এখানে, আপনি কত পান? ".$link) }}" target="_blank" rel="noopener" class="flex h-14 items-center justify-center rounded-full bg-red px-4 text-lg font-bold text-white shadow-[3px_3px_0_#13212C]">WhatsApp-এ পাঠান</a>
                            <button type="button" data-copy="{{ $link }}" class="flex h-12 items-center justify-center rounded-full border-2 border-navy bg-white font-bold">লিঙ্ক কপি করুন</button>
                        </div>
                        <p class="mt-1 min-h-5 text-center text-sm font-semibold text-sky-night" role="status" data-copy-msg></p>
                    </div>
                @elseif ($open)
                    <form method="POST" action="{{ route('game.lorai.own', $challenge['code']) }}" class="mt-4">@csrf
                        <button class="flex h-14 w-full items-center justify-center rounded-full bg-red px-6 text-lg font-bold text-white shadow-[3px_3px_0_#13212C]">এবার আপনি বন্ধুদের ডাকুন →</button>
                    </form>
                    <p class="mt-1 text-center text-sm text-ink2">আপনার এই নম্বর নিয়েই নতুন লিঙ্ক হবে, আবার খেলতে হবে না।</p>
                @else
                    <a href="{{ Url::to('/khela/lorai') }}" class="mt-4 flex h-14 items-center justify-center rounded-full bg-red px-6 text-lg font-bold text-white shadow-[3px_3px_0_#13212C]">এ সপ্তাহের নতুন লড়াই শুরু করুন</a>
                @endif
                <a href="{{ Url::to('/khela/aaj') }}" class="mt-3 flex h-12 items-center justify-center rounded-full border-2 border-navy bg-white font-bold">আজকের অন্য খেলা</a>
            </div>
            @guest
                <div class="mt-6 rounded-3xl border-2 border-navy bg-sky-tint p-4">
                    <p class="text-lg font-bold">রোজ এমন খেলা চান? হাতপাখায় রোজ নতুন বাংলা খেলা আর সপ্তাহে একটা কুইজ, পয়েন্ট আর মাসের সেরার খেতাব।</p>
                    <a href="{{ Url::to('/register') }}?next={{ rawurlencode('/khela/aaj/') }}" class="mt-3 flex h-12 items-center justify-center rounded-full bg-red px-6 font-bold text-white">বিনামূল্যে অ্যাকাউন্ট খুলুন</a>
                </div>
            @endguest
        @else
            <div data-c-start hidden class="mt-5 rounded-3xl border-2 border-navy bg-white p-5 shadow-[4px_4px_0_#13212C]">
                <label for="hp-lorai-name" class="block text-lg font-bold">{{ $maker ? 'আপনার নাম লিখুন (সবার পয়েন্টের পাশে এটা থাকবে)' : 'আপনার নাম লিখুন (বন্ধুরা লিঙ্ক খুলে এটা দেখবেন)' }}</label>
                <input id="hp-lorai-name" data-c-name type="text" maxlength="30" autocomplete="nickname" class="mt-2 h-14 w-full rounded-2xl border-2 border-navy bg-paper px-4 text-xl" placeholder="যেমন: মিতা, রাজুদা">
                <p class="mt-1 min-h-5 text-sm font-bold text-red" role="status" data-c-name-msg></p>
                <button type="button" data-c-go class="mt-2 flex h-14 w-full items-center justify-center rounded-full bg-red px-6 text-lg font-bold text-white shadow-[3px_3px_0_#13212C]">শুরু করুন →</button>
                <p class="mt-3 text-sm text-ink2">অ্যাকাউন্ট আছে? <a class="inline-flex min-h-11 items-center font-bold text-sky-night underline" href="{{ Url::to('/login') }}?next={{ rawurlencode('/'.trim(request()->path(), '/').'/') }}">সাইন ইন করে খেলুন</a>, নামটা নিজে থেকে বসবে।</p>
            </div>
            @include('partials.choice-board', ['count' => count($questions), 'after' => false, 'noShare' => true,
                'extra' => '<a href="#" data-c-results hidden class="mt-4 flex h-14 items-center justify-center rounded-full bg-red px-6 text-lg font-bold text-white shadow-[3px_3px_0_#13212C]">'.($maker ? 'সবার পয়েন্ট দেখুন →' : 'সবার পয়েন্ট দেখুন ও লিঙ্ক পাঠান →').'</a>'])
            @if ($invited)<p class="mt-4 text-ink2">প্রশ্ন বদলায় প্রতি রবিবার (ভারতের সময়)। অ্যাকাউন্ট লাগে না। এই লড়াইয়ের পয়েন্ট রোজকার হিসেবে যোগ হয় না।</p>@endif
            <script type="application/json" data-c-data>{!! json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
        @endif
        <x-ad-slot spot="list" :height="250" class="!mt-16" />
    </section>
    @if ($open && ! $played)<script src="{{ asset('js/hp-choice.js') }}?v=3" defer></script>@endif
    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
        document.querySelectorAll('[data-copy]').forEach((b) => b.addEventListener('click', async () => {
            const m = document.querySelector('[data-copy-msg]');
            try { await navigator.clipboard.writeText(b.dataset.copy); m.textContent = 'লিঙ্ক কপি হয়েছে। যেখানে খুশি পেস্ট করুন।'; } catch (e) { document.querySelector('[data-lorai-url]').select(); }
        }));
    </script>
</x-layouts.app>
