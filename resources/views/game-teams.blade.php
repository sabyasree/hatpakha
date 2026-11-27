{{-- D328: পাড়ার দল. Teams of players (a school, a club, an association abroad, a family). A team's month is its ten
     best players' points; a player is in one team at a time. $team: one team's page, or null for the list. --}}
@php
    use App\Support\Bn;
    use App\Support\Url;
    $done = session('team_done');
    $teamUrl = $team ? Url::to('/khela/dol/'.$team->code) : null;
    $isMine = $team && $mine && $mine->id === $team->id;
@endphp
<x-layouts.app :seo="$seo">
    <section class="mx-auto max-w-[560px] px-5 pb-12 pt-6">
        @include('partials.game-nav', ['on' => 'social'])
        <div class="mb-4 grid grid-cols-2 gap-2 font-bold">
            <a href="{{ Url::to('/khela/lorai') }}" class="flex min-h-12 items-center justify-center rounded-full border-2 border-navy bg-white">সাপ্তাহিক লড়াই</a>
            <a href="{{ Url::to('/khela/dol') }}" aria-current="page" class="flex min-h-12 items-center justify-center rounded-full bg-navy text-white">পাড়ার দল</a>
        </div>

        @if ($done)
            <p class="mb-4 rounded-2xl bg-sky-tint p-3 text-lg font-bold text-sky-night" role="status">{{ ['made' => 'দল তৈরি হয়েছে! এবার নিচের লিঙ্কটা বন্ধুদের পাঠান।', 'joined' => 'আপনি এই দলে যোগ দিয়েছেন। আপনার পয়েন্ট এখন দলের হিসেবেও যোগ হবে।', 'left' => 'আপনি দল ছেড়েছেন।'][$done] ?? '' }}</p>
        @endif

        @if ($team)
            <p class="font-hand text-2xl text-red">পাড়ার দল</p>
            <h1 class="font-display text-4xl font-extrabold leading-tight">{{ $team->name }}</h1>
            @if ($team->hidden_at)
                <p class="mt-2 rounded-2xl bg-peach-light p-3">এই দলকে আপাতত তালিকা থেকে সরানো হয়েছে।</p>
            @elseif ($row)
                <p class="mt-2 text-lg">এ মাসে দলের তালিকায় <b>{{ Bn::num((string) $row['rank']) }}</b> নম্বরে · <b>{{ Bn::num(number_format($row['points'])) }}</b> পয়েন্ট</p>
            @else
                <p class="mt-2 text-lg text-ink2">দুজন খেলোয়াড় হলে দলটা তালিকায় উঠবে।</p>
            @endif

            @if (! $team->hidden_at)
            <div class="mt-4 rounded-3xl border-2 border-navy bg-white p-4 shadow-[4px_4px_0_#13212C]">
                @auth
                    @if ($isMine)
                        <p class="text-lg font-bold">এটা আপনার দল। বন্ধুদের ডাকুন:</p>
                    @elseif ($mine)
                        <p class="text-lg">আপনি এখন <b>{{ $mine->name }}</b> দলে আছেন। এই দলে এলে আগেরটা ছেড়ে আসবেন।</p>
                    @endif
                    @unless ($isMine)
                        <form method="POST" action="{{ route('game.team.join', $team->code) }}" class="mt-3">@csrf
                            <button class="flex h-14 w-full items-center justify-center rounded-full bg-red px-6 text-lg font-bold text-white shadow-[3px_3px_0_#13212C]">{{ $team->name }} দলে যোগ দিন</button>
                        </form>
                    @endunless
                @else
                    <p class="text-lg">এই দলে যোগ দিতে হাতপাখায় একটা অ্যাকাউন্ট লাগে (বিনামূল্যে, এক মিনিটের কাজ)।</p>
                    <a href="{{ Url::to('/register') }}?next={{ rawurlencode('/khela/dol/'.$team->code.'/') }}" class="mt-3 flex h-14 items-center justify-center rounded-full bg-red px-6 text-lg font-bold text-white shadow-[3px_3px_0_#13212C]">অ্যাকাউন্ট খুলে যোগ দিন</a>
                    <p class="mt-2 text-center"><a href="{{ Url::to('/login') }}?next={{ rawurlencode('/khela/dol/'.$team->code.'/') }}" class="inline-flex min-h-11 items-center font-bold text-sky-night underline">আগে থেকে আছে? সাইন ইন</a></p>
                @endauth
                <div class="mt-3 grid gap-2">
                    <a href="https://wa.me/?text={{ rawurlencode('হাতপাখার খেলায় আমাদের দল "'.$team->name.'"-এ যোগ দিন। রোজ বাংলা শব্দের খেলা, একসঙ্গে পয়েন্ট: '.$teamUrl) }}" target="_blank" rel="noopener" class="flex h-12 items-center justify-center rounded-full border-2 border-navy bg-white font-bold">WhatsApp-এ দলের লিঙ্ক পাঠান</a>
                    <button type="button" data-copy="{{ $teamUrl }}" class="flex h-12 items-center justify-center rounded-full border-2 border-navy bg-white font-bold">লিঙ্ক কপি করুন</button>
                </div>
                <p class="mt-1 min-h-5 text-center text-sm font-semibold text-sky-night" role="status" data-copy-msg></p>
            </div>
            @endif

            {{-- D330 (game designers): the family's own scoreboard for today, to send to the group --}}
            @php
                $named = collect($members)->filter(fn ($m) => $m['name'] !== null);
                $todayText = 'আমাদের দল "'.$team->name.'" আজ: '.$named->map(fn ($m) => $m['name'].' '.($m['today'] !== null ? Bn::num((string) $m['today']).' ✓' : 'বাকি…'))->implode(', ')."\n".$teamUrl;
            @endphp
            <h2 class="mt-6 font-display text-2xl font-extrabold">আজ দলে</h2>
            <ul class="mt-2 grid gap-1.5 rounded-2xl border-2 border-navy bg-white p-3">
                @foreach ($members as $m)
                    <li class="flex items-center justify-between gap-3 {{ $m['me'] ? 'font-extrabold' : '' }}">
                        <span class="min-w-0 truncate">{{ $m['name'] ?? 'নাম না-দেখানো খেলোয়াড়' }}@if ($m['me']) · আপনি@endif</span>
                        <span class="flex-none {{ $m['today'] !== null ? 'font-bold text-navy' : 'text-ink2' }}">{{ $m['today'] !== null ? Bn::num((string) $m['today']).' ✓' : 'এখনও খেলেননি' }}</span>
                    </li>
                @endforeach
            </ul>
            <a href="https://wa.me/?text={{ rawurlencode($todayText) }}" target="_blank" rel="noopener" class="mt-2 flex h-12 items-center justify-center rounded-full bg-navy px-5 font-bold text-white">দলে আজকের ফল পাঠান</a>

            <h2 class="mt-6 font-display text-2xl font-extrabold">দলের খেলোয়াড়, এ মাসে</h2>
            <ol class="mt-2 divide-y divide-rule rounded-2xl border-2 border-navy bg-white px-3">
                @forelse ($members as $i => $m)
                    <li class="flex items-center gap-3 py-2.5 {{ $m['me'] ? 'font-extrabold' : '' }}">
                        <span class="flex h-8 w-8 flex-none items-center justify-center rounded-full {{ $i < \App\Support\GameSocial::TEAM_BEST && $m['counts'] ? 'bg-navy text-white' : 'border-2 border-rule text-ink2' }}">{{ Bn::num((string) ($i + 1)) }}</span>
                        <span class="min-w-0 flex-1 truncate">{{ $m['name'] ?? 'নাম না-দেখানো খেলোয়াড়' }}@if ($m['me']) · আপনি@endif</span>
                        <span>{{ Bn::num(number_format($m['points'])) }}</span>
                    </li>
                @empty
                    <li class="py-3 text-ink2">এখনও কেউ নেই।</li>
                @endforelse
            </ol>
            <p class="mt-2 text-sm text-ink2">দলের হিসেবে গোনা হয় সেরা {{ Bn::num((string) \App\Support\GameSocial::TEAM_BEST) }} জনের এ মাসের পয়েন্ট (কারা এগিয়ে-র মাসের পয়েন্টই)। তাই বড় দল শুধু লোক বাড়িয়ে জেতে না।</p>
            @if ($isMine)
                <form method="POST" action="{{ route('game.teams.leave') }}" class="mt-6" data-confirm="দল ছাড়বেন? পরে লিঙ্ক দিয়ে আবার যোগ দিতে পারবেন।">@csrf
                    <button class="flex min-h-11 items-center font-bold text-sky-night underline">দল ছাড়ুন</button>
                </form>
            @endif
            <a href="{{ Url::to('/khela/dol') }}" class="mt-6 flex h-12 items-center justify-center rounded-full border-2 border-navy bg-white font-bold">সব দলের তালিকা</a>
        @else
            <p class="font-hand text-2xl text-red">একসঙ্গে খেলুন</p>
            <h1 class="font-display text-4xl font-extrabold leading-tight">পাড়ার দল</h1>
            <p class="mt-2 text-lg">স্কুলের ক্লাস, পাড়ার ক্লাব, প্রবাসের বাঙালি সংগঠন, অফিস বা পরিবার: দল বেঁধে খেলুন। রোজকার খেলায় যা পয়েন্ট পান, তা আপনার নামে তো থাকেই, দলের হিসেবেও যোগ হয়।</p>

            @auth
                @if ($mine)
                    <a href="{{ Url::to('/khela/dol/'.$mine->code) }}" class="mt-4 flex items-center justify-between rounded-3xl border-2 border-navy bg-white p-4 shadow-[4px_4px_0_#13212C]">
                        <span><span class="block text-sm font-bold text-ink2">আপনার দল</span><span class="block font-display text-2xl font-extrabold">{{ $mine->name }}</span></span>
                        <span class="flex h-12 items-center rounded-full bg-red px-5 font-bold text-white">দেখুন</span>
                    </a>
                @else
                    <form method="POST" action="{{ route('game.teams.store') }}" class="mt-4 rounded-3xl border-2 border-navy bg-white p-4 shadow-[4px_4px_0_#13212C]">@csrf
                        <label for="hp-team-name" class="block text-lg font-bold">নতুন দল তৈরি করুন</label>
                        <input id="hp-team-name" name="name" type="text" required maxlength="40" value="{{ old('name') }}" class="mt-2 h-14 w-full rounded-2xl border-2 border-navy bg-paper px-4 text-xl" placeholder="যেমন: সপ্তম শ্রেণি খ, বেহালা পাঠচক্র">
                        @error('name')<p class="mt-1 font-bold text-red">{{ $message }}</p>@enderror
                        <button class="mt-3 flex h-14 w-full items-center justify-center rounded-full bg-red px-6 text-lg font-bold text-white shadow-[3px_3px_0_#13212C]">দল তৈরি করুন</button>
                        <p class="mt-2 text-sm text-ink2">তৈরি হলে একটা লিঙ্ক পাবেন; সেটা যাঁরা খুলে "যোগ দিন" টিপবেন, তাঁরাই দলে। কারও দলের লিঙ্ক পেলে সেটা খুলে যোগ দিন।</p>
                    </form>
                @endif
            @else
                <div class="mt-4 rounded-3xl border-2 border-navy bg-peach-light p-4">
                    <p class="text-lg font-bold">দল তৈরি করতে বা দলে যোগ দিতে অ্যাকাউন্ট লাগে।</p>
                    <a href="{{ Url::to('/register') }}?next={{ rawurlencode('/khela/dol/') }}" class="mt-3 flex h-12 items-center justify-center rounded-full bg-red px-6 font-bold text-white">বিনামূল্যে অ্যাকাউন্ট খুলুন</a>
                    <p class="mt-2 text-center"><a href="{{ Url::to('/login') }}?next={{ rawurlencode('/khela/dol/') }}" class="inline-flex min-h-11 items-center font-bold text-sky-night underline">সাইন ইন</a></p>
                </div>
            @endauth

            <h2 class="mt-6 font-display text-2xl font-extrabold">এ মাসের দল</h2>
            @if ($teams)
                <ol class="mt-2 divide-y divide-rule rounded-2xl border-2 border-navy bg-white px-3">
                    @foreach (array_slice($teams, 0, 30) as $t)
                        <li><a href="{{ Url::to('/khela/dol/'.$t['code']) }}" class="flex min-h-12 items-center gap-3 py-2 {{ $mine && $mine->id === $t['id'] ? 'font-extrabold' : '' }}">
                            <span class="flex h-8 w-8 flex-none items-center justify-center rounded-full {{ $t['rank'] === 1 ? 'bg-red text-white' : 'border-2 border-navy' }}">{{ Bn::num((string) $t['rank']) }}</span>
                            <span class="min-w-0 flex-1"><span class="block truncate">{{ $t['name'] }}</span><span class="block text-sm text-ink2">{{ Bn::num((string) $t['players']) }} জন</span></span>
                            <span class="font-bold">{{ Bn::num(number_format($t['points'])) }}</span>
                        </a></li>
                    @endforeach
                </ol>
            @else
                <p class="mt-2 rounded-2xl bg-sky-tint p-4 text-lg">এ মাসে এখনও কোনো দল তালিকায় ওঠেনি (দলে অন্তত দুজন লাগে)। প্রথম দলটা আপনার হোক।</p>
            @endif
            <p class="mt-2 text-sm text-ink2">দলের পয়েন্ট = দলের সেরা {{ Bn::num((string) \App\Support\GameSocial::TEAM_BEST) }} জনের এ মাসের পয়েন্ট যোগ করে। প্রতি মাসের ১ তারিখে নতুন করে শুরু।</p>
        @endif
        <x-ad-slot spot="list" :height="250" class="!mt-16" />
    </section>
    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
        document.querySelectorAll('form[data-confirm]').forEach((f) => f.addEventListener('submit', (e) => { if (!confirm(f.dataset.confirm)) e.preventDefault(); }));
        document.querySelectorAll('[data-copy]').forEach((b) => b.addEventListener('click', async () => {
            const m = document.querySelector('[data-copy-msg]');
            try { await navigator.clipboard.writeText(b.dataset.copy); m.textContent = 'লিঙ্ক কপি হয়েছে।'; } catch (e) { m.textContent = b.dataset.copy; }
        }));
    </script>
</x-layouts.app>
