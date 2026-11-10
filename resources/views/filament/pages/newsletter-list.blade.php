{{-- D120: who gets the letters. --}}
<x-filament-panels::page>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap">
        <x-filament::button size="sm" tag="a" :href="static::getUrl()" :color="$source === 'signups' ? 'gray' : 'primary'">{{ __('অ্যাকাউন্ট (পাঠক ও লেখক)') }}</x-filament::button>
        <x-filament::button size="sm" tag="a" :href="static::getUrl(['source' => 'signups'])" :color="$source === 'signups' ? 'primary' : 'gray'">{{ __('অ্যাকাউন্ট ছাড়া সাইন-আপ') }}</x-filament::button>
        <x-filament::button size="sm" tag="a" :href="static::getUrl(['source' => 'suspects'])" :color="$source === 'suspects' ? 'warning' : 'gray'" icon="heroicon-o-shield-exclamation">
            {{ __('বট-যাচাই') }}@if ($p = \App\Support\BotCheck::pendingCount()) ({{ \App\Support\Bn::num((string) $p) }})@endif
        </x-filament::button>
    </div>
    @if ($source === 'suspects')
        <div class="text-sm" style="opacity:.8;line-height:1.7">{{ __('এই অ্যাকাউন্টগুলো দেখতে বটের মতো। কাউকে মোছা বা আটকানো হয়নি; শুধু দেখা না হওয়া পর্যন্ত চিঠি পাবে না আর মন্তব্য দেখে নেওয়া হবে। প্রতিটি দেখে “মানুষ” বা “বট” টিপুন; একসঙ্গে অনেকগুলো বেছে নিয়েও করা যায়। নতুন কেউ বটের মতো সাইন-আপ করলে এখানেই আসবে।') }}</div>
    @endif
    {{ $this->table }}
</x-filament-panels::page>
