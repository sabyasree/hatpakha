{{-- Marketing desk (D51, D70). Inline styles: /desk has no utility CSS. Cards use the logo palette only. --}}
@php($d = $this->data())
@php($isAdmin = \App\Filament\Support\Deleting::isAdmin())
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
        @php($fbAuto = \App\Support\FacebookPage::auto())
        <x-filament::section icon="heroicon-o-globe-alt">
            <x-slot name="heading">{{ __('ফেসবুক পেজে পোস্ট') }}</x-slot>
            <div style="display:flex;gap:1rem;flex-wrap:wrap;align-items:center;justify-content:space-between">
                <div style="max-width:46rem">
                    <b>{{ $fbAuto ? __('নিজে থেকে পোস্ট: চালু') : __('নিজে থেকে পোস্ট: বন্ধ') }}</b>
                    <p class="hp-muted" style="margin-top:.2rem">{{ $fbAuto
                        ? __('রোজ সকাল ১০টা আর সন্ধ্যা সাড়ে ৭টায় নিচের তালিকার সবচেয়ে পুরোনো তৈরি পোস্টটি নিজে থেকে পেজে যায় (দিনে বড়জোর দুটি)। কোনোটি না চাইলে “পোস্ট করব না” টিপুন।')
                        : __('এখন প্রতিটি পোস্ট আপনি “ফেসবুকে দিন” বা “সময় ঠিক করুন” টিপলেই যায়। চালু করলে রোজ দুটি পোস্ট নিজে থেকে যাবে।') }}</p>
                    @if ($fbNext = \App\Support\FacebookPage::nextAuto())<p style="margin-top:.2rem">{{ __('পরের পোস্ট') }}: <b>{{ $fbNext->title }}</b></p>@endif
                    @unless (\App\Support\FacebookPage::ready())<p class="hp-muted" style="margin-top:.2rem">⚠ {{ __('এটি আসল সাইট নয়: এখান থেকে ফেসবুকে কিছু যায় না।') }}</p>@endunless
                </div>
                @if ($isAdmin)
                    <x-filament::button :color="$fbAuto ? 'gray' : 'primary'" icon="heroicon-o-bolt" wire:click="toggleFacebookAuto"
                        wire:confirm="{{ $fbAuto ? __('নিজে থেকে পোস্ট বন্ধ করবেন?') : __('চালু করলে রোজ দুটি তৈরি পোস্ট আপনার না দেখে পেজে যাবে। চালু করবেন?') }}">{{ $fbAuto ? __('বন্ধ করুন') : __('নিজে থেকে পোস্ট চালু করুন') }}</x-filament::button>
                @endif
            </div>
        </x-filament::section>
    @endif

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
                    <span class="hp-muted">{{ __(['piece' => 'লেখা', 'issue' => 'সংখ্যা', 'weekly' => 'সাপ্তাহিক পরিকল্পনা', 'spotlight' => 'লেখক পরিচিতি', 'festival' => 'উৎসব', 'contest' => 'প্রতিযোগিতা', 'archive' => 'আর্কাইভ থেকে', 'custom' => 'নিজের বিষয়', 'poster' => 'পোস্টার'][$s->kind] ?? $s->kind) }}
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
