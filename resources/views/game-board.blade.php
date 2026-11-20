{{-- D177, D243 (owner: "show those badges at the top of the leaderboard, so people will chase them"; with a game
     designer and a UX designer). From the top: the reigning বছরের সেরা, who is ahead this month, last month's first
     three; then today / this month / this year / the roll of honour. A signed-in player's own place stays in sight.
     Names only of signed-in players who have not hidden theirs. --}}
@php
    use App\Support\Bn;
    use App\Support\GameScores;
    use App\Support\GameTitles;
    use App\Support\Url;
    $now = now('Asia/Kolkata');
    $tabs = ['today' => 'আজ', 'month' => 'এ মাস', 'year' => 'এ বছর', 'hall' => 'সম্মান-ফলক'];
    $lead = $boards['month']['rows'][0] ?? null;
    $left = (int) $now->daysInMonth - (int) $now->day;
    $judged = $now->format('Y-m') >= GameTitles::FIRST_MONTH;
    $n = fn ($x) => Bn::num(number_format((int) $x));
    $notes = [
        'today' => 'আজকের পয়েন্ট। প্রতিটি খেলা ১০০, দিনের সেরা তিনটে খেলা গোনা হয়: পুরো দিন ৩০০।',
        'month' => 'মাসের সেরা '.Bn::num((string) GameScores::MONTH_DAYS).' দিনের পয়েন্ট গোনা হয়, তাই এক-আধ দিন বাদ গেলেও ক্ষতি নেই। মাসের সেরা হতে অন্তত '.Bn::num((string) GameScores::MONTH_MIN_DAYS).' দিন খেলতে হয়।',
        'year' => 'বছরের সেরা '.Bn::num((string) GameScores::YEAR_MONTHS).' মাসের পয়েন্ট গোনা হয়। বছরের সেরা হতে অন্তত '.Bn::num((string) GameScores::YEAR_MIN_MONTHS).' মাস নিয়মিত খেলতে হয়।',
    ];
    $mine = $boards['month']['mine'];
