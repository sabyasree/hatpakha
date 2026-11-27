{{-- D328: সাপ্তাহিক লড়াই. Ten questions a week (spelling, এক শব্দে প্রকাশ, proverbs). Without a code: play, then get a
     link to send. With a friend's code: play the same ten and see everyone on that link. No account needed; not part
     of the daily points. Runs in the browser (public/js/hp-choice.js). --}}
@php
    use App\Support\Bn;
    use App\Support\Url;
    $code = $challenge['code'] ?? null;
    $open = ! $challenge || $challenge['open'];
    $max = \App\Support\GameSocial::QUESTIONS * \App\Support\GameSocial::POINTS;
    $payload = ['mode' => 'lorai', 'game' => 'lorai', 'key' => 'lorai-'.($code ?: 'new'), 'id' => $week, 'date' => $week, 'no' => null, 'code' => $code,
        'title' => 'হাতপাখা সাপ্তাহিক লড়াই', 'shareLine' => 'আপনি কত পান? একই দশটা প্রশ্ন খেলুন: ', 'url' => $code ? Url::to('/khela/lorai/'.$code) : Url::to('/khela/lorai'),
        'post' => $code ? route('game.lorai.answer', $code) : route('game.lorai.store'), 'create' => route('game.lorai.store'),
        'points' => \App\Support\GameSocial::POINTS, 'label' => 'প্রশ্ন', 'report' => false, 'showPrompt' => true, 'needName' => ! auth()->check(),
        'plays' => $challenge['plays'] ?? null,
        'items' => array_map(fn ($q) => ['p' => $q['prompt'], 'a' => base64_encode($q['answer']), 'o' => $q['options'], 'n' => $q['note']], $questions)];
    $kinds = ['banan' => 'বানান', 'ekshobde' => 'এক শব্দে', 'probad' => 'প্রবাদ'];
    $ends = \App\Support\GameSocial::weekEnds($week);
