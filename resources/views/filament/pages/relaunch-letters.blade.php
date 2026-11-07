{{-- D104: the apology letter to writers of the old unanswered submissions. Inline styles: /desk has no utility CSS. --}}
@php
    $d = $this->data();
    $s = $d['stats'];
@endphp
<x-filament-panels::page>
    <style>
        .hp-r-stats { display: grid; gap: .6rem; grid-template-columns: repeat(auto-fit, minmax(8.5rem, 1fr)); }
        .hp-r-stat { border: 1px solid rgba(127,127,127,.3); border-radius: .8rem; padding: .7rem; }
        .hp-r-num { font-size: 1.6rem; font-weight: 800; line-height: 1.1; }
        .hp-muted { font-size: .85rem; opacity: .7; }
        .hp-r-field { width: 100%; border: 1px solid rgba(127,127,127,.4); border-radius: .6rem; padding: .6rem .7rem; background: transparent; }
        .hp-r-table td, .hp-r-table th { padding: .45rem .5rem; text-align: left; border-bottom: 1px solid rgba(127,127,127,.2); vertical-align: top; }
    </style>

    <x-filament::section icon="heroicon-o-information-circle">
        <x-slot name="heading">{{ __('কীভাবে চলে') }}</x-slot>
        <ul style="margin:0;padding-left:1.2rem;line-height:1.9">
            <li>{{ __('প্রথমে এআই সম্পাদক প্রতিটি পুরনো লেখা একবার পড়ে বলে: জিজ্ঞেস করার মতো, না খুব দুর্বল। আপনি যেকোনোটি বদলাতে পারেন।') }}</li>
            <li>{{ __('শুধু "জিজ্ঞেস করার মতো" লেখার লেখকরা একটি চিঠি পান: ক্ষমা, দেরির কারণ, আর প্রশ্ন: লেখাটি কি এখনও প্রকাশ করতে চান? যাঁদের সব লেখা দুর্বল, তাঁরা চিঠি পান না।') }}</li>
            <li>{{ __('চিঠি নিজে থেকে যায় শুধু hatpakha.com থেকে, আসল ই-মেল চালু থাকলে, আর আপনি চিঠিটি অনুমোদন দিলে। ঘণ্টায় ২০টি করে, নতুন জমা আগে।') }}</li>
            <li>{{ __('লেখক "চাই" বললে লেখাটি "পুরনো: লেখক রাজি" তালিকায় আসে; আপনি পড়ে সিদ্ধান্ত নেবেন। "চাই না" বা উত্তর না দিলে কখনও প্রকাশ হবে না।') }}</li>
        </ul>
    </x-filament::section>

    <div class="hp-r-stats">
        <div class="hp-r-stat"><div class="hp-r-num">{{ $s['pieces'] }}</div><div class="hp-muted">{{ __('পুরনো জমা লেখা') }}</div></div>
        <div class="hp-r-stat"><div class="hp-r-num">{{ $s['ask'] }}</div><div class="hp-muted">{{ __('জিজ্ঞেস করার মতো') }}</div></div>
        <div class="hp-r-stat"><div class="hp-r-num">{{ $s['skip'] }}</div><div class="hp-muted">{{ __('খুব দুর্বল') }}</div></div>
        <div class="hp-r-stat"><div class="hp-r-num">{{ $s['unread'] }}</div><div class="hp-muted">{{ __('এখনও পড়া হয়নি') }}</div></div>
        <div class="hp-r-stat"><div class="hp-r-num">{{ $s['writers'] }}</div><div class="hp-muted">{{ __('চিঠি পাবেন এমন লেখক') }}</div></div>
        <div class="hp-r-stat"><div class="hp-r-num">{{ $s['sent'] }}</div><div class="hp-muted">{{ __('চিঠি গেছে') }}</div></div>
        <div class="hp-r-stat"><div class="hp-r-num">{{ $s['due'] }}</div><div class="hp-muted">{{ __('চিঠি বাকি') }}</div></div>
        <div class="hp-r-stat"><div class="hp-r-num">{{ $s['yes'] }}</div><div class="hp-muted">{{ __('প্রকাশ চান') }}</div></div>
        <div class="hp-r-stat"><div class="hp-r-num">{{ $s['no'] }}</div><div class="hp-muted">{{ __('চান না') }}</div></div>
    </div>

    {{-- Owner (D104): read first; only pieces worth publishing are asked about --}}
    <x-filament::section icon="heroicon-o-funnel">
        <x-slot name="heading">{{ __('লেখা বাছাই') }}</x-slot>
        <x-slot name="description">{{ __('এআই সম্পাদকের মত ও কারণ শুধু আপনার জন্য; লেখক কখনও দেখেন না।') }}</x-slot>
        <x-slot name="afterHeader">
            @if ($s['unread'])<x-filament::button size="sm" color="gray" icon="heroicon-o-play" wire:click="screenNow">{{ __('এখনই আরও পড়ুক') }}</x-filament::button>@endif
        </x-slot>
        <div style="display:flex;gap:.4rem;flex-wrap:wrap;margin-bottom:.8rem">
            @foreach (['ask' => 'জিজ্ঞেস করার মতো', 'skip' => 'খুব দুর্বল', 'unread' => 'এখনও পড়া হয়নি'] as $key => $label)
                <x-filament::button size="sm" :color="$this->show === $key ? 'primary' : 'gray'" wire:click="showList('{{ $key }}')">{{ __($label) }} ({{ $s[$key] }})</x-filament::button>
            @endforeach
        </div>
        @if ($d['list']->isEmpty())
            <div class="hp-muted">{{ __('এই তালিকায় কিছু নেই।') }}</div>
        @else
            <table class="hp-r-table" style="width:100%;border-collapse:collapse;font-size:.92rem">
                @foreach ($d['list'] as $p)
                    <tr wire:key="w-{{ $p->id }}">
                        <td style="min-width:12rem"><a href="{{ $d['editUrl']($p) }}" style="text-decoration:underline;font-weight:600">{{ $p->title }}</a>
                            <div class="hp-muted">{{ $p->authors->first()?->publicName() }} · {{ $p->submitted_at?->format('Y') }}</div></td>
                        <td style="width:4rem;font-weight:700">{{ $p->relaunch_score ? $p->relaunch_score.'/5' : '' }}</td>
                        <td>{{ $p->relaunch_note }}
                            @if ($p->relaunch_worth_by_owner)
                                <div class="hp-muted">{{ __('আপনার বাছাই') }}</div>
                            @endif
                        </td>
                        <td style="width:9rem;text-align:right">
                            @if ($p->relaunch_worth !== 'ask')<x-filament::button size="xs" color="success" wire:click="setWorth({{ $p->id }}, 'ask')">{{ __('জিজ্ঞেস করুন') }}</x-filament::button>@endif
                            @if ($p->relaunch_worth !== 'skip')<x-filament::button size="xs" color="gray" wire:click="setWorth({{ $p->id }}, 'skip')">{{ __('বাদ দিন') }}</x-filament::button>@endif
                        </td>
                    </tr>
                @endforeach
            </table>
            @if ($d['list']->count() >= $this->limit)
                <x-filament::button size="sm" color="gray" wire:click="$set('limit', {{ $this->limit + 60 }})" style="margin-top:.6rem">{{ __('আরও দেখুন') }}</x-filament::button>
            @endif
        @endif
    </x-filament::section>

    <x-filament::section icon="heroicon-o-envelope">
        <x-slot name="heading">{{ __('চিঠি') }}</x-slot>
        <x-slot name="description">
            @if ($d['waiting'])
                {{ __('এখন পাঠানো বন্ধ') }}: {{ __($d['waiting']) }}
            @else
                {{ __('পাঠানো চলছে') }}
            @endif
        </x-slot>
        <div style="display:grid;gap:.8rem">
            <label><div style="font-weight:600;margin-bottom:.3rem">{{ __('বিষয়') }}</div>
                <input type="text" wire:model="subject" class="hp-r-field" maxlength="200"></label>
            <label><div style="font-weight:600;margin-bottom:.3rem">{{ __('চিঠি') }}</div>
                <textarea wire:model="body" rows="14" class="hp-r-field" style="line-height:1.7"></textarea></label>
            <div class="hp-muted">{{ __('{লেখা} জায়গায় লেখকের লেখার নাম বসবে (১০টির বেশি হলে "এবং আরও…")। শুরুতে "নমস্কার, নাম" আর শেষে "উত্তর দিন" বোতাম নিজে থেকে থাকে।') }}</div>
            @error('subject')<div style="color:#CB1B32">{{ $message }}</div>@enderror
            @error('body')<div style="color:#CB1B32">{{ $message }}</div>@enderror
            <div style="display:flex;gap:.6rem;flex-wrap:wrap;align-items:center">
                <x-filament::button color="gray" wire:click="saveLetter">{{ __('রাখুন') }}</x-filament::button>
                <x-filament::button color="success" icon="heroicon-o-check" wire:click="approve"
                    wire:confirm="{{ __('এই চিঠি অনুমোদন দিচ্ছেন? hatpakha.com-এ চালু হলে যাঁদের লেখা “জিজ্ঞেস করার মতো”, তাঁরা এটি পাবেন।') }}">{{ __('চিঠি অনুমোদন') }}</x-filament::button>
                @if ($d['approved'])<span style="font-weight:700">✓ {{ __('অনুমোদিত') }}</span>@endif
            </div>
        </div>
    </x-filament::section>

    @if ($d['preview'])
        <x-filament::section icon="heroicon-o-eye" collapsible collapsed>
            <x-slot name="heading">{{ __('চিঠি দেখতে কেমন') }}</x-slot>
            <iframe srcdoc="{{ $d['preview'] }}" style="width:100%;height:42rem;border:0;border-radius:.6rem;background:#fff" sandbox></iframe>
        </x-filament::section>
    @endif

    <x-filament::section icon="heroicon-o-hand-thumb-up">
        <x-slot name="heading">{{ __('যাঁরা প্রকাশ চান') }}</x-slot>
        @if ($d['yes']->isEmpty())
            <div class="hp-muted">{{ __('এখনও কোনো উত্তর আসেনি।') }}</div>
        @else
            <table class="hp-r-table" style="width:100%;border-collapse:collapse;font-size:.92rem">
                @foreach ($d['yes'] as $p)
                    <tr>
                        <td><a href="{{ $d['editUrl']($p) }}" style="text-decoration:underline;font-weight:600">{{ $p->title }}</a>
                            <div class="hp-muted">{{ $p->authors->first()?->publicName() }}</div></td>
                        <td>@if ($p->relaunch_elsewhere)<span style="font-weight:600">{{ __('অন্য কোথাও আগে প্রকাশিত') }}</span>@endif</td>
                        <td class="hp-muted">{{ $p->relaunch_answered_at?->diffForHumans() }}</td>
                    </tr>
                @endforeach
            </table>
        @endif
    </x-filament::section>
</x-filament-panels::page>
