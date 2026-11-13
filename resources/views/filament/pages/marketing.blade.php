{{-- Marketing desk (D51, D70). Inline styles: /desk has no utility CSS. Cards use the logo palette only. --}}
@php $d = $this->data(); $schedule = $d['schedule']; $whatsapp = $d['whatsapp']; $lessons = $d['lessons']; $review = $d['review']; @endphp
@php $isAdmin = \App\Filament\Support\Deleting::isAdmin(); @endphp
<x-filament-panels::page>
    <style>
        .hp-mk { display: grid; gap: 1rem; }
        .hp-mk-item { border: 1px solid rgba(127,127,127,.25); border-radius: .9rem; padding: 1rem; display: grid; gap: .75rem; }
        .hp-mk-cols { display: grid; gap: .75rem; grid-template-columns: repeat(auto-fit, minmax(15rem, 1fr)); }
        .hp-mk-text { white-space: pre-wrap; font-size: .92rem; line-height: 1.6; border-radius: .6rem; padding: .7rem; background: rgba(127,127,127,.08); max-height: 16rem; overflow: auto; }
        .hp-muted { font-size: .85rem; opacity: .72; }
        .hp-card-wrap { width: 270px; height: 270px; overflow: hidden; border-radius: .6rem; }
        .hp-card-wrap > .hp-card { transform: scale(.25); transform-origin: top left; }
    </style>

    {{-- How this page works (D70): plain steps, so the buttons are never a puzzle. --}}
    {{-- Facebook Page (owner, 2026-11-12): post now, at a time, or by itself (automatic mode, the owner's switch) --}}
    @if (\App\Support\FacebookPage::configured())
        @php $fbAuto = \App\Support\FacebookPage::auto(); @endphp
        <x-filament::section icon="heroicon-o-globe-alt">
            <x-slot name="heading">{{ __('ফেসবুক পেজে পোস্ট') }}</x-slot>
            <div style="display:flex;gap:1rem;flex-wrap:wrap;align-items:center;justify-content:space-between">
                <div style="max-width:46rem">
                    <b>{{ $fbAuto ? __('নিজে থেকে পোস্ট: চালু') : __('নিজে থেকে পোস্ট: বন্ধ') }}</b>
                    <p class="hp-muted" style="margin-top:.2rem">{{ $fbAuto
                        ? __('মার্কেটিং টিম রোজের পোস্ট নিজেই সাজায়, ছবি ও রিল নিজেই তৈরি করে, আর ঠিক সময়ে ফেসবুক ও ইনস্টাগ্রামে দেয়: সকালে ধাঁধা, দুপুরে রিল, সন্ধ্যায় দিনের লেখা। কোনোটি না চাইলে নিচে “পোস্ট করব না” টিপুন।')
                        : __('এখন কিছুই নিজে থেকে যায় না; প্রতিটি পোস্ট আপনি টিপলে যায়। চালু করলে মার্কেটিং টিম রোজের পোস্ট নিজেই দেবে।') }}</p>
                    @unless (\App\Support\FacebookPage::ready())<p class="hp-muted" style="margin-top:.2rem">⚠ {{ __('এটি আসল সাইট নয়: এখান থেকে ফেসবুকে কিছু যায় না।') }}</p>@endunless
                    <p style="margin-top:.5rem"><x-filament::button size="xs" color="gray" icon="heroicon-o-shield-check" wire:click="checkFacebook">{{ __('ফেসবুক কী কী করতে দিচ্ছে, দেখুন') }}</x-filament::button></p>
                    @if ($this->fbCheck)
                        <div class="hp-mk-text" style="margin-top:.4rem">@if (! $this->fbCheck['ok'])⚠ {{ __('ফেসবুক উত্তর দেয়নি') }}: {{ $this->fbCheck['error'] }}
{{ __('নিজে দেখতে: developers.facebook.com → Tools → Access Token Debugger-এ পেজের চাবিটি বসান (চাবিটি আর কোথাও দেবেন না)।') }}@else
@foreach (\App\Support\FacebookPage::NEEDS as $scope => $what){{ in_array($scope, $this->fbCheck['scopes'], true) ? '✓' : '✗' }} {{ __($what) }} ({{ $scope }})
@endforeach
{{ $this->fbCheck['expires'] ? __('চাবির মেয়াদ শেষ').': '.$this->fbCheck['expires'] : __('চাবির মেয়াদ শেষ হয় না।') }}@endif</div>
                    @endif
                </div>
                @if ($isAdmin)
                    <x-filament::button :color="$fbAuto ? 'gray' : 'primary'" icon="heroicon-o-bolt" wire:click="toggleFacebookAuto"
                        wire:confirm="{{ $fbAuto ? __('নিজে থেকে পোস্ট বন্ধ করবেন?') : __('চালু করলে রোজের পোস্ট আপনার না দেখে ফেসবুক ও ইনস্টাগ্রামে যাবে। চালু করবেন?') }}">{{ $fbAuto ? __('বন্ধ করুন') : __('নিজে থেকে পোস্ট চালু করুন') }}</x-filament::button>
                @endif
            </div>
        </x-filament::section>
    @endif

    {{-- D182: the plan for today and tomorrow, today's WhatsApp message, and what the numbers taught --}}
    <x-filament::section icon="heroicon-o-calendar-days" collapsible>
        <x-slot name="heading">{{ __('আজ ও কালের পোস্ট') }}</x-slot>
        @if ($schedule->isEmpty())
            <p class="hp-muted">{{ __('এখনও কিছু সাজানো নেই। রোজ সন্ধ্যায় পরের দিনের পোস্ট সাজানো হয়।') }}</p>
        @else
            <p class="hp-muted" style="margin-bottom:.5rem">{{ __('কোনো পোস্ট না চাইলে “থামান” টিপুন: সেটি আর নিজে থেকে যাবে না। “খুলে দেখুন” টিপলে ফেসবুক বা ইনস্টাগ্রামে পোস্টটি খুলবে।') }}</p>
            <div style="overflow-x:auto"><table style="width:100%;font-size:.9rem;border-collapse:collapse">
                <thead><tr style="text-align:left"><th style="padding:.3rem .5rem">{{ __('সময়') }}</th><th style="padding:.3rem .5rem">{{ __('পোস্ট') }}</th><th style="padding:.3rem .5rem">{{ __('ধরন') }}</th><th style="padding:.3rem .5rem">{{ __('অবস্থা') }}</th><th style="padding:.3rem .5rem">{{ __('সাড়া') }}</th><th></th></tr></thead>
                <tbody>
                @foreach ($schedule as $row)
                    @php
                        $p = $row['s'];
                        $formats = ['link' => __('লেখা ও লিংক'), 'card' => __('ছবির কার্ড'), 'carousel' => __('কয়েকটি ছবি'), 'reel' => __('রিল')];
                        $sent = $p->fb_post_id || $p->ig_media_id || $p->ig_container;
                    @endphp
                    <tr style="border-top:1px solid rgba(127,127,127,.2);vertical-align:top">
                        <td style="padding:.4rem .5rem;white-space:nowrap">{{ \App\Support\Bn::num($p->slot_at->timezone('Asia/Kolkata')->format('d/m H:i')) }}</td>
                        <td style="padding:.4rem .5rem">{{ $p->title }}
                            @if ($p->media['video'] ?? null) · <a href="{{ asset($p->media['video']) }}" target="_blank" rel="noopener" style="text-decoration:underline">{{ __('রিল দেখুন') }}</a>
                            @elseif ($p->media['images'][0] ?? null) · <a href="{{ asset($p->media['images'][0]) }}" target="_blank" rel="noopener" style="text-decoration:underline">{{ __('ছবি দেখুন') }}</a>@endif
                        </td>
                        <td style="padding:.4rem .5rem;white-space:nowrap">{{ $formats[$p->format] ?? $p->format }}</td>
                        <td style="padding:.4rem .5rem">
                            @if ($p->status === 'dismissed' && ! $sent)<b>{{ __('থামানো আছে') }}</b>@else
                                @foreach ($row['where'] as $w)
                                    <div @if ($w['bad']) style="color:#CB1B32;font-weight:700" @endif>{{ $w['net'] }}: {{ $w['text'] }}@if ($w['link']) · <a href="{{ $w['link'] }}" target="_blank" rel="noopener" style="text-decoration:underline">{{ __('খুলে দেখুন') }}</a>@endif</div>
                                @endforeach
                            @endif
                        </td>
                        <td style="padding:.4rem .5rem;white-space:nowrap">@if ($p->metrics){{ __('দেখেছেন') }} {{ \App\Support\Bn::num((string) $row['sum']['reach']) }} · {{ __('কমেন্ট, শেয়ার, সেভ') }} {{ \App\Support\Bn::num((string) $row['sum']['talk']) }}@if (isset($p->metrics['site']['visits'])) · {{ __('সাইটে এসেছেন') }} {{ \App\Support\Bn::num((string) $p->metrics['site']['visits']) }}@endif @endif</td>
                        <td style="padding:.4rem .5rem;white-space:nowrap">
                            @if ($p->status === 'ready' && ! $sent)
                                <x-filament::button size="xs" color="gray" wire:click="dismiss({{ $p->id }})">{{ __('থামান') }}</x-filament::button>
                            @elseif ($p->status === 'dismissed' && ! $sent)
                                <x-filament::button size="xs" color="gray" wire:click="restore({{ $p->id }})">{{ __('আবার চালু') }}</x-filament::button>
                            @endif
                            @if ($isAdmin && ! $sent)
                                <x-filament::button size="xs" color="danger" wire:click="deleteSocial({{ $p->id }})" wire:confirm="{{ __('এই পোস্টটি একেবারে মুছে ফেলবেন?') }}">{{ __('মুছুন') }}</x-filament::button>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        @endif
        <div style="margin-top:1rem" x-data="{ copied: false }">
            <b>{{ __('আজকের হোয়াটসঅ্যাপ চ্যানেলের বার্তা') }}</b>
            <p class="hp-muted">{{ __('হোয়াটসঅ্যাপ চ্যানেলে বাইরে থেকে নিজে পোস্ট করার ব্যবস্থা নেই। তাই দিনে একবার এটি কপি করে চ্যানেলে পেস্ট করে দিন।') }}</p>
            <div class="hp-mk-text" x-ref="wa">{{ $whatsapp }}</div>
            <x-filament::button size="sm" color="gray" icon="heroicon-o-clipboard" style="margin-top:.4rem" x-on:click="navigator.clipboard.writeText($refs.wa.innerText); copied = true; setTimeout(() => copied = false, 2000)"><span x-show="!copied">{{ __('কপি') }}</span><span x-show="copied" x-cloak>{{ __('কপি হয়েছে') }}</span></x-filament::button>
        </div>
    </x-filament::section>

    <x-filament::section icon="heroicon-o-chart-bar" collapsible>
        <x-slot name="heading">{{ __('সপ্তাহের হিসাব') }}</x-slot>
        @if ($isAdmin)
            <x-slot name="afterHeader"><x-filament::button size="sm" color="gray" icon="heroicon-o-arrow-path" wire:click="reviewNow" wire:loading.attr="disabled">{{ __('এখনই হিসাব করুন') }}</x-filament::button></x-slot>
        @endif
        {{-- D183: the analyst's weekly review: every number on one card, what changed in the plan, what to do next --}}
        @php
            $card = $review['card'] ?? [];
            $bn = fn ($n) => \App\Support\Bn::num((string) $n);
            $pair = fn ($x) => $x ? $bn($x['now']).' ('.__('আগের সপ্তাহে').' '.$bn($x['before']).')' : '—';
            $tune = \App\Support\MarketingAnalyst::tuning();
        @endphp
        <div>
            <p class="hp-muted">{{ __('প্রতি রবিবার রাতে বিশ্লেষক গুগল অ্যানালিটিক্স, ফেসবুক, ইনস্টাগ্রাম, নিউজলেটার, ফোনের নোটিফিকেশন আর সাইটের নিজের সংখ্যা মিলিয়ে দেখে, আর তা থেকে পরের সপ্তাহের পরিকল্পনা বদলায়।') }}
                {{ __('এখন পোস্টের সময়') }}: {{ $bn($tune['morning']) }}, {{ $bn('13:30') }}, {{ $bn($tune['evening']) }}.</p>
            @if (! $card)
                <p class="hp-muted">{{ __('প্রথম হিসাব আসবে এই রবিবার রাতে।') }} {{ __('এখনই দেখতে চাইলে “এখনই হিসাব করুন” টিপুন।') }}</p>
            @else
                @if ($review['report'] ?? [])<ul style="margin:.3rem 0 0;padding-left:1.2rem;line-height:1.8">@foreach ($review['report'] as $l)<li>{{ $l }}</li>@endforeach</ul>@endif
                @if ($review['changes'] ?? [])
                    <p style="margin-top:.5rem"><b>{{ __('পরিকল্পনায় যা বদলাল') }}</b></p>
                    <ul style="margin:.2rem 0 0;padding-left:1.2rem;line-height:1.8">@foreach ($review['changes'] as $l)<li>{{ $l }}</li>@endforeach</ul>
                @endif
                @if ($review['asks'] ?? [])
                    <p style="margin-top:.5rem"><b>{{ __('আপনার করার মতো') }}</b></p>
                    <ul style="margin:.2rem 0 0;padding-left:1.2rem;line-height:1.8">@foreach ($review['asks'] as $l)<li>{{ $l }}</li>@endforeach</ul>
                @endif
                <div class="hp-mk-cols" style="margin-top:.6rem;font-size:.9rem">
                    <div class="hp-mk-text"><b>{{ __('সাইট') }}</b>
{{ __('নতুন সদস্য') }}: {{ $pair($card['site']['signups'] ?? null) }}
{{ __('খেলেছেন') }}: {{ $pair($card['site']['players'] ?? null) }}
{{ __('পাঠকের কলমে লেখা') }}: {{ $pair($card['site']['kolom_entries'] ?? null) }}
{{ __('নিউজলেটারে নতুন') }}: {{ $pair($card['site']['newsletter_signups'] ?? null) }}
{{ __('কমেন্ট') }}: {{ $pair($card['site']['comments'] ?? null) }}</div>
                    @if ($card['ga']['now'] ?? null)
                        <div class="hp-mk-text"><b>{{ __('গুগল অ্যানালিটিক্স (৭ দিন)') }}</b>
{{ __('পাঠক') }}: {{ $bn((int) $card['ga']['now']['activeUsers']) }} ({{ __('আগের সপ্তাহে') }} {{ $bn((int) ($card['ga']['before']['activeUsers'] ?? 0)) }})
@if ($card['ga']['returning_share'] !== null){{ __('ফিরে আসা পাঠক') }}: {{ $bn($card['ga']['returning_share']) }}%
@endif
{{ __('কোথা থেকে এলেন') }}: @foreach (array_slice($card['ga']['sources'], 0, 6) as $r){{ $r['sessionSource'] }} {{ $bn((int) $r['sessions']) }}@if (! $loop->last), @endif @endforeach

@if ($card['ga']['campaigns']){{ __('কোন পোস্ট থেকে') }}: @foreach (array_slice($card['ga']['campaigns'], 0, 6) as $r){{ $r['sessionCampaignName'] }} {{ $bn((int) $r['sessions']) }}@if (! $loop->last), @endif @endforeach
@endif</div>
                    @endif
                    <div class="hp-mk-text"><b>{{ __('ফেসবুক ও ইনস্টাগ্রাম') }}</b>
@foreach ($card['social']['followers'] ?? [] as $net => $n){{ $net }}: {{ $bn($n) }} {{ __('অনুসরণকারী') }}
@endforeach
@foreach (array_slice($card['social']['by_kind'] ?? [], 0, 6) as $t){{ $t['what'] }}: {{ __('গড়ে দেখেছেন') }} {{ $bn($t['reach']) }}, {{ __('সাড়া') }} {{ $bn($t['talk']) }}
@endforeach</div>
                    <div class="hp-mk-text"><b>{{ __('নিউজলেটার ও নোটিফিকেশন') }}</b>
@foreach ($card['newsletter'] ?? [] as $n){{ \Illuminate\Support\Str::limit($n['subject'], 40) }}: {{ $bn($n['sent']) }} {{ __('জনকে পাঠানো') }}, {{ $bn($n['click_rate']) }}% {{ __('ক্লিক করেছেন') }}
@endforeach
@foreach ($card['push'] ?? [] as $n){{ $n['kind'] }}: {{ $bn($n['sent']) }} {{ __('পাঠানো') }}, {{ $bn($n['opened']) }} {{ __('খুলেছেন') }}
@endforeach</div>
                </div>
            @endif
            @if ($lessons['lessons'] ?? [])
                <p style="margin-top:.5rem"><b>{{ __('পোস্ট লেখার জন্য শিক্ষা') }}</b></p>
                <ul style="margin:.2rem 0 0;padding-left:1.2rem;line-height:1.8">@foreach ($lessons['lessons'] as $l)<li>{{ $l }}</li>@endforeach</ul>
            @endif
        </div>
    </x-filament::section>

    <x-filament::section icon="heroicon-o-question-mark-circle" collapsible>
        <x-slot name="heading">{{ __('এই পাতায় কী করবেন') }}</x-slot>
        <ol style="margin:0;padding-left:1.2rem;line-height:1.9">
            <li>{{ __('মার্কেটিং টিম রোজের পোস্ট নিজেই সাজায় আর “নিজে থেকে পোস্ট” চালু থাকলে নিজেই দেয়। নিচের বোতামগুলো আপনি নিজে হাতে কিছু দিতে চাইলে।') }}</li>
            <li>{{ __('"ফেসবুকে দিন", "হোয়াটসঅ্যাপে দিন" বা "ইনস্টাগ্রামে দিন" টিপুন: সেই সাইট বা অ্যাপ লেখা, লিংক আর ছবি সমেত খুলবে, সেখানে "পোস্ট" টিপলেই হলো। ফেসবুক বাইরের লেখা নিজে বসায় না, তাই লেখাটি কপি হয়ে থাকে: পেস্ট করে দিন। চাইলে "কপি" টিপে নিজে পেস্ট করতেও পারেন।') }}</li>
            <li>{{ __('পোস্ট হয়ে গেলে "পোস্ট করেছি" টিপুন: কার্ডটি "পোস্ট করা / বাদ"-এ চলে যাবে, যাতে কোনটা বাকি বোঝা যায়।') }}</li>
            <li>{{ __('কোনো পোস্ট দিতে না চাইলে "পোস্ট করব না" টিপুন। ভুল করে টিপলে সঙ্গে সঙ্গে "ফিরিয়ে আনুন" টিপুন, বা পরে "পোস্ট করা / বাদ" থেকে ফেরান।') }}</li>
            <li>{{ __('সপ্তাহের পরিকল্পনা শুধু পথনির্দেশ: কোন দিন কী পোস্ট করবেন। এতে কিছু টিপতে হয় না; সবসময় উপরে থাকে।') }}</li>
        </ol>
    </x-filament::section>

    {{-- The weekly plan, pinned (D70). --}}
    <x-filament::section icon="heroicon-o-calendar-days" collapsible persist-collapsed collapse-id="hp-marketing-1">
        <x-slot name="heading">{{ __('সপ্তাহের পরিকল্পনা') }}</x-slot>
        <x-slot name="afterHeader">
            <x-filament::button size="sm" color="gray" icon="heroicon-o-forward" wire:click="nextWeek" wire:loading.attr="disabled" wire:target="nextWeek">
                <span wire:loading.remove wire:target="nextWeek">{{ __('পরের সপ্তাহের পরিকল্পনা তৈরি করুন') }}</span>
                <span wire:loading wire:target="nextWeek">{{ __('তৈরি হচ্ছে…') }}</span>
            </x-filament::button>
        </x-slot>
        @forelse ($d['plans'] as $plan)
            <div style="{{ $loop->first ? '' : 'margin-top:1rem;padding-top:1rem;border-top:1px solid rgba(127,127,127,.25)' }}">
                <div style="display:flex;justify-content:space-between;gap:.5rem;flex-wrap:wrap">
                    <strong>{{ $plan->title }}</strong>
                    <span style="display:flex;gap:.5rem;align-items:center">
                        <span class="hp-muted">{{ $plan->created_at->locale(app()->getLocale())->diffForHumans() }}</span>
                        @if ($isAdmin)
                            <x-filament::button size="xs" color="danger" outlined icon="heroicon-o-trash" wire:click="deleteSocial({{ $plan->id }})" wire:confirm="{{ __('এই পরিকল্পনাটি মুছে ফেলবেন? এটি আর ফেরানো যাবে না।') }}">{{ __('মুছুন') }}</x-filament::button>
                        @endif
                    </span>
                </div>
                <ul style="margin:.4rem 0 0;padding-left:1.2rem;line-height:1.8">@foreach ((array) ($plan->texts['plan'] ?? []) as $line)<li>{{ $line }}</li>@endforeach</ul>
                @if (filled($plan->texts['facebook'] ?? '') || filled($plan->texts['whatsapp'] ?? ''))
                    <details style="margin-top:.5rem">
                        <summary style="cursor:pointer;font-weight:600">{{ __('সোমবারের পোস্টের লেখা') }}</summary>
                        <div class="hp-mk-cols" style="margin-top:.5rem">
                            @foreach (['facebook' => 'ফেসবুক', 'whatsapp' => 'হোয়াটসঅ্যাপ', 'instagram' => 'ইনস্টাগ্রাম'] as $ch => $label)
                                @if (filled($plan->texts[$ch] ?? ''))
                                    <div x-data="{ copied: false, msg: '' }">
                                        <div style="display:flex;justify-content:space-between;align-items:center;gap:.4rem;flex-wrap:wrap;margin-bottom:.3rem">
                                            <span style="font-weight:600">{{ __($label) }}</span>
                                            @include('filament.partials.share-buttons', ['ch' => $ch, 'link' => \App\Support\Url::to('/')])
                                        </div>
                                        <div class="hp-mk-text" x-ref="t">{{ $plan->texts[$ch] }}</div>
                                        <div class="hp-muted" x-show="msg" x-text="msg" role="status" style="margin-top:.3rem"></div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </details>
                @endif
            </div>
        @empty
            <div class="hp-muted">{{ __('এখনও পরিকল্পনা নেই। প্রতি সোমবার সকালে তৈরি হয়; এখনই চাইলে উপরের বোতাম টিপুন।') }}</div>
        @endforelse
    </x-filament::section>

    {{-- Weekly newsletter (D48, D57): drafted every Saturday (or now, from the button above); sent from চিঠি. --}}
    <x-filament::section icon="heroicon-o-envelope" collapsible collapsed>
        <x-slot name="heading">{{ __('সাপ্তাহিক চিঠি (নিউজলেটার)') }}</x-slot>
        <p class="hp-muted" style="margin:0 0 .5rem">{{ __('যাঁরা নিজে সম্মতি দিয়েছেন, শুধু তাঁরাই পান') }}: <strong>{{ \App\Support\Bn::num((string) $d['readers']) }}</strong> {{ __('জন') }}।
            {{ __('প্রতি শনিবার পাঠকদের আর প্রতি মাসের ২১ তারিখে লেখকদের চিঠির খসড়া তৈরি হয়। সব চিঠি:') }} <a href="{{ \App\Filament\Resources\Newsletters\NewsletterResource::getUrl() }}" style="text-decoration:underline">{{ __('চিঠি') }}</a></p>
        @if ($nl = $d['newsletter'])
            <div class="hp-mk-text" style="max-height:none">
                <strong>{{ $nl->subject }}</strong>

{{ $nl->intro }}
@foreach ((array) $nl->items as $it)
• {{ $it['title'] }} — {{ $it['author'] }}: {{ $it['line'] }}
@endforeach</div>
            <x-filament::button tag="a" :href="\App\Filament\Resources\Newsletters\NewsletterResource::getUrl('edit', ['record' => $nl])" size="sm" icon="heroicon-o-paper-airplane" style="margin-top:.6rem">{{ __('খুলুন, দেখে পাঠান') }}</x-filament::button>
        @else
            <div class="hp-muted">{{ __('এখন কোনো খসড়া নেই।') }}</div>
        @endif
    </x-filament::section>

    <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
        <x-filament::button size="sm" :color="$filter === 'ready' ? 'primary' : 'gray'" wire:click="$set('filter', 'ready')">{{ __('পোস্টের জন্য তৈরি') }}</x-filament::button>
        <x-filament::button size="sm" :color="$filter === 'done' ? 'primary' : 'gray'" wire:click="$set('filter', 'done')">{{ __('পোস্ট করা / বাদ') }}</x-filament::button>
        @if ($isAdmin && $filter === 'done' && $d['items']->isNotEmpty())
            <x-filament::button size="sm" color="danger" outlined icon="heroicon-o-trash" wire:click="clearDone" wire:confirm="{{ __('পোস্ট করা ও বাদ দেওয়া সব পোস্ট মুছে ফেলবেন? এগুলো আর ফেরানো যাবে না।') }}">{{ __('এই তালিকা খালি করুন') }}</x-filament::button>
        @endif
        <span class="hp-muted">{{ $filter === 'done' ? __('ভুল করে সরানো কিছু থাকলে "ফিরিয়ে আনুন" টিপুন।') : __('কপি করে পোস্ট করুন, তারপর "পোস্ট করেছি" টিপুন।') }}</span>
    </div>

    <div class="hp-mk">
        @forelse ($d['items'] as ['s' => $s, 'card' => $card, 'link' => $link])
            <div class="hp-mk-item" wire:key="mk-{{ $s->id }}">
                <div style="display:flex;justify-content:space-between;gap:.5rem;flex-wrap:wrap;align-items:center">
                    <strong>{{ $s->title }}</strong>
                    <span class="hp-muted">{{ __(['piece' => 'লেখা', 'issue' => 'সংখ্যা', 'weekly' => 'সাপ্তাহিক পরিকল্পনা', 'spotlight' => 'লেখক পরিচিতি', 'festival' => 'উৎসব', 'contest' => 'প্রতিযোগিতা', 'archive' => 'আর্কাইভ থেকে', 'custom' => 'নিজের বিষয়', 'poster' => 'পোস্টার', 'kolom' => 'পাঠকের কলম'][$s->kind] ?? $s->kind) }}
                        · {{ $s->created_at->locale(app()->getLocale())->diffForHumans() }}</span>
                </div>
                <div class="hp-mk-cols">
                    @foreach (['facebook' => 'ফেসবুক', 'whatsapp' => 'হোয়াটসঅ্যাপ', 'instagram' => 'ইনস্টাগ্রাম'] as $ch => $label)
                        @if (filled($s->texts[$ch] ?? ''))
                            <div x-data="{ copied: false, msg: '' }">
                                <div style="display:flex;justify-content:space-between;align-items:center;gap:.4rem;flex-wrap:wrap;margin-bottom:.3rem">
                                    <span style="font-weight:600">{{ __($label) }}</span>
                                    @include('filament.partials.share-buttons', ['ch' => $ch, 'link' => $link])
                                </div>
                                <div class="hp-mk-text" x-ref="t">{{ $s->texts[$ch] }}</div>
                                <div class="hp-muted" x-show="msg" x-text="msg" role="status" style="margin-top:.3rem"></div>
                            </div>
                        @endif
                    @endforeach
                </div>
                @if ($card)
                    {{-- D170 (owner): every card can go to Facebook or Instagram on its own; "আরও উদ্ধৃতি কার্ড" makes more --}}
                    @php
                        $cards = ['share' => $card];
                        foreach ($s->quotes() as $qi => $q) {
                            $cards['q'.$qi] = ['quote' => $q] + $card;
                        }
                        $fbOn = \App\Support\FacebookPage::configured() && filled($s->texts['facebook'] ?? '');
                        $igOn = \App\Support\InstagramPage::configured() && filled($s->texts['instagram'] ?? '');
                    @endphp
                    <div style="display:flex;gap:1rem;flex-wrap:wrap;align-items:flex-start">
                        @foreach ($cards as $key => $c)
                            @php
                                $layout = $key === 'share' ? 'share' : 'quote';
                                $done = (array) ($s->texts['card_posts'][$key] ?? []);
                            @endphp
                            <div data-card-key="{{ $key }}" x-data="{ busy: false }" style="width:270px">
                                <div class="hp-card-wrap" @if ($key === 'share') x-data x-init="$nextTick(() => { const c = $el.querySelector('.hp-card'); if (c) { c.dataset.name = 'hatpakha-{{ $s->id }}.png'; window.hpShare?.watch($el); } })" @endif>@include('filament.partials.social-card', ['card' => $c, 'layout' => $layout])</div>
                                <div style="display:flex;gap:.35rem;flex-wrap:wrap;margin-top:.4rem">
                                    <x-filament::button size="xs" color="gray" icon="heroicon-o-arrow-down-tray"
                                        x-on:click="window.hpCard.download($el.closest('[data-card-key]').querySelector('.hp-card'), 'hatpakha-{{ $s->id }}-{{ $key }}.png')">
                                        {{ $layout === 'quote' ? __('উদ্ধৃতির ছবি নামান') : __('শেয়ারের ছবি নামান') }}
                                    </x-filament::button>
                                    @if ($fbOn)
                                        <x-filament::button size="xs" color="gray" icon="heroicon-o-paper-airplane" x-bind:disabled="busy"
                                            x-on:click="if (! confirm({{ \Illuminate\Support\Js::from(__('এই ছবিটি ফেসবুকের লেখার সঙ্গে এখনই হাতপাখার ফেসবুক পেজে যাবে। পাঠাবেন?')) }})) return; busy = true; window.hpIg.postCard($el, {{ $s->id }}, '{{ $key }}', 'facebook').finally(() => busy = false)">{{ __('এই ছবি ফেসবুকে') }}</x-filament::button>
                                    @endif
                                    @if ($igOn)
                                        <x-filament::button size="xs" color="gray" icon="heroicon-o-camera" x-bind:disabled="busy"
                                            x-on:click="if (! confirm({{ \Illuminate\Support\Js::from(__('এই ছবিটি ইনস্টাগ্রামের লেখার সঙ্গে এখনই হাতপাখার ইনস্টাগ্রামে যাবে। পাঠাবেন?')) }})) return; busy = true; window.hpIg.postCard($el, {{ $s->id }}, '{{ $key }}', 'instagram').finally(() => busy = false)">{{ __('এই ছবি ইনস্টাগ্রামে') }}</x-filament::button>
                                    @endif
                                    @if (str_starts_with($key, 'q') && $key !== 'q0')
                                        <x-filament::button size="xs" color="gray" icon="heroicon-o-x-mark" wire:click="removeQuote({{ $s->id }}, {{ (int) substr($key, 1) - 1 }})">{{ __('সরান') }}</x-filament::button>
                                    @endif
                                </div>
                                @if ($done)
                                    <p class="hp-muted" style="margin:.3rem 0 0">✓ {{ implode(' · ', array_map(fn ($n) => __($n === 'facebook' ? 'ফেসবুকে গেছে' : 'ইনস্টাগ্রামে গেছে'), array_keys($done))) }}</p>
                                @endif
                            </div>
                        @endforeach
                        @if ($s->subject instanceof \App\Models\Post)
                            <div style="align-self:center">
                                <x-filament::button size="sm" color="gray" icon="heroicon-o-plus" wire:click="moreQuotes({{ $s->id }})" wire:loading.attr="disabled" wire:target="moreQuotes({{ $s->id }})">{{ __('আরও উদ্ধৃতি কার্ড') }}</x-filament::button>
                                <p class="hp-muted" style="max-width:15rem;margin:.3rem 0 0">{{ __('লেখা থেকে হুবহু আরও লাইন বেছে নতুন কার্ড বানায়।') }}</p>
                            </div>
                        @endif
                    </div>
                @endif
                {{-- D170: Facebook and Instagram are separate; posting to one keeps the other available --}}
                @if ($s->status === 'ready' && ! $s->fb_post_id)
                    @if (\App\Support\FacebookPage::configured() && filled($s->texts['facebook'] ?? ''))
                        <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center" x-data="{ at: '' }">
                            <x-filament::button size="sm" icon="heroicon-o-paper-airplane" wire:click="facebookNow({{ $s->id }})"
                                wire:confirm="{{ __('এখনই হাতপাখার ফেসবুক পেজে যাবে। পাঠাবেন?') }}">{{ __('ফেসবুকে দিন') }}</x-filament::button>
                            @if ($s->fb_scheduled_at)
                                <span class="hp-muted">⏰ {{ __('ফেসবুকে যাবে') }}: {{ \App\Support\Bn::date($s->fb_scheduled_at->timezone('Asia/Kolkata')) }} {{ \App\Support\Bn::num($s->fb_scheduled_at->timezone('Asia/Kolkata')->format('H:i')) }}</span>
                                <x-filament::button size="sm" color="gray" wire:click="facebookUnschedule({{ $s->id }})">{{ __('সময় বাতিল') }}</x-filament::button>
                            @else
                                <input type="datetime-local" x-model="at" aria-label="{{ __('কখন ফেসবুকে যাবে') }}" style="border:1px solid rgba(127,127,127,.4);border-radius:.5rem;padding:.25rem .5rem;background:transparent">
                                <x-filament::button size="sm" color="gray" icon="heroicon-o-clock" x-on:click="at && $wire.facebookSchedule({{ $s->id }}, at)">{{ __('সময় ঠিক করুন') }}</x-filament::button>
                            @endif
                        </div>
                        @if ($s->fb_error)<p style="color:#CB1B32;font-weight:700;margin:.2rem 0 0">⚠ {{ __('ফেসবুকে যায়নি') }}: {{ $s->fb_error }}</p>@endif
                    @endif
                @elseif ($s->fb_post_id)
                    <p class="hp-muted" style="margin:0">✓ {{ __('ফেসবুক পেজে পোস্ট হয়েছে') }} · <a style="text-decoration:underline" target="_blank" rel="noopener" href="https://www.facebook.com/{{ $s->fb_post_id }}">{{ __('ফেসবুকে দেখুন') }}</a></p>
                @endif
                    {{-- Instagram (owner, 2026-11-12): the card the browser draws goes up as the picture --}}
                    @if (\App\Support\InstagramPage::configured() && filled($s->texts['instagram'] ?? ''))
                        <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center">
                            @if ($s->ig_media_id)
                                <span class="hp-muted">✓ {{ __('ইনস্টাগ্রামে পোস্ট হয়েছে') }}</span>
                                @if ($s->ig_permalink)<a class="hp-muted" style="text-decoration:underline" target="_blank" rel="noopener" href="{{ $s->ig_permalink }}">{{ __('ইনস্টাগ্রামে দেখুন') }}</a>@endif
                            @else
                                <x-filament::button size="sm" color="gray" icon="heroicon-o-camera" data-ig-post="{{ $s->id }}" data-needs-card="{{ $card && blank($s->texts['card_image'] ?? null) && \App\Support\FacebookPage::auto() ? '1' : '0' }}"
                                    x-data="{ busy: false }" x-bind:disabled="busy"
                                    x-on:click="if (! confirm({{ \Illuminate\Support\Js::from(__('এখনই হাতপাখার ইনস্টাগ্রামে যাবে (কার্ডের ছবি আর ইনস্টাগ্রামের লেখা)। পাঠাবেন?')) }})) return; busy = true; window.hpIg.post($el, {{ $s->id }}).finally(() => busy = false)">{{ __('ইনস্টাগ্রামে দিন') }}</x-filament::button>
                                @if (! \App\Support\InstagramPage::picture($s) && ! $card)<span class="hp-muted">{{ __('এই পোস্টে ছবি নেই, তাই ইনস্টাগ্রামে যাবে না') }}</span>@endif
                            @endif
                        </div>
                        @if ($s->ig_error && ! $s->ig_media_id)<p style="color:#CB1B32;font-weight:700;margin:.2rem 0 0">⚠ {{ __('ইনস্টাগ্রামে যায়নি') }}: {{ $s->ig_error }}</p>@endif
                    @endif
                @if ($s->status === 'ready')
                    <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center">
                        <x-filament::button size="sm" color="success" icon="heroicon-o-check" wire:click="posted({{ $s->id }})">{{ __('পোস্ট করেছি') }}</x-filament::button>
                        <x-filament::button size="sm" color="gray" icon="heroicon-o-x-mark" wire:click="dismiss({{ $s->id }})">{{ __('পোস্ট করব না') }}</x-filament::button>
                        <span class="hp-muted">{{ __('দুটোই পরে ফেরানো যায়।') }}</span>
                        @if ($isAdmin)
                            <x-filament::button size="sm" color="danger" outlined icon="heroicon-o-trash" wire:click="deleteSocial({{ $s->id }})" wire:confirm="{{ __('এই পোস্টটি মুছে ফেলবেন? এটি আর ফেরানো যাবে না।') }}">{{ __('মুছুন') }}</x-filament::button>
                        @endif
                    </div>
                @else
                    <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
                        <span class="hp-muted">{{ $s->status === 'posted' ? '✓ '.($s->fb_post_id ? __('ফেসবুক পেজে পোস্ট হয়েছে') : __('পোস্ট করা হয়েছে')) : __('বাদ দেওয়া') }}</span>
                        @if ($s->fb_post_id)<a class="hp-muted" style="text-decoration:underline" target="_blank" rel="noopener" href="https://www.facebook.com/{{ $s->fb_post_id }}">{{ __('ফেসবুকে দেখুন') }}</a>@endif
                        <x-filament::button size="sm" color="gray" icon="heroicon-o-arrow-uturn-left" wire:click="restore({{ $s->id }})">{{ __('ফিরিয়ে আনুন') }}</x-filament::button>
                        @if ($isAdmin)
                            <x-filament::button size="sm" color="danger" outlined icon="heroicon-o-trash" wire:click="deleteSocial({{ $s->id }})" wire:confirm="{{ __('এই পোস্টটি মুছে ফেলবেন? এটি আর ফেরানো যাবে না।') }}">{{ __('মুছুন') }}</x-filament::button>
                        @endif
                    </div>
                @endif
            </div>
        @empty
            <x-filament::section><div class="hp-muted">{{ $filter === 'done' ? __('এখনও কিছু পোস্ট করা বা বাদ দেওয়া হয়নি।') : __('এখন কিছু নেই। নতুন লেখা প্রকাশ হলে মার্কেটিং বিশেষজ্ঞ এখানে পোস্ট তৈরি রাখবে; উপরের "নতুন পোস্ট তৈরি করুন" দিয়ে এখনই চাইতেও পারেন।') }}</div></x-filament::section>
        @endforelse
    </div>
    <script src="{{ asset('js/hp-card.js') }}?v=3"></script>
    @if (\App\Support\InstagramPage::configured())
        <script>
            // Instagram (owner, 2026-11-12): the browser draws the share card as a JPEG and uploads it with the post.
            // Ready posts without a saved card get one quietly when this page is open, so automatic posts have it too.
            window.hpIg = window.hpIg || {
                cardOf(el) { return el.closest('.hp-mk-item')?.querySelector('.hp-card-wrap .hp-card') || null; },
                // D170: one particular card to Facebook or Instagram
                async postCard(el, id, key, net) {
                    const card = el.closest('[data-card-key]')?.querySelector('.hp-card');
                    if (!card) return;
                    const file = await window.hpCard.jpeg(card, 'hatpakha-' + id + '-' + key + '.jpg');
                    await new Promise((ok, fail) => @this.upload('cardJpeg', file, ok, fail));
                    return @this.cardPost(id, key, net);
                },
                async upload(card, id) {
                    const file = await window.hpCard.jpeg(card, 'hatpakha-' + id + '.jpg');
                    await new Promise((ok, fail) => @this.upload('cardJpeg', file, ok, fail));
                },
                async post(el, id) {
                    const card = this.cardOf(el);
                    if (card) { await this.upload(card, id); return @this.instagramNow(id, true); }
                    return @this.instagramNow(id, false);
                },
            };
            document.addEventListener('DOMContentLoaded', () => setTimeout(async () => {
                const todo = [...document.querySelectorAll('[data-ig-post][data-needs-card="1"]')].slice(0, 5);
                for (const b of todo) {
                    const card = window.hpIg.cardOf(b);
                    if (!card) continue;
                    try { await window.hpIg.upload(card, b.dataset.igPost); await @this.storeCard(Number(b.dataset.igPost)); } catch (e) { /* next time */ }
                }
            }, 2500));
        </script>
    @endif
    <script src="{{ asset('js/hp-share.js') }}?v=2"></script>
</x-filament-panels::page>
