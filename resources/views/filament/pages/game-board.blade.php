{{-- D282: the games leaderboard in the desk. Numbers on top, the board as readers see it (plus who they don't see),
     one player's games on a click, and the titles given so far. --}}
@php
    use App\Support\Bn;
    use App\Support\GameScores;
    $sum = $this->summary();
    $rows = $this->rows();
    $hall = rescue(fn () => \App\Support\GameTitles::hall(), ['years' => [], 'months' => []], false);
    $games = GameScores::GAMES;
@endphp
<x-filament-panels::page>
    <style>
        .gb-tiles { display: grid; gap: .75rem; grid-template-columns: repeat(auto-fit, minmax(9.5rem, 1fr)); }
        .gb-tile { border: 1px solid rgba(127,127,127,.25); border-radius: .9rem; padding: .8rem 1rem; }
        .gb-tile b { display: block; font-size: 1.7rem; line-height: 1.2; }
        .gb-tabs { display: flex; flex-wrap: wrap; gap: .4rem; }
        .gb-tabs button { border: 2px solid #13212C; border-radius: 999px; padding: .3rem 1rem; font-weight: 700; min-height: 2.6rem; }
        .gb-tabs button.on { background: #13212C; color: #fff; }
        .gb-table { width: 100%; border-collapse: collapse; }
        .gb-table th { text-align: left; font-size: .8rem; opacity: .7; padding: .5rem; border-bottom: 2px solid rgba(127,127,127,.3); }
        .gb-table td { padding: .55rem .5rem; border-bottom: 1px solid rgba(127,127,127,.15); vertical-align: top; }
        .gb-muted { opacity: .7; font-size: .85rem; }
        .gb-tag { font-size: .75rem; font-weight: 700; padding: .05rem .45rem; border-radius: .35rem; background: #F08D90; color: #13212C; }
        .gb-days { display: grid; gap: .25rem; grid-template-columns: repeat(auto-fill, minmax(11rem, 1fr)); margin: .4rem 0; }
        .gb-day { background: rgba(127,127,127,.07); border-radius: .5rem; padding: .35rem .55rem; font-size: .85rem; }
    </style>

    <div class="gb-tiles">
        <div class="gb-tile"><span class="gb-muted">{{ __('আজ খেলেছেন') }}</span><b>{{ Bn::num((string) $sum['today']) }} {{ __('জন') }}</b><span class="gb-muted">{{ Bn::num((string) $sum['todayGames']) }}{{ __('টি খেলা') }}</span></div>
        <div class="gb-tile"><span class="gb-muted">{{ __('সাত দিনে') }}</span><b>{{ Bn::num((string) $sum['week']) }} {{ __('জন') }}</b><span class="gb-muted">{{ __('নতুন') }} {{ Bn::num((string) $sum['new']) }} {{ __('জন') }}</span></div>
        <div class="gb-tile"><span class="gb-muted">{{ __('এই মাসে') }}</span><b>{{ Bn::num((string) $sum['month']) }} {{ __('জন') }}</b></div>
        <div class="gb-tile"><span class="gb-muted">{{ __('আজ কোন খেলা কতবার') }}</span>
            <div style="margin-top:.3rem;line-height:1.7">@forelse ($sum['byGame'] as $g => $n)<div>{{ __($games[$g] ?? $g) }}: <b style="display:inline;font-size:1rem">{{ Bn::num((string) $n) }}</b></div>@empty<span class="gb-muted">—</span>@endforelse</div></div>
    </div>

    <x-filament::section>
        <x-slot name="heading">{{ __('তালিকা') }}</x-slot>
        <x-slot name="description">{{ __('পাঠকেরা খেলার পাতায় যেমন দেখেন, সেই ক্রমে। নাম লুকোনো বা ডেস্কের বাদ দেওয়া খেলোয়াড়ও এখানে আছেন, চিহ্ন দিয়ে; তাঁদের ক্রম নেই। নামে চাপলে সেই সময়ের খেলাগুলো দেখা যাবে।') }}</x-slot>
        <div class="gb-tabs" style="margin-bottom:.8rem">
            @foreach (\App\Filament\Pages\GameBoard::RANGES as $k => $label)
                <button type="button" wire:click="setRange('{{ $k }}')" class="{{ $this->range === $k ? 'on' : '' }}">{{ __($label) }}</button>
            @endforeach
        </div>
        @if (! $rows)
            <p class="gb-muted">{{ __('এই সময়ে কেউ খেলেননি।') }}</p>
        @else
            <div style="overflow-x:auto">
                <table class="gb-table">
                    <thead><tr><th>{{ __('ক্রম') }}</th><th>{{ __('খেলোয়াড়') }}</th><th>{{ __('পয়েন্ট') }}</th><th>{{ __('দিন') }}</th><th>{{ __('মোট সময়') }}</th><th>{{ __('স্তর') }}</th></tr></thead>
                    <tbody>
                        @foreach (array_slice($rows, 0, 100) as $r)
                            <tr wire:key="gb-{{ $r['user_id'] }}">
                                <td style="font-weight:800">{{ $r['rank'] ? Bn::num((string) $r['rank']) : '—' }}</td>
                                <td>
                                    <button type="button" wire:click="toggle({{ $r['user_id'] }})" style="font-weight:700;text-decoration:underline;text-align:left">{{ $r['name'] }}</button>
                                    @if ($r['why'])<span class="gb-tag">{{ __($r['why']) }}</span>@endif
                                    <div class="gb-muted">{{ $r['email'] }} · <a href="{{ $this->userUrl($r['user_id']) }}" style="text-decoration:underline">{{ __('ব্যবহারকারী') }}</a></div>
                                    @if ($this->open === $r['user_id'])
                                        <div class="gb-days">
                                            @foreach ($this->games($r['user_id']) as $day => $list)
                                                <div class="gb-day"><b>{{ Bn::num(\Illuminate\Support\Carbon::parse($day)->locale(app()->getLocale())->isoFormat('D MMM')) }}</b> · {{ Bn::num((string) GameScores::dayTotal(array_map(fn ($x) => $x[0], $list))) }}
                                                    <div class="gb-muted">@foreach ($list as $g => $x){{ __($games[$g] ?? $g) }} {{ Bn::num((string) $x[0]) }}@if (! $loop->last) · @endif @endforeach</div></div>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td style="font-weight:700">{{ Bn::num((string) $r['points']) }}</td>
                                <td>{{ Bn::num((string) $r['days']) }}@if ($this->range === 'year') <span class="gb-muted">({{ Bn::num((string) $r['months']) }} {{ __('মাস') }})</span>@endif</td>
                                <td>{{ Bn::num((string) intdiv($r['seconds'], 60)) }} {{ __('মিনিট') }}</td>
                                <td class="gb-muted">{{ $r['level'] ? __($r['level']) : '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if (count($rows) > 100)<p class="gb-muted" style="margin-top:.5rem">{{ __('প্রথম ১০০ জন দেখানো হলো।') }}</p>@endif
        @endif
        <p class="gb-muted" style="margin-top:.8rem">{{ __('মাসের হিসাব: সেরা ২০ দিন। বছরের হিসাব: সেরা ১০ মাস। কাউকে তালিকা থেকে বাদ দিতে: প্রশাসন → ব্যবহারকারী → “খেলার তালিকা থেকে বাদ”।') }}</p>
    </x-filament::section>

    <x-filament::section collapsible>
        <x-slot name="heading">{{ __('খেতাব (মাস ও বছর)') }}</x-slot>
        @if (! $hall['months'] && ! $hall['years'])
            <p class="gb-muted">{{ __('এখনও কোনো খেতাব দেওয়া হয়নি। মাস শেষ হলে সেই মাসের প্রথম তিনজন খেতাব পান; বছর শেষ হলে বছরের।') }}</p>
        @else
            <table class="gb-table">
                <thead><tr><th>{{ __('কবে') }}</th><th>{{ __('স্থান') }}</th><th>{{ __('খেলোয়াড়') }}</th><th>{{ __('পয়েন্ট') }}</th></tr></thead>
                <tbody>
                    @foreach (array_merge($hall['years'], $hall['months']) as $t)
                        <tr><td>{{ $t['label'] }}</td><td>{{ __(\App\Support\GameTitles::PLACES[$t['place']] ?? '') }}</td><td>{{ $t['name'] }}</td><td>{{ Bn::num((string) $t['points']) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-filament::section>
</x-filament-panels::page>
