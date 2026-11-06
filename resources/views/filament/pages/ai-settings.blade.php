<x-filament-panels::page>
    @php($s = $this->stats())
    <div class="grid gap-4 md:grid-cols-4">
        <x-filament::section><div class="text-sm text-gray-500">অবস্থা</div><div class="text-lg font-bold">{{ $s['enabled'] ? 'চালু' : 'বন্ধ' }}</div><div class="text-sm">{{ $s['provider'] }}</div></x-filament::section>
        <x-filament::section><div class="text-sm text-gray-500">এপিআই চাবি</div><div class="text-sm">Claude: {{ $s['keys']['anthropic'] ? '✓' : '—' }} · OpenAI: {{ $s['keys']['openai'] ? '✓' : '—' }}</div></x-filament::section>
        <x-filament::section><div class="text-sm text-gray-500">এই মাসের খরচ</div><div class="text-lg font-bold">{{ \App\Support\Bn::num(number_format($s['spent'], 2)) }} ডলার / {{ \App\Support\Bn::num(number_format($s['budget'], 0)) }} ডলার</div><div class="text-sm">সীমায় পৌঁছলে এআই নিজে থেকেই থেমে যায়</div></x-filament::section>
        <x-filament::section><div class="text-sm text-gray-500">এই মাসের রিপোর্ট</div><div class="text-lg font-bold">{{ \App\Support\Bn::num((int) $s['reviews']) }}</div><div class="text-sm">গড় {{ \App\Support\Bn::num(number_format($s['avg'], 3)) }} ডলার/রিপোর্ট</div></x-filament::section>
    </div>
    <form wire:submit="save" class="mt-6">
        {{ $this->form }}
        <div class="mt-4"><x-filament::button type="submit">মানদণ্ড রাখুন</x-filament::button></div>
    </form>
</x-filament-panels::page>
