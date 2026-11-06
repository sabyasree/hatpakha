<x-filament-panels::page>
    @php($s = $this->stats())
    <div class="grid gap-4 md:grid-cols-4">
        <x-filament::section><div class="text-sm text-gray-500">{{ __('অবস্থা') }}</div><div class="text-lg font-bold">{{ ! $s['enabled'] ? __('বন্ধ (সার্ভারে চালু করা হয়নি)') : ($s['paused'] ? __('থামানো আছে') : __('চালু')) }}</div><div class="text-sm">{{ $s['provider'] }}</div></x-filament::section>
        <x-filament::section><div class="text-sm text-gray-500">{{ __('এপিআই চাবি') }}</div><div class="text-sm">Claude: {{ $s['keys']['anthropic'] ? '✓' : '—' }} · OpenAI: {{ $s['keys']['openai'] ? '✓' : '—' }}</div></x-filament::section>
        <x-filament::section><div class="text-sm text-gray-500">{{ __('এই মাসের খরচ') }}</div><div class="text-lg font-bold">{{ \App\Support\Bn::num(number_format($s['spent'], 2)) }} {{ __('ডলার') }}</div><div class="text-sm">{{ __('কোনো সীমা নেই; থামাতে উপরের বোতাম') }}</div></x-filament::section>
        <x-filament::section><div class="text-sm text-gray-500">{{ __('এই মাসের রিপোর্ট') }}</div><div class="text-lg font-bold">{{ \App\Support\Bn::num((int) $s['reviews']) }}</div><div class="text-sm">{{ __('গড়') }} {{ \App\Support\Bn::num(number_format($s['avg'], 3)) }} {{ __('ডলার/রিপোর্ট') }}</div></x-filament::section>
    </div>
    <form wire:submit="save" class="mt-6">
        {{ $this->form }}
        <div class="mt-4"><x-filament::button type="submit">{{ __('মানদণ্ড রাখুন') }}</x-filament::button></div>
    </form>
</x-filament-panels::page>
