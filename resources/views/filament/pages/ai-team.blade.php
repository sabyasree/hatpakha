{{-- The AI team (D49). Inline styles: /desk has no utility CSS. --}}
@php
    use App\Support\Bn;
    $d = $this->data();
    $n = fn ($v) => Bn::num((string) $v);
@endphp
<x-filament-panels::page>
    <style>
        .hp-team { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fill, minmax(17rem, 1fr)); }
        .hp-agent { border: 1px solid rgba(127,127,127,.25); border-radius: .9rem; padding: 1rem; display: flex; flex-direction: column; gap: .5rem; }
        .hp-muted { font-size: .875rem; opacity: .72; }
    </style>

    <x-filament::section icon="heroicon-o-shield-check">
        <x-slot name="heading">{{ __('প্রধান সম্পাদকের রিপোর্ট') }}</x-slot>
        <div style="line-height:1.9">
            <div>{{ __('পুরো দল') }}: <x-filament::badge :color="! $d['enabled'] ? 'gray' : ($d['teamPaused'] ? 'warning' : 'success')">{{ ! $d['enabled'] ? __('বন্ধ') : ($d['teamPaused'] ? __('থামানো') : __('কাজ করছে')) }}</x-filament::badge>
                · {{ __('ঘড়ি') }}: {{ $d['clock'] ? $n($d['clock']->locale(app()->getLocale())->diffForHumans()) : __('কখনও না') }}</div>
            @forelse ($d['alerts'] as $a)
                <div style="margin-top:.4rem;padding:.55rem .8rem;border-radius:.6rem;border:1px solid #E39A6A">⚠ {{ $a->title }}</div>
            @empty
                <div class="hp-muted">{{ __('সব ঠিক চলছে। কোনো সমস্যা হলে এখানে আর আজকের কাজে জানাবে।') }}</div>
            @endforelse
        </div>
    </x-filament::section>

    <div class="hp-team">
        @foreach (\App\Ai\Team::AGENTS as $key => [$name, $job])
            @php($s = $d['stats'][$key])
            @php($paused = \App\Ai\Team::paused($key))
            <div class="hp-agent" wire:key="agent-{{ $key }}">
                <div style="display:flex;justify-content:space-between;gap:.5rem;align-items:center">
                    <strong style="font-size:1.05rem">{{ __($name) }}</strong>
                    <x-filament::badge :color="$paused ? 'warning' : 'success'">{{ $paused ? __('থামানো') : __('চালু') }}</x-filament::badge>
                </div>
                <div class="hp-muted">{{ __($job) }}</div>
                <div>{{ __('এই মাসে') }}: {{ $n($s['calls']) }} {{ __('কাজ') }} · {{ $n(number_format($s['cost'], 2)) }} {{ __('ডলার') }}</div>
                <div class="hp-muted">{{ __('শেষ কাজ:') }} {{ $s['last'] ? $n(\Illuminate\Support\Carbon::parse($s['last'])->locale(app()->getLocale())->diffForHumans()) : __('কখনও না') }}</div>
                <div>
                    <x-filament::button size="sm" :color="$open === $key ? 'primary' : 'gray'" icon="heroicon-o-queue-list" wire:click="show('{{ $key }}')">{{ __('কী করেছে') }}</x-filament::button>
                    <x-filament::button size="sm" :color="$paused ? 'success' : 'gray'" :icon="$paused ? 'heroicon-o-play' : 'heroicon-o-pause'" wire:click="toggle('{{ $key }}')">
                        {{ $paused ? __('আবার চালু করুন') : __('থামান') }}
                    </x-filament::button>
                </div>
                @if ($open === $key)
                    @php([$where, $whereUrl] = \App\Filament\Pages\AiTeam::whereToSee($key))
                    <div style="border-top:1px solid rgba(127,127,127,.2);padding-top:.5rem">
                        <div class="hp-muted">{{ __('ফল কোথায় দেখবেন:') }} <a href="{{ $whereUrl }}" style="text-decoration:underline">{{ $where }}</a></div>
                        @php($jobs = $this->recent($key))
                        @forelse ($jobs as $j)
                            <div style="font-size:.85rem;padding:.2rem 0;border-bottom:1px dashed rgba(127,127,127,.15)">
                                <span class="hp-muted">{{ $n($j['at']->locale(app()->getLocale())->diffForHumans()) }}</span> ·
                                {{ __(\App\Ai\Team::PURPOSES[$j['purpose']] ?? $j['purpose']) }}
                                @if ($j['post']) · <a href="{{ \App\Filament\Resources\Posts\PostResource::getUrl('edit', ['record' => $j['post']->id]) }}" style="text-decoration:underline">{{ \Illuminate\Support\Str::limit($j['post']->title, 40) }}</a>@endif
                            </div>
                        @empty
                            <div class="hp-muted">{{ __('এখনও কোনো কাজ করেনি।') }}</div>
                        @endforelse
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
