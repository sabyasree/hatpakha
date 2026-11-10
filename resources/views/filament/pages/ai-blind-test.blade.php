@php
    use App\Support\Bn;
    use App\Filament\Pages\AiBlindTest;
    $pairs = $this->pairs();
    $result = $this->result();
    $done = $pairs->filter(fn ($p) => AiBlindTest::choiceOf($p) !== null)->count();
    $pair = $pairs->get($this->postId);
    $post = $pair?->first()->post;
    $name = fn ($p) => $p === 'anthropic' ? 'Claude' : 'OpenAI';
    $words = fn ($html) => count(preg_split('/\s+/u', trim(strip_tags((string) $html)), -1, PREG_SPLIT_NO_EMPTY));
@endphp
<x-filament-panels::page>
    @if ($pairs->isEmpty())
        <x-filament::section>{{ __('এখনও কোনও পরীক্ষা হয়নি।') }}</x-filament::section>
    @else

    @if ($result)
        {{-- The reveal --}}
        @php($w = $result['wins'])
        @php($winner = $w['anthropic'] === $w['openai'] ? null : ($w['anthropic'] > $w['openai'] ? 'anthropic' : 'openai'))
        <x-filament::section icon="heroicon-o-trophy" collapsible persist-collapsed collapse-id="hp-ai-blind-test-1">
            <x-slot name="heading">{{ __('ফলাফল') }}</x-slot>
            <p style="font-size:1.25rem;margin:0 0 .75rem">
                @if ($winner)
                    {{ __('আপনার বিচারে জিতেছে') }} <strong>{{ $name($winner) }}</strong>।
                @else
                    {{ __('দুটো সমান সমান।') }}
                @endif
            </p>
            <div style="display:flex;flex-wrap:wrap;gap:1rem">
                @foreach (['anthropic', 'openai'] as $p)
                    <div style="flex:1 1 12rem;border:1px solid rgba(127,127,127,.25);border-radius:.75rem;padding:1rem">
                        <div style="font-weight:700">{{ $name($p) }}</div>
                        <div style="font-size:2rem;font-weight:700">{{ Bn::num($w[$p]) }}</div>
                        <div style="opacity:.75">{{ __('টি লেখায় এর রিপোর্ট ভালো লেগেছে') }}</div>
                    </div>
                @endforeach
                <div style="flex:1 1 12rem;border:1px solid rgba(127,127,127,.25);border-radius:.75rem;padding:1rem">
                    <div style="font-weight:700">{{ __('সমান') }}</div>
                    <div style="font-size:2rem;font-weight:700">{{ Bn::num($w['same']) }}</div>
                    <div style="opacity:.75">{{ __('টি লেখায়') }}</div>
                </div>
            </div>
            <p style="margin:.75rem 0 0;opacity:.75">{{ __('জানালেই বিজয়ীকে রোজকার রিপোর্টের জন্য বসিয়ে দেওয়া হবে।') }}</p>
        </x-filament::section>
    @else
        {{-- How it works + progress --}}
        <div style="font-size:1.05rem;line-height:1.7">
            {{ __('একই লেখা দুটো এআই পড়ে দুটো রিপোর্ট লিখেছে:') }} <strong>{{ __('রিপোর্ট A') }}</strong> {{ __('আর') }} <strong>{{ __('রিপোর্ট B') }}</strong>{{ __('। কোনটা কার, এখন বলা হবে না।
            প্রতিটি লেখায় শুধু বলুন') }} <strong>{{ __('কোন রিপোর্টটা সম্পাদক হিসেবে আপনার বেশি কাজে লাগবে।') }}</strong> {{ __('সব শেষ হলে ফল দেখা যাবে।') }}
        </div>
        <div>
            <div style="display:flex;justify-content:space-between;font-size:.9rem;opacity:.8"><span>{{ Bn::num($done) }} / {{ Bn::num($pairs->count()) }}{{ __('টি লেখা দেখা হয়েছে') }}</span>
                @if ($this->failedCount())<span>{{ Bn::num($this->failedCount()) }}{{ __('টি লেখার রিপোর্ট হয়নি, সেগুলো বাদ') }}</span>@endif</div>
            <div style="height:.5rem;border-radius:999px;background:rgba(127,127,127,.2);overflow:hidden;margin-top:.25rem">
                <div style="height:100%;width:{{ (int) round(100 * $done / max(1, $pairs->count())) }}%;background:#2DAAE2"></div>
            </div>
        </div>
    @endif

    @if ($pair)
        {{-- The piece --}}
        <x-filament::section collapsible persist-collapsed collapse-id="hp-ai-blind-test-2">
            <x-slot name="heading">{{ $post?->title }}</x-slot>
            <x-slot name="description">{{ $post?->section?->name_bn ?? '' }} · {{ Bn::num($words($post?->body_html)) }} {{ __('শব্দ') }}</x-slot>
            <details>
                <summary style="cursor:pointer;font-weight:700">{{ __('লেখাটি পড়ুন') }}</summary>
                <div style="margin-top:.75rem;max-height:24rem;overflow:auto;white-space:pre-line;line-height:1.9;padding-inline-end:.5rem">{{ trim(html_entity_decode(strip_tags(preg_replace(['~<br\s*/?>~i', '~</p>~i'], ["\n", "\n\n"], (string) $post?->body_html)))) }}</div>
            </details>
        </x-filament::section>

        {{-- The two reports, side by side (stacked on phones) --}}
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(20rem,1fr));gap:1rem;align-items:start">
            @foreach ($pair->sortBy('blind_label') as $review)
                <x-filament::section collapsible persist-collapsed collapse-id="hp-ai-blind-test-3">
                    <x-slot name="heading">
                        {{ __('রিপোর্ট') }} {{ $review->blind_label }}
                        @if ($result) <span style="font-weight:400;opacity:.75">({{ $name($review->provider) }})</span>@endif
                    </x-slot>
                    @include('filament.ai-report', ['review' => $review, 'blind' => ! $result])
                </x-filament::section>
            @endforeach
        </div>

        {{-- One question --}}
        @php($picked = AiBlindTest::choiceOf($pair))
        <x-filament::section collapsible persist-collapsed collapse-id="hp-ai-blind-test-4">
            <x-slot name="heading">{{ __('কোন রিপোর্টটা আপনার বেশি কাজে লাগবে?') }}</x-slot>
            <x-slot name="description">{{ __('লেখাটা বুঝেছে কি না, বাংলা কেমন, পরামর্শ কাজের ও সদয় কি না। দাম বা দৈর্ঘ্য নয়।') }}</x-slot>
            <div style="display:flex;flex-wrap:wrap;gap:.75rem">
                @foreach (['A' => __('রিপোর্ট A ভালো'), 'same' => __('দুটোই সমান'), 'B' => __('রিপোর্ট B ভালো')] as $value => $label)
                    <x-filament::button size="xl" :color="$picked === $value ? 'primary' : 'gray'" :outlined="$picked !== $value"
                        wire:click="choose('{{ $value }}')" wire:key="c-{{ $this->postId }}-{{ $value }}" style="flex:1 1 10rem">
                        {{ $label }}@if ($picked === $value) ✓@endif
                    </x-filament::button>
                @endforeach
            </div>
        </x-filament::section>

        {{-- Jump to any piece --}}
        <div style="display:flex;flex-wrap:wrap;gap:.35rem">
            @foreach ($pairs->keys()->values() as $i => $id)
                @php($c = AiBlindTest::choiceOf($pairs[$id]))
                <x-filament::button size="xs" :color="$id === $this->postId ? 'primary' : ($c ? 'success' : 'gray')" :outlined="$id !== $this->postId"
                    wire:click="go({{ $id }})" wire:key="n-{{ $id }}" :tooltip="$pairs[$id]->first()->post?->title">
                    {{ Bn::num($i + 1) }}@if ($c) ✓@endif
                </x-filament::button>
            @endforeach
        </div>
    @endif
    @endif
</x-filament-panels::page>
