{{-- D243: a player's titles and standing in the games (profile). $user. With titles: the objects, with how often.
     Without: the outline to be filled and how far the next step is; never a row of locks. --}}
@php
    use App\Support\Bn;
    use App\Support\GameScores;
    use App\Support\GameTitles;
    $t = GameTitles::of($user);
    $level = GameScores::level(GameScores::lifetime($user));
    $feats = GameScores::feats($user);
    $streak = GameScores::streak($user);
    $mine = GameScores::board('month', $user, 0)['mine'];
    $third = GameScores::ranking('month')[2]['points'] ?? null;
    // what can still be won this month: only a target within it is worth naming
    $reach = GameScores::DAY_MAX * (now('Asia/Kolkata')->daysInMonth - now('Asia/Kolkata')->day + 1) / 2;
    $tiles = array_filter([['দিন টানা খেলছেন', $streak['now']], ['নিখুঁত দিন (৩০০)', $feats['perfect']], ['নিয়মিত মাস', $feats['regular']]], fn ($x) => $x[1] > ($x[0] === 'দিন টানা খেলছেন' ? 1 : 0));
@endphp
<h2 id="games" class="mt-10 scroll-mt-24 font-display text-2xl font-extrabold">{{ __('আমার খেলা') }}</h2>
<div class="mt-3 max-w-xl">
    <div class="flex justify-between gap-3 font-extrabold"><span>{{ $level['name'] }}</span>@if ($level['nextName'])<span class="text-sm font-bold text-ink2">{{ $level['nextName'] }} {{ __('হতে আর') }} {{ Bn::num(number_format($level['left'])) }}</span>@endif</div>
    <div class="mt-1.5 h-3 overflow-hidden rounded-full border-2 border-navy bg-rule"><i class="block h-full bg-red" style="width: {{ $level['percent'] }}%"></i></div>
    <p class="mt-1.5 text-sm text-ink2">@foreach (GameScores::LEVELS as $i => [, $name])@if ($i) → @endif<span class="{{ $i === $level['index'] ? 'font-extrabold text-navy' : '' }}">{{ $name }}</span>@endforeach</p>

    <h3 class="mt-5 font-display text-xl font-extrabold">{{ __('খেতাব') }}</h3>
    @if ($t['any'])
        <ul class="mt-2 grid grid-cols-3 gap-1 rounded-3xl border-2 border-navy bg-peach-light px-2 py-4 text-center shadow-[4px_4px_0_#13212C]">
            @if ($t['multi'])
                <li>{!! GameTitles::svg('multi', 1, 92, count($t['years']) >= 3 ? 'রত্ন' : 'সাধক', count($t['years'])) !!}<span class="block font-extrabold leading-tight">{{ $t['multi'] }}</span><span class="block text-sm text-ink2">{{ implode(', ', array_map(fn ($y) => Bn::year((int) $y), $t['years'])) }}</span></li>
            @endif
            @foreach (array_slice(array_reverse($t['years']), 0, $t['multi'] ? 1 : 2) as $y)
                <li>{!! GameTitles::svg('year', 1, 92, Bn::year((int) $y)) !!}<span class="block font-extrabold leading-tight">বছরের সেরা</span><span class="block text-sm text-ink2">{{ Bn::year((int) $y) }}</span></li>
            @endforeach
            @if ($t['months'])
                <li>{!! GameTitles::svg('month', 1, 92, 'মাস', count($t['months'])) !!}<span class="block font-extrabold leading-tight">মাসের সেরা</span><span class="block text-sm text-ink2">{{ count($t['months']) > 1 ? Bn::num((string) count($t['months'])).' বার' : GameTitles::label('month', $t['months'][0]) }}</span></li>
            @endif
            @if ($t['weeks'])
                <li>{!! GameTitles::svg('week', 1, 80, 'সপ্তাহ', count($t['weeks'])) !!}<span class="block font-extrabold leading-tight">সপ্তাহের সেরা</span><span class="block text-sm text-ink2">{{ count($t['weeks']) > 1 ? Bn::num((string) count($t['weeks'])).' বার' : GameTitles::label('week', $t['weeks'][0]) }}</span></li>
            @endif
            @foreach ([2, 3] as $place)
                @if (($t['yearPlaces'][$place] ?? 0) || ($t['monthPlaces'][$place] ?? 0) || ($t['weekPlaces'][$place] ?? 0))
                    <li>{!! GameTitles::svg(($t['yearPlaces'][$place] ?? 0) ? 'year' : (($t['monthPlaces'][$place] ?? 0) ? 'month' : 'week'), $place, 92, 'সেরা') !!}<span class="block font-extrabold leading-tight">সেরা তিনে, {{ GameTitles::PLACES[$place] }}</span>
                        <span class="block text-sm text-ink2">@if ($t['yearPlaces'][$place] ?? 0)বছরে {{ Bn::num((string) $t['yearPlaces'][$place]) }} বার @endif @if ($t['monthPlaces'][$place] ?? 0)মাসে {{ Bn::num((string) $t['monthPlaces'][$place]) }} বার @endif @if ($t['weekPlaces'][$place] ?? 0)সপ্তাহে {{ Bn::num((string) $t['weekPlaces'][$place]) }} বার@endif</span></li>
                @endif
            @endforeach
        </ul>
    @else
        <div class="mt-2 flex items-center gap-3 rounded-3xl border-2 border-navy bg-peach-light p-3">
            {!! GameTitles::svg('month', 1, 60, '', 0, false) !!}
            <p class="text-base leading-snug">@if ($mine && $mine['rank'])<b>এ মাসে আপনি {{ Bn::num((string) $mine['rank']) }} নম্বরে।</b>@if ($mine['rank'] > 3 && $third && ($third - $mine['points']) < $reach) সেরা তিনে উঠতে আর {{ Bn::num(number_format(max(1, $third - $mine['points'] + 1))) }} পয়েন্ট।@elseif ($mine['rank'] > 3 && $mine['gap']) আর {{ Bn::num(number_format($mine['gap'])) }} পয়েন্ট পেলে {{ Bn::num((string) ($mine['rank'] - 1)) }} নম্বরে।@elseif ($mine['rank'] <= 3) মাসের শেষ পর্যন্ত ধরে রাখলেই সেরা তিনে।@endif এ মাসে {{ Bn::num((string) $mine['days']) }} দিন খেলেছেন।
                @else<b>প্রতি সপ্তাহে আর প্রতি মাসে সেরা তিন জন খেতাব পান।</b> সপ্তাহে তিন দিন খেললেই সপ্তাহের সেরার দৌড়ে থাকা যায়; আজ থেকেই শুরু করুন।@endif
                <a href="{{ \App\Support\Url::to('/khela/aaj') }}" class="font-bold text-sky-night underline">আজকের খেলা →</a></p>
        </div>
    @endif

    @if ($tiles)
        <ul class="mt-3 grid grid-cols-3 gap-2 text-center">
            @foreach ($tiles as [$label, $count])
                <li class="rounded-2xl border-2 border-navy bg-white px-1 py-2"><span class="block font-display text-2xl font-extrabold leading-none">{{ Bn::num((string) $count) }}</span><span class="text-sm text-ink2">{{ $label }}</span></li>
            @endforeach
        </ul>
    @endif
</div>
