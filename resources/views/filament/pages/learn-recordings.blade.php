{{-- D321: human recordings for বাংলা শিখি and the বর্ণমালা. Logo palette only. --}}
@php($n = fn ($v) => \App\Support\Bn::num((string) $v))
<x-filament-panels::page>
    <style>
        .hp-r-box { border: 1px solid rgba(127,127,127,.25); border-radius: 1rem; padding: 1rem 1.1rem; }
        .hp-r-row { display: grid; grid-template-columns: minmax(7rem, 12rem) 1fr auto; gap: .75rem; align-items: center; padding: .6rem 0; border-bottom: 1px solid rgba(127,127,127,.18); }
        .hp-r-text { font-size: 1.6rem; font-weight: 800; line-height: 1.2; }
        .hp-r-hint { font-size: .85rem; opacity: .8; }
        .hp-r-done { color: #1A86C2; font-weight: 700; font-size: .85rem; }
        .hp-r-btn { display: inline-flex; align-items: center; min-height: 2.75rem; padding: 0 1rem; border-radius: 999px; font-weight: 700; border: 2px solid currentColor; cursor: pointer; }
        .hp-r-red { background: #CB1B32; color: #fff; border-color: #CB1B32; }
        .hp-r-tools { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; }
        .hp-r-tools input[type=search] { min-height: 2.75rem; border-radius: .75rem; padding: 0 .8rem; border: 1px solid rgba(127,127,127,.4); background: transparent; min-width: 14rem; }
        @media (max-width: 640px) { .hp-r-row { grid-template-columns: 1fr; } }
    </style>
    <div class="hp-r-box">
        <p><b>{{ $n($count) }}/{{ $n($total) }}</b> {{ __('রেকর্ডিং আছে') }}. {{ __('ফোনের ভয়েস রেকর্ডারে ধীরে, পরিষ্কার করে বলুন, তারপর এখানে তুলে দিন। রাখামাত্র পাঠে বাজবে।') }}</p>
        <p class="hp-r-hint" style="margin-top:.35rem">{{ __('একটি ফাইলে একটিই অক্ষর, শব্দ বা বাক্য। mp3, m4a, ogg, wav বা webm; ৩ MB পর্যন্ত। অক্ষর হলে তার নাম বলুন (যেমন "ক"), চিহ্ন হলে "ক-এ আ-কার কা"।') }}</p>
        <div class="hp-r-tools" style="margin-top:.75rem">
            <input type="search" wire:model.live.debounce.400ms="q" placeholder="{{ __('খুঁজুন: শব্দ বা মানে') }}" aria-label="{{ __('খুঁজুন: শব্দ বা মানে') }}">
            @foreach (['all' => __('সব'), 'missing' => __('রেকর্ডিং নেই'), 'done' => __('রেকর্ডিং আছে')] as $k => $label)
                <button type="button" wire:click="$set('only', '{{ $k }}')" class="hp-r-btn {{ $only === $k ? 'hp-r-red' : '' }}">{{ $label }}</button>
            @endforeach
        </div>
    </div>
    @foreach ($groups as [$title, $rows])
        <section class="hp-r-box" wire:key="g-{{ md5($title) }}">
            <h2 class="text-lg font-bold">{{ $title }}</h2>
            @foreach ($rows as $r)
                @php($k = \App\Filament\Pages\LearnRecordings::key($r['text']))
                @php($a = $have->get(\App\Support\LearnCourse::nfc($r['text'])))
                <div class="hp-r-row" wire:key="r-{{ $k }}">
                    <div><div class="hp-r-text">{{ $r['text'] }}</div><div class="hp-r-hint">{{ $r['hint'] }}</div></div>
                    <div>@if ($a)<audio controls preload="none" src="{{ asset($a->path) }}" style="max-width:100%"></audio><div class="hp-r-done">✓ {{ __('রেকর্ডিং আছে') }}</div>@endif</div>
                    <div class="hp-r-tools">
                        <label class="hp-r-btn {{ $a ? '' : 'hp-r-red' }}">{{ $a ? __('বদলান') : __('তুলুন') }}
                            <input type="file" accept="audio/*" wire:model="files.{{ $k }}" class="sr-only"></label>
                        <span wire:loading wire:target="files.{{ $k }}" class="hp-r-hint">{{ __('তোলা হচ্ছে…') }}</span>
                        @if ($a)<button type="button" wire:click="remove('{{ $k }}')" wire:confirm="{{ __('এই রেকর্ডিং মুছবেন?') }}" class="hp-r-btn">{{ __('মুছুন') }}</button>@endif
                    </div>
                </div>
                @error('files.'.$k)<p class="hp-r-hint" style="color:#CB1B32">{{ $message }}</p>@enderror
            @endforeach
        </section>
    @endforeach
</x-filament-panels::page>