@endphp
<x-layouts.app :seo="$seo">
    <section class="mx-auto max-w-[560px] px-5 pb-12 pt-6" data-board>
        @include('partials.game-nav', ['on' => 'board'])
        <p class="font-hand text-2xl text-red">কারা এগিয়ে</p>
        <h1 class="font-display text-4xl font-extrabold leading-tight">লিডারবোর্ড</h1>

        @if ($hall['year'])
            <div class="mt-3 flex items-center gap-3 rounded-2xl bg-navy px-4 py-2.5 text-white">
                {!! GameTitles::svg('year', 1, 46, Bn::year((int) $hall['year']['period'])) !!}
                <div class="min-w-0"><p class="text-sm font-bold text-peach">বছরের সেরা {{ $hall['year']['label'] }}</p><p class="truncate font-serif text-xl font-bold">{{ $hall['year']['name'] }}</p></div>
            </div>
        @endif

        {{-- who is ahead this month --}}
        <div class="mt-3 flex items-center gap-3 rounded-3xl border-2 border-navy bg-red p-4 text-white shadow-[4px_4px_0_#13212C]">
            <div class="min-w-0 flex-1">
                <p class="font-hand text-2xl leading-tight">এ মাসে এগিয়ে</p>
                @if ($lead)
                    <p class="truncate font-serif text-[26px] font-bold leading-tight">{{ $lead['name'] }}</p>
                    <p class="text-lg font-extrabold">{{ $n($lead['points']) }} পয়েন্ট</p>
                @else
                    <p class="font-serif text-2xl font-bold leading-tight">এখনও কেউ নেই</p>
                    <p class="text-lg font-bold">প্রথম নামটা আপনার হোক</p>
                @endif
                <p class="mt-1 text-sm font-semibold">@if ($judged){{ Bn::MONTHS[$now->month - 1] }} শেষ হতে আর {{ Bn::num((string) $left) }} দিন। সেরা তিন জন খেতাব পাবেন।@else মাসের সেরার খেতাব শুরু {{ GameTitles::label('month', GameTitles::FIRST_MONTH) }} থেকে।@endif</p>
            </div>
            @if ($judged){!! GameTitles::svg('month', 1, 84, Bn::MONTHS[$now->month - 1]) !!}@endif
        </div>

        {{-- the four lists; without the script all four simply show, one under the other --}}
        <div class="mt-5 grid grid-cols-[1fr_1fr_1fr_1.45fr] overflow-hidden rounded-2xl border-2 border-navy bg-white text-center font-bold" role="tablist" aria-label="কোন তালিকা" data-board-tabs hidden>
            @foreach ($tabs as $key => $label)
                <button type="button" role="tab" data-tab="{{ $key }}" aria-selected="{{ $key === 'month' ? 'true' : 'false' }}" class="min-h-12 px-1 text-[15px] leading-tight {{ $loop->first ? '' : 'border-l-2 border-navy' }} aria-selected:bg-navy aria-selected:text-white">{{ $label }}</button>
            @endforeach
        </div>

        @foreach (['today', 'month', 'year'] as $key)
            @php
                $b = $boards[$key];
                $few = count($b['rows']) < 5;
            @endphp
            <div data-panel="{{ $key }}" class="mt-4">
                <h2 class="font-display text-2xl font-extrabold" data-panel-title>{{ $tabs[$key] }}</h2>
                <p class="mt-1 text-ink2">{{ $notes[$key] }}</p>
                @if (empty($b['rows']))
                    <div class="mt-3 rounded-3xl border-2 border-navy bg-white p-5">
                        <p class="text-lg font-bold">এখনও কেউ খেলেননি। প্রথম নামটা আপনার হোক।</p>
                        <a href="{{ Url::to('/khela/aaj') }}" class="mt-3 flex h-12 items-center justify-center rounded-full bg-red px-6 font-bold text-white shadow-[3px_3px_0_#13212C]">খেলুন</a>
                    </div>
                @else
                    @if ($few)<p class="mt-3 rounded-2xl bg-peach-light px-4 py-3 font-bold">এখনও কম জন খেলেছেন। প্রথম দিকে নাম তুলুন।</p>@endif
                    <ol class="mt-3 overflow-hidden rounded-3xl border-2 border-navy bg-white">
                        @foreach ($b['rows'] as $r)
                            <li class="flex min-h-[3.75rem] items-center gap-2.5 border-b border-rule px-3.5 py-1.5 last:border-b-0 {{ $r['me'] ? 'bg-sky-tint' : '' }}">
                                <span class="flex h-8 w-8 flex-none items-center justify-center rounded-full font-extrabold {{ [1 => 'bg-red text-white', 2 => 'bg-navy text-white', 3 => 'bg-sky'][$r['rank']] ?? 'bg-peach-light' }}">{{ Bn::num((string) $r['rank']) }}</span>
                                <span class="min-w-0 flex-1 leading-tight">
                                    <span class="flex items-center gap-1.5"><span class="truncate font-serif text-lg font-semibold">{{ $r['name'] }}</span>@foreach ($marks[$r['user_id']] ?? [] as $m){!! GameTitles::mark($m) !!}@endforeach @if ($r['me'])<span class="flex-none text-sm font-bold text-ink2">(আপনি)</span>@endif</span>
                                    <span class="block text-[15px] text-ink2">{{ $levels[$r['user_id']] ?? GameScores::LEVELS[0][1] }}@if ($key === 'month') · {{ Bn::num((string) $r['days']) }} দিন খেলেছেন@elseif ($key === 'year') · {{ Bn::num((string) $r['months']) }} মাস নিয়মিত@endif</span>
                                </span>
                                <span class="flex-none text-lg font-extrabold">{{ $n($r['points']) }}</span>
                            </li>
                        @endforeach
                    </ol>
                    @if ($b['count'] > count($b['rows']))<p class="mt-2 text-sm text-ink2">প্রথম {{ Bn::num((string) count($b['rows'])) }} জন দেখানো হল; মোট {{ Bn::num((string) $b['count']) }} জন খেলেছেন।</p>@endif
                @endif
                @if ($marks && ! empty($b['rows']))<p class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-[15px] text-ink2"><span class="flex items-center gap-1">{!! GameTitles::mark('m') !!} মাসের সেরা হয়েছেন</span><span class="flex items-center gap-1">{!! GameTitles::mark('y') !!} বছরের সেরা</span><span class="flex items-center gap-1">{!! GameTitles::mark('yy') !!} একাধিক বছর</span></p>@endif
                @if ($key === 'month' && $hall['lastMonth'])
            <div class="mt-4 rounded-2xl border-2 border-navy bg-white px-3 py-2.5">
                <p class="text-sm font-extrabold">{{ $hall['lastMonth'][0]['label'] }}-এর সেরা তিন</p>
                <ul class="mt-1 grid gap-1">
                    @foreach ($hall['lastMonth'] as $w)
                        <li class="flex items-center gap-2 font-serif text-lg font-semibold">{!! GameTitles::svg('month', $w['place'], 30) !!}<span class="min-w-0 flex-1 truncate">{{ $w['name'] }}</span><span class="font-sans text-base font-extrabold">{{ $n($w['points']) }}</span></li>
                    @endforeach
                </ul>
            </div>
        @endif

                @if ($b['mine'] && ! collect($b['rows'])->contains('me', true))
                    <p class="mt-2 rounded-2xl border-2 border-navy bg-sky-tint px-4 py-3 font-bold">আপনি: {{ $b['mine']['rank'] ? Bn::num((string) $b['mine']['rank']).' নম্বরে · ' : 'নাম লুকোনো আছে · ' }}{{ $n($b['mine']['points']) }} পয়েন্ট</p>
                @endif
            </div>
        @endforeach

        @guest
            <p class="mt-4 rounded-2xl border-2 border-navy bg-white p-4 text-lg"><a href="{{ Url::to('/login') }}?next={{ rawurlencode('/khela/board/') }}" class="font-bold text-sky-night underline">সাইন ইন</a> করে খেললে আপনার নামও এখানে উঠবে। নতুন? <a href="{{ Url::to('/register') }}?next={{ rawurlencode('/khela/aaj/') }}" class="font-bold text-sky-night underline">অ্যাকাউন্ট খুলুন</a></p>
        @endguest

        {{-- the roll of honour: every past month and year, kept for good --}}
        <div data-panel="hall" class="mt-6">
            <h2 class="font-display text-2xl font-extrabold" data-panel-title>সম্মান-ফলক</h2>
            <p class="mt-1 text-ink2">প্রতি মাসের আর প্রতি বছরের সেরা তিন জনের নাম এখানে থেকে যায়। দু বছর বছরের সেরা হলে <b class="text-navy">{{ GameTitles::MULTI[2] }}</b>, তিন বছর বা বেশি হলে <b class="text-navy">{{ GameTitles::MULTI[3] }}</b>।</p>
            <div class="mt-3 grid grid-cols-3 items-end gap-2 rounded-3xl border-2 border-navy bg-peach-light px-2 py-4 text-center">
                <div class="flex flex-col items-center justify-end">{!! GameTitles::svg('month', 1, 66, 'মাস') !!}<p class="mt-1 text-sm font-extrabold leading-tight">মাসের সেরা</p></div>
                <div class="flex flex-col items-center justify-end">{!! GameTitles::svg('year', 1, 78, 'বছর') !!}<p class="mt-1 text-sm font-extrabold leading-tight">বছরের সেরা</p></div>
                <div class="flex flex-col items-center justify-end">{!! GameTitles::svg('multi', 1, 90, 'রত্ন') !!}<p class="mt-1 text-sm font-extrabold leading-tight">{{ GameTitles::MULTI[2] }}<span class="block">{{ GameTitles::MULTI[3] }}</span></p></div>
            </div>
            @if (! $hall['months'] && ! $hall['years'])
                <p class="mt-3 rounded-2xl border-2 border-dashed border-navy bg-white p-4 text-lg">ফলক এখনও ফাঁকা। প্রথম নাম উঠবে <b>{{ GameTitles::label('month', GameTitles::FIRST_MONTH) }}</b>-এর সেরা তিন জনের, মাস শেষ হলেই।</p>
            @endif
            @foreach ([['years', 'বছরের সেরা', 'year'], ['months', 'মাসের সেরা', 'month']] as [$set, $title, $kind])
                @if ($hall[$set])
                    <h3 class="mt-5 font-display text-xl font-extrabold">{{ $title }}</h3>
                    <ul class="mt-2 overflow-hidden rounded-3xl border-2 border-navy bg-white">
                        @foreach (collect($hall[$set])->groupBy('period') as $period => $winners)
                            <li class="border-b border-rule px-4 py-2.5 last:border-b-0">
                                <p class="text-sm font-extrabold text-red">{{ $winners[0]['label'] }}</p>
                                @foreach ($winners as $w)
                                    <p class="flex items-center gap-2 font-serif {{ $w['place'] === 1 ? 'text-lg font-bold' : 'text-base' }}">{!! GameTitles::svg($kind, $w['place'], 26) !!}<span class="min-w-0 flex-1 truncate">{{ $w['name'] }}</span><span class="font-sans text-sm font-bold text-ink2">{{ $n($w['points']) }}</span></p>
                                @endforeach
                            </li>
                        @endforeach
                    </ul>
                @endif
            @endforeach
        </div>

        {{-- one ad, under the lists (nothing to tap beside it) --}}
        <x-ad-slot spot="list" :height="250" class="!mt-12" />
        <p class="mt-6 text-sm text-ink2">লিডারবোর্ডে নাম দেখাতে না চাইলে <a href="{{ Url::to('/account/settings') }}#games" class="font-bold underline">সেটিংস</a>-এ গিয়ে বন্ধ করুন। খেলা আর ব্যাজ তাতে বন্ধ হবে না, তবে খেতাব পাওয়া যাবে না।</p>
    </section>

    @auth
        {{-- the player's own place, always in sight above the phone's bottom menu --}}
        <div class="sticky bottom-[calc(3.75rem+env(safe-area-inset-bottom))] z-30 mx-auto flex min-h-[3.75rem] max-w-[560px] items-center gap-3 bg-navy px-4 py-2 text-white md:bottom-0 md:rounded-t-2xl">
            <span class="flex h-9 min-w-9 flex-none items-center justify-center rounded-full bg-white px-1 font-extrabold text-navy">{{ $mine && $mine['rank'] ? Bn::num((string) $mine['rank']) : '–' }}</span>
            <span class="min-w-0 flex-1 leading-tight"><span class="block font-bold">এ মাসে আপনি · {{ $n($mine['points'] ?? 0) }} পয়েন্ট</span>
                <span class="block text-sm font-semibold text-peach">@if (! $mine)আজ খেললেই নাম উঠবে@elseif (! $mine['rank'])নাম লুকোনো আছে@elseif ($mine['rank'] === 1)আপনিই সবার আগে@elseif ($mine['gap'])আর {{ $n($mine['gap']) }} পয়েন্ট পেলে {{ Bn::num((string) ($mine['rank'] - 1)) }} নম্বরে@endif</span></span>
            <a href="{{ Url::to('/khela/aaj') }}" class="flex h-11 flex-none items-center rounded-full bg-red px-4 font-bold text-white">খেলুন</a>
        </div>
    @endauth
    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
    (function () { var root = document.querySelector('[data-board]'); var bar = root && root.querySelector('[data-board-tabs]'); if (!bar) return;
      var show = function (key) { root.querySelectorAll('[data-panel]').forEach(function (p) { p.hidden = p.dataset.panel !== key; });
        bar.querySelectorAll('[data-tab]').forEach(function (t) { t.setAttribute('aria-selected', t.dataset.tab === key ? 'true' : 'false'); }); };
      bar.hidden = false; root.querySelectorAll('[data-panel-title]').forEach(function (h) { h.classList.add('sr-only'); });
      bar.addEventListener('click', function (e) { var t = e.target.closest('[data-tab]'); if (t) { show(t.dataset.tab); try { history.replaceState(null, '', '#' + t.dataset.tab); } catch (x) {} } });
      var want = location.hash.slice(1); show(bar.querySelector('[data-tab="' + want + '"]') ? want : 'month'); })();
    </script>
</x-layouts.app>
