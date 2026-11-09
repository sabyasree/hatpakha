<x-filament-panels::page>
    @php($s = $this->stats())
    <div class="grid gap-4 md:grid-cols-4">
        <x-filament::section><div class="text-sm text-gray-500">{{ __('অবস্থা') }}</div><div class="text-lg font-bold">{{ ! $s['enabled'] ? __('বন্ধ (সার্ভারে চালু করা হয়নি)') : ($s['paused'] ? __('থামানো আছে') : __('চালু')) }}</div><div class="text-sm">{{ $s['provider'] }}</div></x-filament::section>
        <x-filament::section><div class="text-sm text-gray-500">{{ __('এপিআই চাবি') }}</div><div class="text-sm">Claude: {{ $s['keys']['anthropic'] ? '✓' : '—' }} · OpenAI: {{ $s['keys']['openai'] ? '✓' : '—' }}</div></x-filament::section>
        <x-filament::section><div class="text-sm text-gray-500">{{ __('এই মাসের খরচ') }}</div><div class="text-lg font-bold">{{ \App\Support\Bn::num(number_format($s['spent'], 2)) }} {{ __('ডলার') }}</div><div class="text-sm">{{ __('কোনো সীমা নেই; থামাতে উপরের বোতাম') }}</div></x-filament::section>
        <x-filament::section><div class="text-sm text-gray-500">{{ __('এই মাসের রিপোর্ট') }}</div><div class="text-lg font-bold">{{ \App\Support\Bn::num((int) $s['reviews']) }}</div><div class="text-sm">{{ __('গড়') }} {{ \App\Support\Bn::num(number_format($s['avg'], 3)) }} {{ __('ডলার/রিপোর্ট') }}</div></x-filament::section>
    </div>
    {{-- D119: what the AI team reads about readers, from Google Analytics and Search Console. --}}
    <x-filament::section class="mt-6" icon="heroicon-o-chart-bar" collapsible persist-collapsed collapse-id="hp-ai-settings-google">
        <x-slot name="heading">{{ __('গুগল অ্যানালিটিক্স ও সার্চ কনসোল') }}</x-slot>
        <x-slot name="description">{{ ! $s['google']['configured'] ? __('এখনও যুক্ত হয়নি। docs/GOOGLE.md-এর ধাপগুলো দেখুন।') : ($s['google']['status']['at'] ?? null ? __('শেষবার আনা হয়েছে: ').\Illuminate\Support\Carbon::parse($s['google']['status']['at'])->timezone('Asia/Kolkata')->format('d/m H:i').'। '.__('প্রতিদিন সকাল সাড়ে ৭টায় নিজে থেকে আসে।') : __('যুক্ত হয়েছে; প্রথমবার আনা বাকি।')) }}</x-slot>
        @if ($s['google']['configured'])
            <x-slot name="afterHeader"><x-filament::button size="sm" color="gray" icon="heroicon-o-arrow-path" wire:click="refreshGoogle" wire:loading.attr="disabled" wire:target="refreshGoogle">{{ __('এখনই আনুন') }}</x-filament::button></x-slot>
        @endif
        @foreach ((array) ($s['google']['status']['errors'] ?? []) as $err)
            <div class="text-sm" style="color:#CB1B32">{{ $err }}</div>
        @endforeach
        @if ($s['google']['brief'])
            <div class="text-sm text-gray-500" style="margin:.4rem 0">{{ __('এআই দল ঠিক এই লেখাটুকু পড়ে (পরিকল্পনা, পোস্ট, চিঠি, এসইও আর মাসিক হিসাবে):') }}</div>
            <div style="white-space:pre-wrap;font-size:.85rem;line-height:1.6;padding:.7rem;border-radius:.6rem;background:rgba(127,127,127,.08)">{{ $s['google']['brief'] }}</div>
        @endif
    </x-filament::section>
    <form wire:submit="save" class="mt-6">
        {{ $this->form }}
        <div class="mt-4"><x-filament::button type="submit">{{ __('মানদণ্ড রাখুন') }}</x-filament::button></div>
    </form>
</x-filament-panels::page>
