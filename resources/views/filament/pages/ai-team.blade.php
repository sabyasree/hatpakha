{{-- The AI team (D49, D61): status → plan (what's next, what's waiting) → the team → latest work.
     Inline styles: /desk has no utility CSS. --}}
@php
    use App\Support\Bn;
    $d = $this->data();
    $n = fn ($v) => Bn::num((string) $v);
    $ago = fn ($t) => $t ? $n(\Illuminate\Support\Carbon::parse($t)->locale(app()->getLocale())->diffForHumans()) : __('কখনও না');
    $when = fn ($t) => $n($t->copy()->timezone('Asia/Kolkata')->locale(app()->getLocale())->isoFormat('ddd, D MMM · h:mm A'));
    $ok = $d['enabled'] && ! $d['teamPaused'] && $d['clock'] && $d['clock']->gt(now()->subMinutes(20));
    $agents = \App\Ai\Team::AGENTS;
@endphp
<x-filament-panels::page>
    <style>
        .hp-kpis { display: grid; gap: .75rem; grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr)); }
        .hp-kpi { border: 1px solid rgba(127,127,127,.22); border-radius: .9rem; padding: .9rem 1rem; background: rgba(127,127,127,.04); }
        .hp-kpi b { display: block; font-size: 1.6rem; line-height: 1.2; }
        .hp-muted { font-size: .875rem; opacity: .7; }
        .hp-grid2 { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(20rem, 1fr)); }
        .hp-list { display: grid; gap: 0; }
        .hp-row { display: flex; gap: .75rem; align-items: flex-start; padding: .7rem 0; border-bottom: 1px solid rgba(127,127,127,.15); }
        .hp-row:last-child { border-bottom: 0; }
        .hp-dot { width: .6rem; height: .6rem; border-radius: 999px; margin-top: .45rem; flex: none; }
        .hp-chip { display: inline-block; font-size: .72rem; padding: .05rem .5rem; border-radius: 999px; border: 1px solid rgba(127,127,127,.3); margin: .15rem .2rem 0 0; }
        .hp-team { display: grid; gap: 0; }
        .hp-member { display: grid; grid-template-columns: minmax(0, 2.2fr) minmax(0, 1fr) auto; gap: 1rem; align-items: center; padding: .85rem 0; border-bottom: 1px solid rgba(127,127,127,.15); }
        .hp-member:last-child { border-bottom: 0; }
        .hp-num { font-variant-numeric: tabular-nums; }
        .hp-bar { height: .4rem; border-radius: 999px; background: rgba(127,127,127,.15); overflow: hidden; margin-top: .35rem; }
        .hp-bar span { display: block; height: 100%; background: #2DAAE2; }
        @media (max-width: 720px) { .hp-member { grid-template-columns: 1fr; gap: .4rem; } }
    </style>

    {{-- 1. Status at a glance --}}
    <div class="hp-kpis">
        <div class="hp-kpi">
            <span class="hp-muted">{{ __('পুরো দল') }}</span>
            <b style="color:{{ $ok ? '#1A86C2' : '#CB1B32' }}">{{ ! $d['enabled'] ? __('বন্ধ') : ($d['teamPaused'] ? __('থামানো') : ($ok ? __('কাজ করছে') : __('ঘড়ি থেমে আছে'))) }}</b>
            <span class="hp-muted">{{ __('শেষ কাজ') }}: {{ $ago($d['clock']) }}</span>
        </div>
        <div class="hp-kpi"><span class="hp-muted">{{ __('এই মাসে কাজ') }}</span><b class="hp-num">{{ $n(number_format($d['monthJobs'])) }}</b></div>
        <div class="hp-kpi"><span class="hp-muted">{{ __('এই মাসের খরচ') }}</span><b class="hp-num">${{ $n(number_format($d['monthCost'], 2)) }}</b>
            <span class="hp-muted">{{ __('মাস শেষে আনুমানিক') }} ${{ $n(number_format($d['forecast'], 2)) }}</span></div>
        <div class="hp-kpi"><span class="hp-muted">{{ __('সতর্কতা') }}</span><b class="hp-num" style="color:{{ $d['alerts']->isEmpty() ? 'inherit' : '#CB1B32' }}">{{ $n($d['alerts']->count()) }}</b>
            <span class="hp-muted">{{ $d['alerts']->isEmpty() ? __('সব ঠিক চলছে') : __('নিচে দেখুন') }}</span></div>
    </div>

    @foreach ($d['alerts'] as $a)
        <div style="padding:.7rem 1rem;border-radius:.75rem;border:1px solid #E39A6A;background:rgba(239,192,141,.15)">⚠ {{ $a->title }}</div>
    @endforeach

    {{-- 2. The plan --}}
    <div class="hp-grid2">
        <x-filament::section icon="heroicon-o-calendar-days">
            <x-slot name="heading">{{ __('পরের কাজ') }}</x-slot>
            <x-slot name="description">{{ __('দল নিজে থেকে কখন কী করবে') }}</x-slot>
            <div class="hp-list">
                @foreach ($d['plan']['events'] as $e)
                    <div class="hp-row">
                        <span class="hp-dot" style="background:#2DAAE2"></span>
                        <div style="min-width:0;flex:1">
                            <div style="display:flex;justify-content:space-between;gap:.5rem;flex-wrap:wrap">
                                <strong>{{ __($e['title']) }}</strong>
                                <span class="hp-muted hp-num">{{ $when($e['next']) }}</span>
                            </div>
                            <div class="hp-muted">{{ __($e['what']) }}</div>
                            <div>@foreach ($e['who'] as $w)<span class="hp-chip">{{ __($agents[$w][0] ?? $w) }}</span>@endforeach</div>
                        </div>
                    </div>
                @endforeach
            </div>
            <div style="margin-top:.75rem">
                <strong>{{ __('সামনের তারিখ') }}</strong>
                @foreach ($d['plan']['milestones'] as [$date, $what, $who])
                    <div class="hp-row">
                        <span class="hp-dot" style="background:#CB1B32"></span>
                        <div style="flex:1"><div style="display:flex;justify-content:space-between;gap:.5rem;flex-wrap:wrap">
                            <span>{{ __($what) }}</span><span class="hp-muted hp-num">{{ $n($date->locale(app()->getLocale())->isoFormat('D MMM')) }}</span></div>
                            <span class="hp-chip">{{ __($agents[$who][0] ?? $who) }}</span></div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>

        <x-filament::section icon="heroicon-o-inbox-stack">
            <x-slot name="heading">{{ __('যে কাজ বাকি') }}</x-slot>
            <x-slot name="description">{{ __('দল এগুলো ধীরে ধীরে শেষ করছে; আপনাকে কিছু করতে হবে না') }}</x-slot>
            @php($max = max(1, ...array_column($d['plan']['queues'], 1)))
            @foreach ($d['plan']['queues'] as [$label, $count, $who])
                <div style="padding:.55rem 0">
                    <div style="display:flex;justify-content:space-between;gap:.5rem"><span>{{ __($label) }}</span><strong class="hp-num">{{ $n(number_format($count)) }}</strong></div>
                    <div class="hp-bar"><span style="width:{{ $count ? max(3, round($count / $max * 100)) : 0 }}%"></span></div>
                    <span class="hp-muted">{{ __($agents[$who][0] ?? $who) }}</span>
                </div>
            @endforeach
        </x-filament::section>
    </div>

    {{-- The strategist's plan for the month (D63) --}}
    <x-filament::section icon="heroicon-o-light-bulb">
        <x-slot name="heading">{{ __('এই মাসের কৌশল') }}</x-slot>
        <x-slot name="description">{{ $d['strategy'] ? __('কৌশলবিদের পরিকল্পনা').', '.$n($d['strategy']['at']).'। '.__('সম্পাদক, মার্কেটিং, কপিরাইটার আর প্রধান সম্পাদক এই অগ্রাধিকার মেনে কাজ করে।') : __('প্রতি মাসের ১ তারিখে কৌশলবিদ বাজার ও অন্য পত্রিকার খোঁজ নিয়ে পরিকল্পনা দেবে।') }}</x-slot>
        <x-slot name="afterHeader">
            @if (auth()->user()->hasRole(\App\Enums\Role::Admin))
                <x-filament::button size="sm" color="gray" icon="heroicon-o-arrow-path" wire:click="newStrategy" :disabled="$d['strategyPending']">
                    {{ $d['strategyPending'] ? __('তৈরি হচ্ছে…') : __('এখনই নতুন পরিকল্পনা') }}</x-filament::button>
            @endif
        </x-slot>
        @if ($m = $d['strategy'])
            <p style="margin:0 0 .75rem">{{ $m['summary'] }}</p>
            <div class="hp-list">
                @foreach ($m['priorities'] as $i => $p)
                    <div class="hp-row">
                        <strong class="hp-num" style="min-width:1.5rem">{{ $n($i + 1) }}.</strong>
                        <div style="flex:1;min-width:0">{{ $p['what'] }}
                            <div><span class="hp-chip">{{ __($agents[$p['who']][0] ?? ($p['who'] === 'owner' ? 'আপনি' : $p['who'])) }}</span>
                                @if ($p['check'])<span class="hp-muted"> {{ __('মাপকাঠি:') }} {{ $p['check'] }}</span>@endif</div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>

    {{-- The trend scout's news: what the team keeps in mind this week (D62) --}}
    <x-filament::section icon="heroicon-o-sparkles" collapsible>
        <x-slot name="heading">{{ __('এই সপ্তাহের খবর') }}</x-slot>
        <x-slot name="afterHeader">
            <x-filament::button size="sm" color="gray" icon="heroicon-o-magnifying-glass" wire:click="findTrends">{{ __('এখনই খুঁজুন') }}</x-filament::button>
        </x-slot>
        <x-slot name="description">{{ $d['trends'] ? __('ট্রেন্ড-সন্ধানী খুঁজেছে').' '.$n($d['trends']['at']).'। '.__('মার্কেটিং, চিঠি, সংখ্যা আর সংকলনের কাজে দল এগুলো মাথায় রাখে।') : __('প্রতি সোমবার ট্রেন্ড-সন্ধানী বাংলা সাহিত্য ও সংস্কৃতির খবর খুঁজে এখানে রাখবে।') }}</x-slot>
        @if ($d['trends'])
            <div class="hp-list">
                @foreach ($d['trends']['items'] as $t)
                    <div class="hp-row" style="padding:.5rem 0">
                        <span class="hp-dot" style="background:#EFC08D"></span>
                        <div style="flex:1;min-width:0"><a href="{{ $t['url'] }}" target="_blank" rel="noopener noreferrer nofollow" style="font-weight:600;text-decoration:underline">{{ $t['title'] }}</a>
                            @if ($t['when'])<span class="hp-muted"> · {{ $t['when'] }}</span>@endif
                            <div class="hp-muted">{{ $t['why'] }}</div></div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>

    {{-- Work the owner gave the team by hand (D72) --}}
    @php($busy = $d['requests']->whereIn('status', ['queued', 'running'])->isNotEmpty())
    <div @if ($busy) wire:poll.15s @endif>
    <x-filament::section icon="heroicon-o-clipboard-document-check">
        <x-slot name="heading">{{ __('আপনার দেওয়া কাজ') }}</x-slot>
        <x-slot name="description">{{ __('উপরের "দলকে কাজ দিন" বা কোনো সদস্যের "কাজ দিন" থেকে। দল নিজের সময়সূচির বাইরে এগুলো এখনই করে; এক-দুই মিনিটের মধ্যে শুরু হয়।') }}</x-slot>
        <div class="hp-list">
            @forelse ($d['requests'] as $r)
                @php($color = ['queued' => '#EFC08D', 'running' => '#2DAAE2', 'done' => '#1A86C2', 'failed' => '#CB1B32'][$r->status] ?? '#13212C')
                <div class="hp-row" wire:key="req-{{ $r->id }}">
                    <span class="hp-dot" style="background:{{ $color }}"></span>
                    <div style="flex:1;min-width:0">
                        <div style="display:flex;justify-content:space-between;gap:.5rem;flex-wrap:wrap">
                            <strong>{{ $r->agent ? __($agents[$r->agent][0] ?? $r->agent) : __('প্রধান সম্পাদক ঠিক করছে') }} · {{ __(\App\Ai\TeamJobs::label($r->agent, $r->job)) }}@if ($r->redo) · {{ __('আবার') }}@endif</strong>
                            <span class="hp-muted hp-num">{{ __(['queued' => 'অপেক্ষায়', 'running' => 'কাজ চলছে…', 'done' => 'হয়ে গেছে', 'failed' => 'হয়নি'][$r->status] ?? $r->status) }} · {{ $ago($r->finished_at ?? $r->created_at) }}</span>
                        </div>
                        @if ($r->instruction && ! str_starts_with((string) $r->link, 'post:') && ! ctype_digit((string) $r->instruction))
                            <div class="hp-muted">“{{ \Illuminate\Support\Str::limit($r->instruction, 200) }}”</div>
                        @endif
                        @if ($r->result)
                            @if (mb_strlen($r->result) > 300)
                                <details style="margin-top:.3rem"><summary style="cursor:pointer;font-weight:600">{{ __('দলের লেখা উত্তর দেখুন') }}</summary>
                                    <div x-data="{ copied: false }">
                                        <div x-ref="t" style="white-space:pre-wrap;line-height:1.7;margin-top:.4rem;padding:.7rem;border-radius:.6rem;background:rgba(127,127,127,.08)">{{ $r->result }}</div>
                                        <x-filament::button size="xs" color="gray" icon="heroicon-o-clipboard" style="margin-top:.4rem"
                                            x-on:click="navigator.clipboard.writeText($refs.t.innerText); copied = true; setTimeout(() => copied = false, 1500)"><span x-text="copied ? '{{ __('কপি হয়েছে') }}' : '{{ __('কপি') }}'"></span></x-filament::button>
                                    </div>
                                </details>
                            @else
                                <div style="margin-top:.2rem">{{ $r->result }}</div>
                            @endif
                        @endif
                        <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.35rem">
                            @if ($r->status === 'done' && ($l = \App\Filament\Pages\AiTeam::resultLink($r)))
                                <x-filament::button size="xs" tag="a" :href="$l[1]" icon="heroicon-o-arrow-top-right-on-square">{{ $l[0] }}</x-filament::button>
                            @endif
                            @if (in_array($r->status, ['done', 'failed'], true))
                                <x-filament::button size="xs" color="gray" icon="heroicon-o-arrow-path" wire:click="again({{ $r->id }})">{{ __('আবার করুন') }}</x-filament::button>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="hp-muted">{{ __('এখনও হাতে দেওয়া কোনো কাজ নেই। উপরের "দলকে কাজ দিন" বোতাম বা নিচে কোনো সদস্যের "কাজ দিন" টিপুন।') }}</div>
            @endforelse
        </div>
    </x-filament::section>
    </div>

    {{-- 3. The team --}}
    <x-filament::section icon="heroicon-o-user-group">
        <x-slot name="heading">{{ __('দল') }} ({{ $n(count($agents)) }})</x-slot>
        <x-slot name="description">{{ __('কে কী করে, এই মাসে কত কাজ ও খরচ। থামালে শুধু সেই সদস্য বিশ্রাম নেবে।') }}</x-slot>
        <div class="hp-team">
            @foreach ($agents as $key => [$name, $job])
                @php($s = $d['stats'][$key])
                @php($paused = \App\Ai\Team::paused($key))
                <div class="hp-member" wire:key="agent-{{ $key }}">
                    <div style="min-width:0">
                        <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
                            <strong style="font-size:1.02rem">{{ __($name) }}</strong>
                            <x-filament::badge :color="$paused ? 'warning' : 'success'" size="sm">{{ $paused ? __('থামানো') : __('চালু') }}</x-filament::badge>
                        </div>
                        <div class="hp-muted">{{ __($job) }}</div>
                    </div>
                    <div class="hp-num">
                        <div>{{ $n($s['calls']) }} {{ __('কাজ') }} · ${{ $n(number_format($s['cost'], 2)) }}</div>
                        <div class="hp-muted">{{ __('শেষ কাজ:') }} {{ $ago($s['last']) }}</div>
                    </div>
                    <div style="display:flex;gap:.4rem;flex-wrap:wrap">
                        @if (isset(\App\Ai\TeamJobs::JOBS[$key]))
                            {{ ($this->giveAction)(['agent' => $key]) }}
                        @endif
                        {{ ($this->activityAction)(['agent' => $key]) }}
                        <x-filament::button size="sm" :color="$paused ? 'success' : 'gray'" :icon="$paused ? 'heroicon-o-play' : 'heroicon-o-pause'" wire:click="toggle('{{ $key }}')">
                            {{ $paused ? __('চালু করুন') : __('থামান') }}
                        </x-filament::button>
                    </div>
                </div>
            @endforeach
        </div>
    </x-filament::section>

    {{-- 4. Latest work, whole team --}}
    <x-filament::section icon="heroicon-o-clock" collapsible>
        <x-slot name="heading">{{ __('সাম্প্রতিক কাজ') }}</x-slot>
        <div class="hp-list">
            @forelse ($d['feed'] as $f)
                <div class="hp-row" style="padding:.45rem 0">
                    <span class="hp-muted hp-num" style="min-width:8.5rem">{{ $ago($f['at']) }}</span>
                    <span style="flex:1;min-width:0"><strong>{{ __($agents[$f['agent']][0] ?? '') }}</strong> · {{ __(\App\Ai\Team::PURPOSES[$f['purpose']] ?? $f['purpose']) }}
                        @if ($f['title']) · <a href="{{ \App\Filament\Resources\Posts\PostResource::getUrl('edit', ['record' => $f['post']]) }}" style="text-decoration:underline">{{ \Illuminate\Support\Str::limit($f['title'], 50) }}</a>@endif</span>
                </div>
            @empty
                <div class="hp-muted">{{ __('এখনও কোনো কাজ হয়নি।') }}</div>
            @endforelse
        </div>
    </x-filament::section>

</x-filament-panels::page>
