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
        /* Job steps (owner + UX review, 2026-11-11): shape first, colour second; logo palette only. */
        .hp-req-summary { display: flex; flex-wrap: wrap; gap: .4rem; align-items: center; margin-bottom: .6rem; }
        .hp-req-pill { font-size: .85rem; font-weight: 600; padding: .2rem .65rem; border-radius: 999px; border: 1px solid rgba(127,127,127,.35); }
        .hp-req-pill--ready { background: #13212C; color: #FFFBF4; border-color: #13212C; }
        .hp-req-pill--failed { background: #CB1B32; color: #fff; border-color: #CB1B32; }
        .hp-req-new { display: inline-block; font-size: .7rem; font-weight: 700; padding: 0 .45rem; border-radius: 999px; background: #CB1B32; color: #fff; vertical-align: middle; }
        .hp-req-why { margin-top: .35rem; padding: .55rem .7rem; border-left: 3px solid #CB1B32; border-radius: 0 .5rem .5rem 0; background: rgba(239,192,141,.18); white-space: pre-wrap; font-size: .9rem; }
        .hp-steps { margin-top: .35rem; }
        .hp-steps-now { display: flex; gap: .5rem; align-items: center; cursor: pointer; list-style: none; }
        .hp-steps-now::-webkit-details-marker { display: none; }
        .hp-steps-now::after { content: '▾'; opacity: .6; margin-left: auto; }
        .hp-steps[open] .hp-steps-now::after { content: '▴'; }
        .hp-step-list { list-style: none; margin: .5rem 0 0; padding: 0 0 0 .2rem; }
        .hp-step { position: relative; display: flex; gap: .6rem; padding: 0 0 .7rem; }
        .hp-step:not(:last-child)::before { content: ''; position: absolute; left: .55rem; top: 1.25rem; bottom: .1rem; border-left: 2px solid rgba(127,127,127,.3); }
        .hp-step--done:not(:last-child)::before { border-left-color: #13212C; }
        .hp-step-mark { position: relative; flex: none; width: 1.15rem; height: 1.15rem; border-radius: 999px; display: inline-flex; align-items: center; justify-content: center; font-size: .7rem; font-weight: 700; line-height: 1; }
        .hp-step-mark--done { background: #13212C; color: #FFFBF4; }
        .hp-step-mark--done::after { content: '✓'; }
        .hp-step-mark--current { border: 3px solid #CB1B32; }
        .hp-step-mark--pending { border: 2px solid rgba(19,33,44,.4); }
        .hp-step-mark--skipped { border: 2px dashed rgba(19,33,44,.45); }
        .hp-step-mark--skipped::after { content: '–'; opacity: .6; }
        .hp-step-mark--retry { background: #EFC08D; color: #13212C; }
        .hp-step-mark--retry::after { content: '↻'; }
        .hp-step-mark--failed { background: #CB1B32; color: #fff; }
        .hp-step-mark--failed::after { content: '✕'; }
        .dark .hp-step-mark--done { background: #FFFBF4; color: #13212C; }
        .dark .hp-step-mark--pending, .dark .hp-step-mark--skipped { border-color: rgba(255,251,244,.45); }
        .hp-step--current .hp-step-label { font-weight: 700; }
        .hp-step--pending .hp-step-label, .hp-step--skipped .hp-step-label { opacity: .55; }
        .hp-step--failed .hp-step-label { color: #CB1B32; font-weight: 700; }
        @media (prefers-reduced-motion: no-preference) {
            .hp-step-mark--current { animation: hp-pulse 1.8s ease-in-out infinite; }
            @keyframes hp-pulse { 50% { box-shadow: 0 0 0 4px rgba(203,27,50,.18); } }
        }
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
        <x-filament::section icon="heroicon-o-calendar-days" collapsible persist-collapsed collapse-id="hp-ai-team-1">
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

        <x-filament::section icon="heroicon-o-inbox-stack" collapsible persist-collapsed collapse-id="hp-ai-team-2">
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
    <x-filament::section icon="heroicon-o-light-bulb" collapsible persist-collapsed collapse-id="hp-ai-team-3">
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
    <div @if ($busy) wire:poll.10s @endif>
    <x-filament::section icon="heroicon-o-clipboard-document-check" collapsible persist-collapsed collapse-id="hp-ai-team-4">
        <x-slot name="heading">{{ __('আপনার দেওয়া কাজ') }}</x-slot>
        <x-slot name="description">{{ __('উপরের “দলকে কাজ দিন” বা কোনো সদস্যের “কাজ দিন” থেকে। দল নিজের সময়সূচির বাইরে এগুলো এখনই করে; এক-দুই মিনিটের মধ্যে শুরু হয়, বড় লেখায় ৫-১০ মিনিট লাগতে পারে। এই তালিকা নিজে থেকেই হালনাগাদ হয়। সাময়িক সমস্যায় দল নিজেই আবার চেষ্টা করে (৩ বার পর্যন্ত); শেষ হলে বা না হলে ফোনে খবর আসে (ফোনে খবর চালু থাকলে)।') }}</x-slot>
        @if (\App\Filament\Support\Deleting::isAdmin() && $d['requests']->whereIn('status', ['done', 'failed'])->isNotEmpty())
            <x-slot name="afterHeader">
                {{-- Owner (2026-11-11): choose what to clear; each choice says how many and asks first. --}}
                @php($seenDone = $d['requests']->where('status', 'done')->whereNotNull('seen_at')->count())
                @php($allDone = $d['requests']->where('status', 'done')->count())
                @php($failedN = $d['requests']->where('status', 'failed')->count())
                <x-filament::dropdown placement="bottom-end">
                    <x-slot name="trigger">
                        <x-filament::button size="sm" color="danger" outlined icon="heroicon-o-trash" icon-position="before">{{ __('তালিকা থেকে মুছুন…') }}</x-filament::button>
                    </x-slot>
                    <x-filament::dropdown.list>
                        @if ($seenDone)
                            <x-filament::dropdown.list.item icon="heroicon-o-check-circle" wire:click="clearRequests('done')" wire:confirm="{{ __('দেখা হয়ে গেছে এমন শেষ হওয়া কাজগুলো মুছবেন?') }}">{{ __('দেখা হয়ে যাওয়া শেষ কাজ') }} ({{ $n($seenDone) }})</x-filament::dropdown.list.item>
                        @endif
                        @if ($allDone)
                            <x-filament::dropdown.list.item icon="heroicon-o-check-badge" wire:click="clearRequests('done_all')" wire:confirm="{{ __('সব শেষ হওয়া কাজ মুছবেন? নতুন, এখনও না-দেখাগুলোও যাবে।') }}">{{ __('সব শেষ হওয়া কাজ') }} ({{ $n($allDone) }})</x-filament::dropdown.list.item>
                        @endif
                        @if ($failedN)
                            <x-filament::dropdown.list.item icon="heroicon-o-x-circle" wire:click="clearRequests('failed')" wire:confirm="{{ __('যে কাজগুলো হয়নি, সেগুলো মুছবেন?') }}">{{ __('যে কাজগুলো হয়নি') }} ({{ $n($failedN) }})</x-filament::dropdown.list.item>
                        @endif
                        @if ($allDone && $failedN)
                            <x-filament::dropdown.list.item icon="heroicon-o-trash" color="danger" wire:click="clearRequests('all')" wire:confirm="{{ __('শেষ হওয়া আর না-হওয়া সব কাজ মুছবেন?') }}">{{ __('শেষ হওয়া আর না-হওয়া সব') }} ({{ $n($allDone + $failedN) }})</x-filament::dropdown.list.item>
                        @endif
                    </x-filament::dropdown.list>
                </x-filament::dropdown>
                <span class="hp-muted" style="font-size:.8rem">{{ __('অপেক্ষায় বা চলমান কাজ এখান থেকে মোছে না।') }}</span>
            </x-slot>
        @endif
        {{-- Owner + UX review (2026-11-11): what needs you first, then what is running; each job shows its steps. --}}
        @php($reqs = $d['requests'])
        @php($cnt = ['ready' => $reqs->where('status', 'done')->whereNull('seen_at')->count(), 'failed' => $reqs->where('status', 'failed')->whereNull('seen_at')->count(),
            'running' => $reqs->where('status', 'running')->count(), 'waiting' => $reqs->where('status', 'queued')->count()])
        @if (array_sum($cnt) > 0)
            <div class="hp-req-summary" role="status">
                @if ($cnt['ready'])<span class="hp-req-pill hp-req-pill--ready">✓ {{ __('আপনার দেখার জন্য তৈরি') }} {{ $n($cnt['ready']) }}</span>@endif
                @if ($cnt['failed'])<span class="hp-req-pill hp-req-pill--failed">✕ {{ __('কাজ হয়নি') }} {{ $n($cnt['failed']) }}</span>@endif
                @if ($cnt['running'])<span class="hp-req-pill">◉ {{ __('চলছে') }} {{ $n($cnt['running']) }}</span>@endif
                @if ($cnt['waiting'])<span class="hp-req-pill">○ {{ __('অপেক্ষায়') }} {{ $n($cnt['waiting']) }}</span>@endif
                @if ($busy)<span class="hp-muted" style="margin-left:auto;font-size:.8rem">{{ __('শেষ দেখা') }}: {{ $n(now()->timezone('Asia/Kolkata')->format('H:i:s')) }}</span>@endif
            </div>
        @endif
        <div class="hp-list">
            @forelse ($reqs as $r)
                @php($steps = \App\Filament\Pages\AiTeam::stepsFor($r))
                @php($current = $r->status === 'failed' ? end($steps) : (collect($steps)->first(fn ($st) => in_array($st['state'], ['current', 'retry'], true)) ?? collect($steps)->last(fn ($st) => $st['state'] === 'done')))
                @php($new = in_array($r->status, ['done', 'failed'], true) && ! $r->seen_at)
                <div class="hp-row" wire:key="req-{{ $r->id }}">
                    <div style="flex:1;min-width:0">
                        <div style="display:flex;justify-content:space-between;gap:.5rem;flex-wrap:wrap;align-items:baseline">
                            <strong>@if ($new)<span class="hp-req-new">{{ __('নতুন') }}</span> @endif{{ $r->agent ? __($agents[$r->agent][0] ?? $r->agent) : __('প্রধান সম্পাদক ঠিক করছে') }} · {{ __(\App\Ai\TeamJobs::label($r->agent, $r->job)) }}@if ($r->redo) · {{ __('আবার') }}@endif</strong>
                            <span class="hp-muted hp-num" style="font-size:.85rem">{{ $ago($r->created_at) }}</span>
                        </div>
                        @if ($r->instruction && ! str_starts_with((string) $r->link, 'post:') && ! ctype_digit((string) $r->instruction))
                            <div class="hp-muted">“{{ \Illuminate\Support\Str::limit($r->instruction, 200) }}”</div>
                        @endif
                        <details class="hp-steps" wire:ignore.self @if (! in_array($r->status, ['done'], true) || $new) open @endif
                            @if ($new) x-on:toggle="if ($el.open) $wire.markSeen({{ $r->id }})" @endif>
                            <summary class="hp-steps-now"><span class="hp-step-mark hp-step-mark--{{ $current['state'] }}" aria-hidden="true"></span>
                                <span><strong>{{ $current['label'] }}</strong>@if ($current['sub']) <span class="hp-muted">· {{ $current['sub'] }}</span>@endif</span></summary>
                            <ol class="hp-step-list">
                                @foreach ($steps as $st)
                                    <li class="hp-step hp-step--{{ $st['state'] }}">
                                        <span class="hp-step-mark hp-step-mark--{{ $st['state'] }}" aria-hidden="true"></span>
                                        <div style="min-width:0">
                                            <div><span class="hp-step-label">{{ $st['label'] }}</span>
                                                <span class="sr-only">({{ __(['done' => 'হয়ে গেছে', 'current' => 'এখন চলছে', 'pending' => 'বাকি', 'skipped' => 'দরকার হয়নি', 'retry' => 'আবার চেষ্টা হবে', 'failed' => 'হয়নি'][$st['state']]) }})</span>
                                                @if ($st['at'])<span class="hp-muted hp-num" style="font-size:.8rem"> · {{ $st['at'] }}</span>@endif</div>
                                            @if ($st['sub'])<div class="hp-muted" style="font-size:.85rem">{{ $st['sub'] }}</div>@endif
                                        </div>
                                    </li>
                                @endforeach
                            </ol>
                        </details>
                        @if ($r->result && $r->status !== 'running')
                            @if (in_array($r->status, ['failed', 'queued'], true))
                                <div class="hp-req-why">{{ $r->result }}</div>
                            @elseif (mb_strlen($r->result) > 300)
                                <details style="margin-top:.3rem" @if ($new) x-on:toggle="if ($el.open) $wire.markSeen({{ $r->id }})" @endif><summary style="cursor:pointer;font-weight:600">{{ __('দলের লেখা উত্তর দেখুন') }}</summary>
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
                            @if ($r->status === 'done')
                                @foreach (\App\Filament\Pages\AiTeam::resultLinks($r) as $l)
                                    <x-filament::button size="xs" tag="a" :href="$l[1]" icon="heroicon-o-arrow-top-right-on-square" wire:click="markSeen({{ $r->id }})">{{ $l[0] }}</x-filament::button>
                                @endforeach
                            @endif
                            @if (in_array($r->status, ['done', 'failed'], true))
                                <x-filament::button size="xs" color="gray" icon="heroicon-o-arrow-path" wire:click="again({{ $r->id }})">{{ __('আবার করুন') }}</x-filament::button>
                            @endif
                            @if ($new && $r->status === 'failed')
                                <x-filament::button size="xs" color="gray" wire:click="markSeen({{ $r->id }})">{{ __('দেখেছি') }}</x-filament::button>
                            @endif
                            @if ($r->status !== 'running' && \App\Filament\Support\Deleting::isAdmin())
                                <x-filament::button size="xs" color="danger" outlined icon="heroicon-o-trash" wire:click="deleteRequest({{ $r->id }})" wire:confirm="{{ $r->status === 'queued' ? __('এই কাজটি বাতিল করে মুছে ফেলবেন?') : __('এই কাজটি তালিকা থেকে মুছে ফেলবেন?') }}">{{ $r->status === 'queued' ? __('বাতিল করুন') : __('মুছুন') }}</x-filament::button>
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
    <x-filament::section icon="heroicon-o-user-group" collapsible persist-collapsed collapse-id="hp-ai-team-5">
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
