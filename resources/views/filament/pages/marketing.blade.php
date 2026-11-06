{{-- Marketing desk (D51). Inline styles: /desk has no utility CSS. Cards use the logo palette only. --}}
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

    <div style="display:flex;gap:.5rem">
        <x-filament::button size="sm" :color="$filter === 'ready' ? 'primary' : 'gray'" wire:click="$set('filter', 'ready')">{{ __('পোস্টের জন্য তৈরি') }}</x-filament::button>
        <x-filament::button size="sm" :color="$filter === 'done' ? 'primary' : 'gray'" wire:click="$set('filter', 'done')">{{ __('পোস্ট করা / বাদ') }}</x-filament::button>
    </div>
    <p class="hp-muted">{{ __('মার্কেটিং বিশেষজ্ঞ প্রতিটি নতুন লেখা আর সংখ্যার জন্য লেখা তৈরি রাখে। কপি করে নিজের পাতায় পোস্ট করুন, তারপর "পোস্ট করেছি" টিপুন। ছবি নামিয়ে পোস্টের সঙ্গে দিন।') }}</p>

    <div class="hp-mk">
        @forelse ($d['items'] as ['s' => $s, 'card' => $card])
            <div class="hp-mk-item" wire:key="mk-{{ $s->id }}">
                <div style="display:flex;justify-content:space-between;gap:.5rem;flex-wrap:wrap;align-items:center">
                    <strong>{{ $s->title }}</strong>
                    <span class="hp-muted">{{ __(['piece' => 'লেখা', 'issue' => 'সংখ্যা', 'weekly' => 'সাপ্তাহিক পরিকল্পনা', 'spotlight' => 'লেখক পরিচিতি', 'festival' => 'উৎসব', 'contest' => 'প্রতিযোগিতা'][$s->kind] ?? $s->kind) }}
                        · {{ $s->created_at->locale(app()->getLocale())->diffForHumans() }}
                        @if ($s->status === 'posted') · ✓ {{ __('পোস্ট করা') }} @endif</span>
                </div>
                @if (! empty($s->texts['plan']))
                    <ul style="margin:0;padding-left:1.2rem;line-height:1.8">@foreach ($s->texts['plan'] as $line)<li>{{ $line }}</li>@endforeach</ul>
                @endif
                <div class="hp-mk-cols">
                    @foreach (['facebook' => 'ফেসবুক', 'whatsapp' => 'হোয়াটসঅ্যাপ', 'instagram' => 'ইনস্টাগ্রাম'] as $ch => $label)
                        @if (filled($s->texts[$ch] ?? ''))
                            <div x-data="{ copied: false }">
                                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.3rem">
                                    <span style="font-weight:600">{{ __($label) }}</span>
                                    <x-filament::button size="xs" color="gray" icon="heroicon-o-clipboard"
                                        x-on:click="navigator.clipboard.writeText($refs.t.innerText); copied = true; setTimeout(() => copied = false, 1500)">
                                        <span x-text="copied ? '{{ __('কপি হয়েছে') }}' : '{{ __('কপি') }}'"></span>
                                    </x-filament::button>
                                </div>
                                <div class="hp-mk-text" x-ref="t">{{ $s->texts[$ch] }}</div>
                            </div>
                        @endif
                    @endforeach
                </div>
                @if ($card)
                    <div style="display:flex;gap:1rem;flex-wrap:wrap;align-items:flex-end">
                        @foreach (array_filter(['share' => true, 'quote' => filled($card['quote'])]) as $layout => $_)
                            <div>
                                <div class="hp-card-wrap">@include('filament.partials.social-card', ['card' => $card, 'layout' => $layout])</div>
                                <x-filament::button size="xs" color="gray" icon="heroicon-o-arrow-down-tray" style="margin-top:.4rem"
                                    x-data x-on:click="window.hpCard.download($el.parentElement.querySelector('.hp-card'), 'hatpakha-{{ $s->id }}-{{ $layout }}.png')">
                                    {{ $layout === 'quote' ? __('উদ্ধৃতির ছবি নামান') : __('শেয়ারের ছবি নামান') }}
                                </x-filament::button>
                            </div>
                        @endforeach
                    </div>
                @endif
                @if ($s->status === 'ready')
                    <div style="display:flex;gap:.5rem">
                        <x-filament::button size="sm" color="success" icon="heroicon-o-check" wire:click="posted({{ $s->id }})">{{ __('পোস্ট করেছি') }}</x-filament::button>
                        <x-filament::button size="sm" color="gray" wire:click="dismiss({{ $s->id }})">{{ __('বাদ দিন') }}</x-filament::button>
                    </div>
                @endif
            </div>
        @empty
            <x-filament::section><div class="hp-muted">{{ __('এখন কিছু নেই। নতুন লেখা প্রকাশ হলে মার্কেটিং বিশেষজ্ঞ এখানে পোস্ট তৈরি রাখবে।') }}</div></x-filament::section>
        @endforelse
    </div>
    <script src="{{ asset('js/hp-card.js') }}?v=1"></script>
</x-filament-panels::page>
