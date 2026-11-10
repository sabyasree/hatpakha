{{-- D120: who gets the letters. --}}
<x-filament-panels::page>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap">
        <x-filament::button size="sm" tag="a" :href="static::getUrl()" :color="$source === 'signups' ? 'gray' : 'primary'">{{ __('অ্যাকাউন্ট (পাঠক ও লেখক)') }}</x-filament::button>
        <x-filament::button size="sm" tag="a" :href="static::getUrl(['source' => 'signups'])" :color="$source === 'signups' ? 'primary' : 'gray'">{{ __('অ্যাকাউন্ট ছাড়া সাইন-আপ') }}</x-filament::button>
    </div>
    {{ $this->table }}
</x-filament-panels::page>
