{{-- Honouring writers (D76). Inline styles: /desk has no utility CSS. --}}
@php
    $d = $this->data();
    $n = fn ($v) => \App\Support\Bn::num((string) $v);
@endphp
<x-filament-panels::page>
    <style>
        .hp-muted { font-size: .85rem; opacity: .72; }
        .hp-cards { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(17rem, 1fr)); }
        .hp-card2 { border: 1px solid rgba(127,127,127,.25); border-radius: .9rem; padding: 1rem; display: grid; gap: .5rem; align-content: start; }
        .hp-av { width: 3rem; height: 3rem; border-radius: 999px; object-fit: cover; display: flex; align-items: center; justify-content: center; font-weight: 800; background: #FBEBD8; color: #7A1022; flex: none; }
        .hp-stats { display: flex; flex-wrap: wrap; gap: .35rem; }
        .hp-stats span { font-size: .75rem; padding: .1rem .5rem; border-radius: 999px; background: rgba(127,127,127,.1); }
    </style>

    <x-filament::section icon="heroicon-o-information-circle">
        <x-slot name="heading">{{ __('যেভাবে কাজ করে') }}</x-slot>
        <ol style="margin:0;padding-left:1.2rem;line-height:1.9">
            <li>{{ __('নিচের তালিকা থেকে বা যেকোনো লেখকের পাতা থেকে "সম্মান দিন" টিপুন: কোন সম্মান (মাসের লেখক, সম্পাদকের পছন্দের লেখক, নতুন প্রতিভা, হাতপাখা সম্মান) আর আপনার প্রশংসার কথা। চাইলে এআই প্রথম খসড়া লিখে দেবে।') }}</li>
            <li>{{ __('সাইটে দেখা যাবে: প্রথম পাতায় "সম্মানিত লেখক" (৬০ দিন), লেখকের পাতায় ★ ব্যাজ আর আপনার কথা, প্রতিটি লেখার নিচের লেখক-পরিচিতিতে ★।') }}</li>
            <li>{{ __('লেখক পান একটি সম্মাননা শংসাপত্র আর (চাইলে) অভিনন্দনের ই-মেল; মার্কেটিং পাতায় তাঁকে নিয়ে একটি পোস্ট তৈরি হয়।') }}</li>
            <li>{{ __('প্রতি মাসের ১ তারিখে এআই সম্পাদক একজন "মাসের লেখক" প্রস্তাব করে (আজকের কাজে আসে); এআই দল পাতা থেকে এখনই চাইতেও পারেন।') }}</li>
        </ol>
    </x-filament::section>

    @if ($p = $d['proposal'])
        <x-filament::section icon="heroicon-o-sparkles">
            <x-slot name="heading">{{ __('এআই সম্পাদকের প্রস্তাব') }}</x-slot>
            <strong>{{ $p->title }}</strong>
            @if ($p->payload['why'] ?? null)<p class="hp-muted" style="margin:.3rem 0 0">{{ $p->payload['why'] }}</p>@endif
            <div style="margin-top:.6rem"><x-filament::button tag="a" size="sm" :href="\App\Filament\Pages\Dashboard::getUrl().'#tasks'" icon="heroicon-o-arrow-right">{{ __('আজকের কাজে দেখে সম্মান দিন') }}</x-filament::button></div>
        </x-filament::section>
    @endif

    <x-filament::section icon="heroicon-o-star">
        <x-slot name="heading">{{ __('এখন সম্মানিত') }} ({{ $n($d['honoured']->count()) }})</x-slot>
        @if ($d['honoured']->isEmpty())
            <div class="hp-muted">{{ __('এখনও কাউকে সম্মান দেওয়া হয়নি, তাই সাইটে কিছু দেখা যাচ্ছে না। নিচের তালিকা থেকে শুরু করুন।') }}</div>
        @else
            <div class="hp-cards">
                @foreach ($d['honoured'] as $a)
                    <div class="hp-card2" wire:key="h-{{ $a->id }}">
                        <div style="display:flex;gap:.75rem;align-items:center">
                            @if ($a->avatar_path)<img src="{{ $a->avatar_path }}" alt="" class="hp-av">@else<span class="hp-av">{{ $a->initial() }}</span>@endif
                            <div><strong>{{ $a->publicName() }}</strong><div style="color:#CB1B32;font-weight:700;font-size:.85rem">★ {{ $a->honour_label }}</div>
                                <div class="hp-muted">{{ $a->honoured_at ? \App\Support\Bn::date($a->honoured_at) : '' }}</div></div>
                        </div>
                        @if ($a->honour_note)<p style="margin:0;font-size:.9rem;line-height:1.6">“{{ \Illuminate\Support\Str::limit($a->honour_note, 220) }}”</p>@endif
                        <div style="display:flex;gap:.4rem;flex-wrap:wrap">
                            <x-filament::button size="xs" color="gray" tag="a" target="_blank" :href="$a->url()" icon="heroicon-o-arrow-top-right-on-square">{{ __('সাইটে দেখুন') }}</x-filament::button>
                            {{ ($this->honourAction)(['author' => $a->id]) }}
                            <x-filament::button size="xs" color="gray" icon="heroicon-o-x-mark" wire:click="unhonour({{ $a->id }})" wire:confirm="{{ __('সম্মানটি সরাবেন?') }}">{{ __('সরান') }}</x-filament::button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>

    <x-filament::section icon="heroicon-o-trophy">
        <x-slot name="heading">{{ __('যাঁদের সম্মান দেওয়া যায়') }}</x-slot>
        <x-slot name="description">{{ __('পুরো আর্কাইভ থেকে, শুধু সংখ্যা দিয়ে: প্রকাশিত লেখা, পাঠকের ভালো লাগা ও মতামত, পড়া, আর এআই সম্পাদকের নম্বর। শেষ সিদ্ধান্ত আপনার।') }}</x-slot>
        <div class="hp-cards">
            @forelse ($d['candidates'] as $c)
                @php($a = $c['author'])
                <div class="hp-card2" wire:key="c-{{ $a->id }}">
                    <div style="display:flex;gap:.75rem;align-items:center">
                        @if ($a->avatar_path)<img src="{{ $a->avatar_path }}" alt="" class="hp-av">@else<span class="hp-av">{{ $a->initial() }}</span>@endif
                        <div style="min-width:0"><strong>{{ $a->publicName() }}</strong>
                            @if ($c['best'])<div class="hp-muted">{{ __('যেমন') }}: {{ \Illuminate\Support\Str::limit($c['best']->title, 40) }}</div>@endif</div>
                    </div>
                    <div class="hp-stats">
                        <span>{{ $n($c['pieces']) }} {{ __('লেখা') }}</span>
                        @if ($c['likes'])<span>♥ {{ $n($c['likes']) }}</span>@endif
                        @if ($c['comments'])<span>{{ $n($c['comments']) }} {{ __('মতামত') }}</span>@endif
                        @if ($c['reads'])<span>{{ $n($c['reads']) }} {{ __('বার পড়া') }}</span>@endif
                        @if ($c['score'])<span>{{ __('এআই') }} {{ $n($c['score']) }}/১০</span>@endif
                    </div>
                    <div style="display:flex;gap:.4rem;flex-wrap:wrap">
                        {{ ($this->honourAction)(['author' => $a->id]) }}
                        <x-filament::button size="xs" color="gray" tag="a" target="_blank" :href="$a->url()" icon="heroicon-o-arrow-top-right-on-square">{{ __('লেখা দেখুন') }}</x-filament::button>
                    </div>
                </div>
            @empty
                <div class="hp-muted">{{ __('এখনও যথেষ্ট তথ্য নেই।') }}</div>
            @endforelse
        </div>
    </x-filament::section>
</x-filament-panels::page>
