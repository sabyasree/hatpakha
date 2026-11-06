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
            <li>{{ __('তালিকায় লেখার মানই আগে: এআই সম্পাদক প্রত্যেকের কয়েকটি লেখা পড়ে নম্বর দেয়। এই নম্বর শুধু আপনি দেখেন, পাঠকেরা নয়।') }}</li>
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

    <x-filament::section icon="heroicon-o-flag" collapsible>
        <x-slot name="heading">{{ __('মাইলফলক') }} @if ($c = $d['milestones']->where('status', 'proposed')->count())({{ $n($c) }} {{ __('নতুন') }})@endif</x-slot>
        <x-slot name="description">{{ __('লেখকদের প্রথম লেখা, ১০/২৫/৫০/১০০তম লেখা, হাতপাখায় ৫/১০/১৫ বছর: সত্যিকারের তথ্য থেকে রোজ খোঁজা হয়। অনুমোদন দিলে প্রথম পাতার "অভিনন্দন" সারিতে ৪৫ দিন দেখা যাবে।') }}</x-slot>
        @forelse ($d['milestones'] as $m)
            <div wire:key="ms-{{ $m->id }}" style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;padding:.55rem 0;border-bottom:1px solid rgba(127,127,127,.15)">
                <div style="flex:1;min-width:14rem"><strong>{{ $m->author?->publicName() }}</strong> · {{ $m->label }}
                    <div class="hp-muted">{{ \App\Support\Bn::date($m->reached_at) }}@if ($m->post) · {{ \Illuminate\Support\Str::limit($m->post->title, 50) }}@endif</div></div>
                @if ($m->status === 'approved')
                    <span style="font-size:.85rem;font-weight:700;color:#1A86C2">✓ {{ __('প্রথম পাতায় আছে') }}</span>
                    <x-filament::button size="xs" color="gray" wire:click="milestone({{ $m->id }}, false)">{{ __('সরান') }}</x-filament::button>
                @else
                    <x-filament::button size="xs" color="success" icon="heroicon-o-check" wire:click="milestone({{ $m->id }}, true)">{{ __('অনুমোদন') }}</x-filament::button>
                    <x-filament::button size="xs" color="gray" wire:click="milestone({{ $m->id }}, false)">{{ __('এখন নয়') }}</x-filament::button>
                @endif
            </div>
        @empty
            <div class="hp-muted">{{ __('গত ৬০ দিনে নতুন মাইলফলক নেই।') }}</div>
        @endforelse
    </x-filament::section>

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
        <x-slot name="description">{{ __('লেখার মানই আগে: এআই সম্পাদক প্রত্যেকের কয়েকটি লেখা পড়ে নম্বর দেয়, সেটাই ক্রম ঠিক করে। লেখার সংখ্যা সামান্য গুরুত্ব পায় (যত বেশিই হোক), সাম্প্রতিক লেখা বেশি; পাঠকের ভালো লাগা, মতামত আর পড়া যোগ হয়। যাঁদের লেখা এখনও পড়া হয়নি, তাঁরা পরে। শেষ সিদ্ধান্ত আপনার।') }}</x-slot>
        @if ($d['reading'])
            <div style="padding:.6rem .8rem;border-radius:.6rem;background:rgba(45,170,226,.12);margin-bottom:.8rem">{{ __('এআই সম্পাদক এখন লেখা পড়ছে; কয়েক মিনিট পরে পাতাটি আবার খুলুন।') }}</div>
        @elseif ($d['readCount'] === 0)
            <div style="padding:.6rem .8rem;border-radius:.6rem;background:rgba(239,192,141,.25);margin-bottom:.8rem">{{ __('এখনও কারও লেখা পড়া হয়নি, তাই তালিকাটি শুধু সংখ্যা দিয়ে সাজানো। উপরের "এআই দিয়ে লেখা পড়ান" টিপুন (প্রতি মাসের ১ তারিখেও নিজে থেকে পড়ে)।') }}</div>
        @endif
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
                        @if ($c['recent'])<span>{{ $n($c['recent']) }} {{ __('টি গত দুই বছরে') }}</span>@endif
                    </div>
                    @if ($c['quality'] !== null)
                        <div style="padding:.5rem .6rem;border-radius:.6rem;background:rgba(239,192,141,.22)">
                            <strong style="color:#7A1022">{{ __('লেখার মান') }}: {{ $n($c['quality']) }}/১০</strong>
                            <div style="font-size:.85rem;line-height:1.5;margin-top:.15rem">{{ $c['author']->quality_note }}</div>
                        </div>
                    @else
                        <div class="hp-muted">{{ __('লেখা এখনও পড়া হয়নি') }}</div>
                    @endif
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
