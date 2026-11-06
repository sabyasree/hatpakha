{{-- Marketing desk (D51, D70). Inline styles: /desk has no utility CSS. Cards use the logo palette only. --}}
@php($d = $this->data())
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
    <x-filament::section icon="heroicon-o-question-mark-circle" collapsible>
        <x-slot name="heading">{{ __('এই পাতায় কী করবেন') }}</x-slot>
        <ol style="margin:0;padding-left:1.2rem;line-height:1.9">
            <li>{{ __('মার্কেটিং বিশেষজ্ঞ প্রতিটি নতুন লেখা, সংখ্যা আর উৎসবের জন্য পোস্ট লিখে রাখে। এখান থেকে কিছুই নিজে থেকে পোস্ট হয় না।') }}</li>
            <li>{{ __('"ফেসবুকে দিন", "হোয়াটসঅ্যাপে দিন" বা "ইনস্টাগ্রামে দিন" টিপুন: সেই সাইট বা অ্যাপ লেখা, লিংক আর ছবি সমেত খুলবে, সেখানে "পোস্ট" টিপলেই হলো। ফেসবুক বাইরের লেখা নিজে বসায় না, তাই লেখাটি কপি হয়ে থাকে: পেস্ট করে দিন। চাইলে "কপি" টিপে নিজে পেস্ট করতেও পারেন।') }}</li>
            <li>{{ __('পোস্ট হয়ে গেলে "পোস্ট করেছি" টিপুন: কার্ডটি "পোস্ট করা / বাদ"-এ চলে যাবে, যাতে কোনটা বাকি বোঝা যায়।') }}</li>
            <li>{{ __('কোনো পোস্ট দিতে না চাইলে "পোস্ট করব না" টিপুন। ভুল করে টিপলে সঙ্গে সঙ্গে "ফিরিয়ে আনুন" টিপুন, বা পরে "পোস্ট করা / বাদ" থেকে ফেরান।') }}</li>
            <li>{{ __('সপ্তাহের পরিকল্পনা শুধু পথনির্দেশ: কোন দিন কী পোস্ট করবেন। এতে কিছু টিপতে হয় না; সবসময় উপরে থাকে।') }}</li>
        </ol>
    </x-filament::section>

    {{-- The weekly plan, pinned (D70). --}}
    <x-filament::section icon="heroicon-o-calendar-days">
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
                    <span class="hp-muted">{{ $plan->created_at->locale(app()->getLocale())->diffForHumans() }}</span>
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
        <span class="hp-muted">{{ $filter === 'done' ? __('ভুল করে সরানো কিছু থাকলে "ফিরিয়ে আনুন" টিপুন।') : __('কপি করে পোস্ট করুন, তারপর "পোস্ট করেছি" টিপুন।') }}</span>
    </div>

    <div class="hp-mk">
        @forelse ($d['items'] as ['s' => $s, 'card' => $card, 'link' => $link])
            <div class="hp-mk-item" wire:key="mk-{{ $s->id }}">
                <div style="display:flex;justify-content:space-between;gap:.5rem;flex-wrap:wrap;align-items:center">
                    <strong>{{ $s->title }}</strong>
                    <span class="hp-muted">{{ __(['piece' => 'লেখা', 'issue' => 'সংখ্যা', 'weekly' => 'সাপ্তাহিক পরিকল্পনা', 'spotlight' => 'লেখক পরিচিতি', 'festival' => 'উৎসব', 'contest' => 'প্রতিযোগিতা', 'archive' => 'আর্কাইভ থেকে', 'custom' => 'নিজের বিষয়'][$s->kind] ?? $s->kind) }}
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
                    <div style="display:flex;gap:1rem;flex-wrap:wrap;align-items:flex-end">
                        @foreach (array_filter(['share' => true, 'quote' => filled($card['quote'])]) as $layout => $_)
                            <div>
                                <div class="hp-card-wrap" @if ($layout === 'share') x-data x-init="$nextTick(() => { const c = $el.querySelector('.hp-card'); if (c) { c.dataset.name = 'hatpakha-{{ $s->id }}.png'; window.hpShare?.watch($el); } })" @endif>@include('filament.partials.social-card', ['card' => $card, 'layout' => $layout])</div>
                                <x-filament::button size="xs" color="gray" icon="heroicon-o-arrow-down-tray" style="margin-top:.4rem"
                                    x-data x-on:click="window.hpCard.download($el.parentElement.querySelector('.hp-card'), 'hatpakha-{{ $s->id }}-{{ $layout }}.png')">
                                    {{ $layout === 'quote' ? __('উদ্ধৃতির ছবি নামান') : __('শেয়ারের ছবি নামান') }}
                                </x-filament::button>
                            </div>
                        @endforeach
                    </div>
                @endif
                @if ($s->status === 'ready')
                    <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center">
                        <x-filament::button size="sm" color="success" icon="heroicon-o-check" wire:click="posted({{ $s->id }})">{{ __('পোস্ট করেছি') }}</x-filament::button>
                        <x-filament::button size="sm" color="gray" icon="heroicon-o-x-mark" wire:click="dismiss({{ $s->id }})">{{ __('পোস্ট করব না') }}</x-filament::button>
                        <span class="hp-muted">{{ __('দুটোই পরে ফেরানো যায়।') }}</span>
                    </div>
                @else
                    <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
                        <span class="hp-muted">{{ $s->status === 'posted' ? '✓ '.__('পোস্ট করা হয়েছে') : __('বাদ দেওয়া') }}</span>
                        <x-filament::button size="sm" color="gray" icon="heroicon-o-arrow-uturn-left" wire:click="restore({{ $s->id }})">{{ __('ফিরিয়ে আনুন') }}</x-filament::button>
                    </div>
                @endif
            </div>
        @empty
            <x-filament::section><div class="hp-muted">{{ $filter === 'done' ? __('এখনও কিছু পোস্ট করা বা বাদ দেওয়া হয়নি।') : __('এখন কিছু নেই। নতুন লেখা প্রকাশ হলে মার্কেটিং বিশেষজ্ঞ এখানে পোস্ট তৈরি রাখবে; উপরের "নতুন পোস্ট তৈরি করুন" দিয়ে এখনই চাইতেও পারেন।') }}</div></x-filament::section>
        @endforelse
    </div>
    <script src="{{ asset('js/hp-card.js') }}?v=2"></script>
    <script src="{{ asset('js/hp-share.js') }}?v=2"></script>
</x-filament-panels::page>
