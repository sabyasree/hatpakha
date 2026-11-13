{{-- D178 (owner): the person's badges, each in three tiers, with how far the next tier is. $badges: Badges::for(user) --}}
@php
    use App\Support\Badges;
    use App\Support\Bn;
    $who = $who ?? '';
@endphp
<ul class="mt-3 grid gap-3 sm:grid-cols-2">
    @foreach ($badges as $b)
        <li class="flex items-start gap-3 rounded-2xl border-2 {{ $b['tier'] ? 'border-navy bg-white' : 'border-rule bg-paper' }} p-3" data-badge="{{ $b['key'] }}">
            <span class="flex-none" data-medal role="img" aria-label="{{ __($b['name']) }}{{ $b['tier'] ? ', '.__($b['tierName']) : '' }}">{!! Badges::medalSvg($b['key'], $b['tier'], 104) !!}</span>
            @if ($b['tier'])<template data-medal-full>{!! Badges::medalSvg($b['key'], $b['tier'], 480) !!}</template>@endif
            <span class="min-w-0 flex-1">
                <span class="block text-lg font-extrabold leading-tight">{{ __($b['name']) }}@if ($b['tier']) <span class="ml-1 whitespace-nowrap rounded-full bg-navy px-2 py-0.5 align-middle text-xs text-white"><span aria-hidden="true">{{ str_repeat('★', $b['tier']) }}</span> {{ __($b['tierName']) }}</span>@endif</span>
                <span class="block text-sm text-ink2">{{ Bn::num((string) $b['count']) }}{{ str_starts_with($b['unit'], 'টি') ? '' : ' ' }}{{ __($b['unit']) }}</span>
                @if ($b['next'])
                    <span class="mt-1.5 block h-2 overflow-hidden rounded-full bg-rule" aria-hidden="true"><span class="block h-full rounded-full bg-sky-deep" style="width: {{ $b['percent'] }}%"></span></span>
                    <span class="mt-1 block text-sm">{{ __(':tier পেতে আর :n', ['tier' => __($b['nextName']), 'n' => Bn::num((string) ($b['next'] - $b['count']))]) }}</span>
                @else
                    <span class="mt-1 block text-sm font-bold text-red">{{ __('সবচেয়ে উঁচু ধাপ!') }}</span>
                @endif
                @unless ($b['tier'])<span class="block text-sm text-ink2">{{ __($b['how']) }}</span>@endunless
                @if ($b['tier'])
                    {{-- D180 (owner): share the medal --}}
                    <button type="button" data-badge-share data-name="{{ __($b['name']) }}" data-tier="{{ str_repeat('★', $b['tier']).' '.__($b['tierName']) }}" data-tier-name="{{ __($b['tierName']) }}" data-who="{{ $who }}"
                        data-line="{{ Bn::num((string) $b['count']) }}{{ str_starts_with($b['unit'], 'টি') ? '' : ' ' }}{{ __($b['unit']) }}"
                        class="mt-2 flex h-11 items-center gap-1.5 rounded-full border-2 border-navy bg-white px-4 text-sm font-bold hover:bg-peach-light">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 12v7a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-7M12 15V4M8 8l4-4 4 4"/></svg>{{ __('শেয়ার করুন') }}</button>
                @endif
            </span>
        </li>
    @endforeach
</ul>
{{-- The share card is drawn in the browser (medal + words) and handed to the phone's share sheet. --}}
<div data-badge-dialog hidden class="fixed inset-0 z-[70] flex items-end justify-center bg-navy/60 sm:items-center sm:p-4" role="dialog" aria-modal="true" aria-labelledby="badge-dialog-h">
    <div class="max-h-[100dvh] w-full max-w-[460px] overflow-y-auto rounded-t-3xl border-2 border-navy bg-paper p-4 pb-[calc(1rem+env(safe-area-inset-bottom))] sm:rounded-3xl">
        <div class="flex items-center justify-between gap-3">
            <h2 id="badge-dialog-h" class="font-display text-xl font-extrabold">{{ __('ব্যাজ শেয়ার করুন') }}</h2>
            <button type="button" data-badge-close class="flex h-11 items-center rounded-full border-2 border-navy bg-white px-4 font-bold">{{ __('✕ বন্ধ') }}</button>
        </div>
        <canvas data-badge-canvas width="1080" height="1080" class="mt-3 block h-auto w-full rounded-2xl border border-rule" aria-label="{{ __('শেয়ার করার ছবি') }}"></canvas>
        <div class="mt-3 grid gap-2">
            <button type="button" data-badge-send class="flex h-12 items-center justify-center rounded-full bg-red px-6 text-lg font-bold text-white shadow-[3px_3px_0_#13212C]">{{ __('শেয়ার করুন') }}</button>
            <div class="grid grid-cols-2 gap-2">
                <button type="button" data-badge-download class="flex h-12 items-center justify-center rounded-full border-2 border-navy bg-white px-3 font-bold">{{ __('ছবি নামান') }}</button>
                <a data-badge-wa href="#" target="_blank" rel="noopener" class="flex h-12 items-center justify-center rounded-full border-2 border-navy bg-white px-3 font-bold">WhatsApp</a>
            </div>
        </div>
        <p data-badge-msg role="status" class="mt-2 min-h-5 text-center text-sm font-semibold text-sky-night" data-done="{{ __('ছবিটি নামানো হয়েছে। WhatsApp, ফেসবুক বা ইনস্টাগ্রামে দিন।') }}" data-text="{{ __('হাতপাখায় আমি “:name” ব্যাজ পেয়েছি (:tier)!', ['name' => ':name', 'tier' => ':tier']) }}" data-url="{{ url('/') }}/"></p>
    </div>
</div>
