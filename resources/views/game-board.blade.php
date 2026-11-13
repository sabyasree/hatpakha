{{-- D177 (owner): the games' leaderboard: today, the last seven days, all time. Names only of signed-in players who
     have not hidden theirs. --}}
@php
    use App\Support\Bn;
    use App\Support\Url;
    $tabs = ['today' => 'আজ', 'week' => 'এ সপ্তাহ', 'all' => 'সব সময়'];
@endphp
<x-layouts.app :seo="$seo">
    <section class="mx-auto max-w-[560px] px-5 pb-12 pt-6">
        @include('partials.game-nav', ['on' => 'board'])
        <p class="font-hand text-2xl text-red">কারা এগিয়ে</p>
        <h1 class="font-display text-4xl font-extrabold leading-tight">লিডারবোর্ড</h1>
        <p class="mt-2 text-ink2">তিনটি খেলা মিলিয়ে রোজ সর্বোচ্চ ৩০০ পয়েন্ট। সমান হলে যিনি কম সময়ে পেরেছেন, তিনি আগে।</p>
        @guest
            <p class="mt-4 rounded-2xl border-2 border-navy bg-peach-light p-4"><a href="{{ Url::to('/login') }}?next={{ rawurlencode('/khela/board/') }}" class="font-bold text-sky-night underline">সাইন ইন</a> করে খেললে আপনার নামও এখানে উঠবে। নতুন? <a href="{{ Url::to('/register') }}" class="font-bold text-sky-night underline">ফ্রিতে সাইন আপ করুন</a></p>
        @endguest

        @foreach ($tabs as $key => $label)
            @php($b = $boards[$key])
            <details class="mt-5 rounded-3xl border-2 border-navy bg-white" @if ($loop->first || ($key === 'week' && empty($boards['today']['rows']))) open @endif>
                <summary class="flex min-h-14 cursor-pointer items-center justify-between gap-3 px-5 font-display text-2xl font-extrabold">{{ $label }}<span class="text-sm font-bold text-ink2">{{ Bn::num((string) count($b['rows'])) }} জন</span></summary>
                @if (empty($b['rows']))
                    <p class="border-t-2 border-rule px-5 py-4 text-ink2">এখনও কেউ খেলেননি। আপনিই প্রথম হোন!</p>
                @else
                    <ol class="border-t-2 border-rule">
                        @foreach ($b['rows'] as $r)
                            <li class="flex min-h-12 items-center gap-3 border-b border-rule px-5 py-2 last:border-b-0 {{ $r['me'] ? 'bg-sky-tint font-bold' : '' }}">
                                <span class="flex h-8 w-8 flex-none items-center justify-center rounded-full {{ $r['rank'] <= 3 ? 'bg-red text-white' : 'bg-peach-light' }} text-sm font-extrabold">{{ Bn::num((string) $r['rank']) }}</span>
                                <span class="min-w-0 flex-1 truncate">{{ $r['name'] }}@if ($r['me']) <span class="text-sm text-ink2">(আপনি)</span>@endif</span>
                                @if ($key !== 'today')<span class="flex-none text-sm text-ink2">{{ Bn::num((string) $r['days']) }} দিন</span>@endif
                                <span class="flex-none font-extrabold">{{ Bn::num((string) $r['points']) }}</span>
                            </li>
                        @endforeach
                    </ol>
                @endif
                @if ($b['mine'] && ! collect($b['rows'])->contains('me', true))
                    <p class="border-t-2 border-navy bg-sky-tint px-5 py-3 font-bold">আপনি: {{ $b['mine']['rank'] ? Bn::num((string) $b['mine']['rank']).' নম্বরে · ' : 'নাম লুকোনো আছে · ' }}{{ Bn::num((string) $b['mine']['points']) }} পয়েন্ট</p>
                @endif
            </details>
        @endforeach

        <p class="mt-6 text-sm text-ink2">লিডারবোর্ডে নাম দেখাতে না চাইলে <a href="{{ Url::to('/account/settings') }}#games" class="font-bold underline">সেটিংস</a>-এ গিয়ে বন্ধ করুন। খেলা আর ব্যাজ তাতে বন্ধ হবে না।</p>
    </section>
</x-layouts.app>