@endphp
<x-layouts.app :seo="$seo">
    <section class="mx-auto max-w-[560px] px-5 pb-12 pt-6" @if ($open) data-choice @endif>
        @include('partials.game-nav', ['on' => 'social'])
        <div class="mb-4 grid grid-cols-2 gap-2 font-bold">
            <a href="{{ Url::to('/khela/lorai') }}" aria-current="page" class="flex min-h-12 items-center justify-center rounded-full bg-navy text-white">সাপ্তাহিক লড়াই</a>
            <a href="{{ Url::to('/khela/dol') }}" class="flex min-h-12 items-center justify-center rounded-full border-2 border-navy bg-white">পাড়ার দল</a>
        </div>

        @if ($maker)
            <p class="font-hand text-2xl text-red">লড়াইয়ের ডাক</p>
            <h1 class="font-display text-3xl font-extrabold leading-tight">{{ $maker['name'] }} আপনাকে লড়াইয়ে ডেকেছেন</h1>
            <p class="mt-2 text-lg">উনি দশটা প্রশ্নে পেয়েছেন <b>{{ Bn::num((string) $maker['score']) }} / {{ Bn::num((string) $max) }}</b>। একই দশটা প্রশ্ন আপনার সামনে। দেখা যাক কে জেতে!</p>
        @else
            <p class="font-hand text-2xl text-red">বন্ধুদের সঙ্গে</p>
            <h1 class="font-display text-4xl font-extrabold leading-tight">সাপ্তাহিক লড়াই</h1>
            <p class="mt-2 text-lg">দশটা প্রশ্ন: বানান, এক কথায় প্রকাশ আর প্রবাদ। খেলা শেষে একটা লিঙ্ক পাবেন; বন্ধু, পরিবার বা অফিসের গ্রুপে পাঠান। ওঁরা একই দশটা প্রশ্ন খেলবেন, আর সবার নম্বর এক জায়গায় দেখা যাবে।</p>
        @endif
        <p class="mt-2 text-ink2">প্রশ্ন বদলায় প্রতি রবিবার। এ সপ্তাহের লড়াই চলবে {{ Bn::date($ends) }} পর্যন্ত। অ্যাকাউন্ট লাগে না। এই খেলার নম্বর রোজকার পয়েন্টে যোগ হয় না।</p>

        @if (! $open)
            <div class="mt-5 rounded-3xl border-2 border-navy bg-peach-light p-5">
                <p class="text-lg font-bold">এই লড়াইয়ের সপ্তাহ শেষ হয়ে গেছে। যাঁরা খেলেছিলেন:</p>
                <ol class="mt-2 divide-y divide-rule">
                    @foreach ($challenge['plays'] as $i => $p)
                        <li class="flex items-center gap-3 py-2"><span class="flex h-8 w-8 flex-none items-center justify-center rounded-full {{ $i === 0 ? 'bg-red text-white' : 'border-2 border-navy' }}">{{ Bn::num((string) ($i + 1)) }}</span><span class="flex-1">{{ $p['name'] }}</span><span>{{ Bn::num((string) $p['score']) }}</span></li>
                    @endforeach
                </ol>
                <a href="{{ Url::to('/khela/lorai') }}" class="mt-4 flex h-14 items-center justify-center rounded-full bg-red px-6 text-lg font-bold text-white shadow-[3px_3px_0_#13212C]">এ সপ্তাহের নতুন লড়াই শুরু করুন</a>
            </div>
        @else
            <div data-c-start hidden class="mt-5 rounded-3xl border-2 border-navy bg-white p-5 shadow-[4px_4px_0_#13212C]">
                <label for="hp-lorai-name" class="block text-lg font-bold">{{ $maker ? 'আপনার নাম কী? সবার নম্বরের পাশে এই নামটা থাকবে' : 'আপনার নাম কী? বন্ধুরা লিঙ্ক খুলে এই নামটা দেখবেন' }}</label>
                <input id="hp-lorai-name" data-c-name type="text" maxlength="30" autocomplete="nickname" class="mt-2 h-14 w-full rounded-2xl border-2 border-navy bg-paper px-4 text-xl" placeholder="যেমন: মিতা, রাজুদা">
                <p class="mt-1 min-h-5 text-sm font-bold text-red" role="status" data-c-name-msg></p>
                <button type="button" data-c-go class="mt-2 flex h-14 w-full items-center justify-center rounded-full bg-red px-6 text-lg font-bold text-white shadow-[3px_3px_0_#13212C]">শুরু করুন →</button>
                <p class="mt-3 text-sm text-ink2">অ্যাকাউন্ট আছে? <a class="font-bold text-sky-night underline" href="{{ Url::to('/login') }}?next={{ rawurlencode('/'.trim(request()->path(), '/').'/') }}">সাইন ইন করে খেলুন</a>, নামটা নিজে থেকে বসবে।</p>
            </div>
            @php
                $extra = '<div data-c-link-box hidden class="mt-4 rounded-2xl border-2 border-navy bg-white p-4">'
                    .'<p class="text-lg font-extrabold">'.e($code ? 'নিজের লিঙ্ক: এবার আপনি বন্ধুদের ডাকুন' : 'আপনার লড়াইয়ের লিঙ্ক তৈরি').'</p>'
                    .'<p class="mt-1 text-ink2">এটা বন্ধুদের পাঠান। যিনি খুলবেন, তিনিই এই দশটা প্রশ্ন খেলবেন; সবার নম্বর এই লিঙ্কে।</p>'
                    .'<input data-c-url readonly aria-label="লড়াইয়ের লিঙ্ক" class="mt-2 h-12 w-full rounded-xl border-2 border-navy bg-paper px-3 text-base">'
                    .'<div class="mt-2 grid grid-cols-2 gap-2"><button type="button" data-c-copy class="flex h-12 items-center justify-center rounded-full border-2 border-navy bg-white font-bold">লিঙ্ক কপি</button>'
                    .'<a data-c-wa href="#" target="_blank" rel="noopener" class="flex h-12 items-center justify-center rounded-full bg-red font-bold text-white">WhatsApp-এ পাঠান</a></div></div>'
                    .($code ? '<button type="button" data-c-own hidden class="mt-4 flex h-14 w-full items-center justify-center rounded-full bg-red px-6 text-lg font-bold text-white shadow-[3px_3px_0_#13212C]">এবার আপনি বন্ধুদের ডাকুন →</button>' : '')
                    .'<h2 class="mt-4 font-display text-xl font-extrabold">'.e($code ? 'এই লড়াইয়ে যাঁরা খেললেন' : 'এই লিঙ্কে নম্বর').'</h2><ol class="mt-1 divide-y divide-rule" data-c-table></ol>';
            @endphp
            @include('partials.choice-board', ['count' => count($questions), 'after' => false, 'noShare' => true, 'extra' => $extra])
            <script type="application/json" data-c-data>{!! json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
            @guest
                <div class="mt-6 rounded-3xl border-2 border-navy bg-sky-tint p-4">
                    <p class="text-lg font-bold">রোজ এমন খেলা চান? হাতপাখায় রোজ আটটা বাংলা খেলা, পয়েন্ট আর মাসের সেরার খেতাব।</p>
                    <a href="{{ Url::to('/register') }}?next={{ rawurlencode('/khela/aaj/') }}" class="mt-3 flex h-12 items-center justify-center rounded-full bg-red px-6 font-bold text-white">বিনামূল্যে অ্যাকাউন্ট খুলুন</a>
                </div>
            @endguest
        @endif
        <x-ad-slot spot="list" :height="250" class="!mt-16" />
    </section>
    @if ($open)<script src="{{ asset('js/hp-choice.js') }}?v=1" defer></script>@endif
</x-layouts.app>
