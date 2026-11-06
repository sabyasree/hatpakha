{{-- Certificates (D73): designs, signer, requests, all given. Inline styles: /desk has no utility CSS. --}}
@php
    $d = $this->data();
    $n = fn ($v) => \App\Support\Bn::num((string) $v);
    $themes = \App\Models\Certificate::THEMES;
@endphp
<x-filament-panels::page>
    <style>
        .hp-muted { font-size: .85rem; opacity: .72; }
        .hp-gallery { display: grid; gap: 1.25rem; grid-template-columns: repeat(auto-fit, minmax(19rem, 1fr)); }
        .hp-row { display: flex; gap: .75rem; align-items: center; flex-wrap: wrap; padding: .65rem 0; border-bottom: 1px solid rgba(127,127,127,.15); }
        .hp-row:last-child { border-bottom: 0; }
    </style>

    <x-filament::section icon="heroicon-o-information-circle">
        <x-slot name="heading">{{ __('কীভাবে চলে') }}</x-slot>
        <ul style="margin:0;padding-left:1.2rem;line-height:1.9">
            <li>{{ $d['auto'] ? __('নতুন লেখা প্রকাশ হলে কয়েক মিনিটের মধ্যে শংসাপত্র নিজে থেকে তৈরি হয়; অ্যাকাউন্ট থাকলে লেখক ই-মেল পান।') : __('নিজে থেকে শংসাপত্র দেওয়া এখন বন্ধ; প্রতিটি শংসাপত্র আপনি দেন।') }}</li>
            <li>{{ __('পুরনো লেখার জন্য: উপরের "একটি লেখার শংসাপত্র দিন", বা লেখার পাতায় "শংসাপত্র" মেনু, বা লেখার তালিকায় কয়েকটি বেছে "শংসাপত্র দিন"।') }}</li>
            <li>{{ __('লেখক নিজের অ্যাকাউন্টে পুরনো লেখার পাশে "শংসাপত্র চাই" টিপতে পারেন।') }} {{ $d['auto'] ? __('নিজে থেকে দেওয়া চালু থাকায় সঙ্গে সঙ্গে পান।') : __('অনুরোধ নিচে আসে।') }}</li>
            <li>{{ __('শংসাপত্র কোথাও ছবি বা ফাইল হিসেবে রাখা হয় না: যতবার খোলা হয়, নতুন করে আঁকা হয়। লেখক সেখান থেকে ছবি বা PDF নামান, প্রিন্ট করেন। QR কোড স্ক্যান করলে যাচাইয়ের পাতা খোলে।') }}</li>
        </ul>
        <div class="hp-muted" style="margin-top:.6rem">{{ __('সই') }}: <strong>{{ $d['signer']['name'] ?? '—' }}</strong>, {{ $d['signer']['title'] }} · {{ $d['signer']['image'] ? __('সইয়ের ছবি আছে') : __('সইয়ের ছবি নেই') }} · {{ __('বদলাতে উপরের "সই ও নিয়ম"।') }}</div>
    </x-filament::section>

    @if ($d['requests']->isNotEmpty())
        <x-filament::section id="requests" icon="heroicon-o-hand-raised">
            <x-slot name="heading">{{ __('লেখকদের অনুরোধ') }} ({{ $n($d['requests']->count()) }})</x-slot>
            @foreach ($d['requests'] as $task)
                <div class="hp-row" wire:key="rq-{{ $task->id }}">
                    <div style="flex:1;min-width:14rem"><strong>{{ $task->subject?->title ?? $task->title }}</strong>
                        <div class="hp-muted">{{ $task->subject?->authors->pluck('display_name')->implode(', ') }} · {{ $n($task->created_at->locale(app()->getLocale())->diffForHumans()) }}</div></div>
                    @if ($task->subject)<x-filament::button size="sm" color="gray" tag="a" target="_blank" :href="$task->subject->url()">{{ __('লেখাটি') }}</x-filament::button>@endif
                    <x-filament::button size="sm" color="success" icon="heroicon-o-check" wire:click="approveRequest({{ $task->id }})">{{ __('শংসাপত্র দিন') }}</x-filament::button>
                    <x-filament::button size="sm" color="gray" wire:click="declineRequest({{ $task->id }})">{{ __('এখন নয়') }}</x-filament::button>
                </div>
            @endforeach
        </x-filament::section>
    @endif

    <x-filament::section icon="heroicon-o-swatch">
        <x-slot name="heading">{{ __('শংসাপত্রের ধরন') }}</x-slot>
        <x-slot name="description">{{ __('কোন লেখা কোন নকশা পাবে, তা নিজে থেকে ঠিক হয়। নমুনায় সত্যিকারের শংসাপত্র থাকলে সেটাই দেখানো হয়েছে।') }}</x-slot>
        <div class="hp-gallery">
            @foreach ($d['samples'] as $theme => $sample)
                <div>
                    @include('filament.partials.cert-preview', ['certificate' => $sample])
                    <div style="margin-top:.5rem;display:flex;justify-content:space-between;gap:.5rem;flex-wrap:wrap">
                        <strong>{{ __($themes[$theme][0]) }}</strong>
                        <span class="hp-muted">{{ __('দেওয়া হয়েছে') }}: {{ $n($theme === 'publication' || $theme === 'issue' ? ($d['counts']['publication'] ?? 0) : ($d['counts'][$theme] ?? 0)) }}{{ in_array($theme, ['publication', 'issue'], true) ? '*' : '' }}</span>
                    </div>
                    <div class="hp-muted">{{ __($themes[$theme][1]) }}</div>
                </div>
            @endforeach
        </div>
        <p class="hp-muted" style="margin-top:.75rem">* {{ __('প্রকাশনা আর সংখ্যার শংসাপত্র একই গোনা: লেখা কোনো সংখ্যায় থাকলে সংখ্যার নকশা দেখায়।') }}</p>
    </x-filament::section>

    <x-filament::section icon="heroicon-o-queue-list" collapsible>
        <x-slot name="heading">{{ __('দেওয়া শংসাপত্র') }}</x-slot>
        @forelse ($d['recent'] as $c)
            <div class="hp-row" wire:key="c-{{ $c->id }}" style="{{ $c->revoked_at ? 'opacity:.55' : '' }}">
                <div style="flex:1;min-width:14rem"><strong>{{ $c->author_name_snapshot }}</strong> · {{ \Illuminate\Support\Str::limit($c->title_snapshot, 60) }}
                    <div class="hp-muted">{{ __($themes[$c->theme()][0]) }} · {{ $c->code }} · {{ \App\Support\Bn::date($c->issued_at) }}@if ($c->revoked_at) · {{ __('বাতিল') }}@endif</div></div>
                <x-filament::button size="xs" color="gray" tag="a" target="_blank" :href="$c->verifyUrl()" icon="heroicon-o-arrow-top-right-on-square">{{ __('খুলুন') }}</x-filament::button>
                @if ($c->revoked_at)
                    <x-filament::button size="xs" color="gray" icon="heroicon-o-arrow-uturn-left" wire:click="restore({{ $c->id }})">{{ __('ফিরিয়ে আনুন') }}</x-filament::button>
                @else
                    <x-filament::button size="xs" color="danger" icon="heroicon-o-no-symbol" wire:click="withdraw({{ $c->id }})" wire:confirm="{{ __('এই শংসাপত্রটি বাতিল করবেন? যাচাইয়ের পাতায় “বাতিল” দেখাবে।') }}">{{ __('বাতিল') }}</x-filament::button>
                @endif
            </div>
        @empty
            <div class="hp-muted">{{ __('এখনও কোনো শংসাপত্র দেওয়া হয়নি।') }}</div>
        @endforelse
    </x-filament::section>
</x-filament-panels::page>
