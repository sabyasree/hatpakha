{{-- D101: ছোটদের হাতপাখা at the desk. Inline styles: /desk has no utility CSS. --}}
@php
    $d = $this->data();
@endphp
<x-filament-panels::page>
    <style>
        .hp-k-grid { display: grid; gap: .6rem; grid-template-columns: repeat(auto-fill, minmax(9.5rem, 1fr)); }
        .hp-k-card { border: 1px solid rgba(127,127,127,.3); border-radius: .8rem; padding: .6rem; display: grid; gap: .35rem; text-align: center; }
        .hp-k-letter { font-size: 2rem; font-weight: 800; line-height: 1; }
        .hp-k-pic { width: 100%; aspect-ratio: 1; object-fit: cover; border-radius: .6rem; background: rgba(127,127,127,.08); }
        .hp-muted { font-size: .82rem; opacity: .7; }
        .hp-k-row { display: flex; gap: .3rem; justify-content: center; flex-wrap: wrap; }
        .hp-k-table td, .hp-k-table th { padding: .45rem .5rem; text-align: left; border-bottom: 1px solid rgba(127,127,127,.2); vertical-align: top; }
    </style>

    <x-filament::section icon="heroicon-o-information-circle" collapsible collapsed>
        <x-slot name="heading">{{ __('কীভাবে চলে') }}</x-slot>
        <ul style="margin:0;padding-left:1.2rem;line-height:1.9">
            <li>{{ __('উপরের বোতামে এআই দলকে কাজ দিন: ডিজাইনার অক্ষরের ছবি আঁকে, কথক (যন্ত্রকণ্ঠ) পড়ে শোনায়, শিক্ষক বয়স আর শব্দের মানে ঠিক করে।') }}</li>
            <li>{{ __('কোনো ছবি বা কণ্ঠ আপনার অনুমোদনের আগে পাঠকের কাছে যায় না। পছন্দ না হলে "সরান" টিপুন, আবার তৈরি করা যাবে।') }}</li>
            <li>{{ __('পাতায় সবসময় লেখা থাকে “কণ্ঠ: হাতপাখার যন্ত্রকণ্ঠ”। ছোটদের পাতায় মন্তব্য আর বিজ্ঞাপন নেই।') }}</li>
            <li>{{ __('ক্লাসিক লেখাগুলো উইকিসংকলন থেকে নেওয়া, খসড়া হিসেবে আছে: উৎসের সঙ্গে মিলিয়ে প্রকাশ করুন।') }}</li>
        </ul>
    </x-filament::section>

    {{-- D102: choose the voice by ear, separately for the alphabet and for rhymes and stories --}}
    <x-filament::section icon="heroicon-o-speaker-wave">
        <x-slot name="heading">{{ __('কণ্ঠ বাছাই') }}</x-slot>
        <x-slot name="description">{{ __('প্রতিটি কণ্ঠে একই লাইন শুনে বেছে নিন। বাছাইয়ের পরে নতুন পাঠ সেই কণ্ঠে হবে।') }}</x-slot>
        <x-slot name="afterHeader">
            <x-filament::button size="sm" color="gray" icon="heroicon-o-play" wire:click="makeSamples">{{ __('কণ্ঠের নমুনা তৈরি করুন') }}</x-filament::button>
        </x-slot>
        <div style="display:grid;gap:1.2rem;grid-template-columns:repeat(auto-fit,minmax(18rem,1fr))">
            @foreach ($d['uses'] as $use => [$useLabel, $line])
                <div>
                    <div style="font-weight:700">{{ __($useLabel) }}: <span class="hp-muted">{{ __('এখন') }} “{{ $d['chosen'][$use] }}”</span></div>
                    <div class="hp-muted" style="margin:.2rem 0 .5rem;white-space:pre-line">{{ $line }}</div>
                    <table class="hp-k-table" style="width:100%;border-collapse:collapse;font-size:.92rem">
                        @foreach ($d['voices'] as $voice)
                            <tr wire:key="v-{{ $use }}-{{ $voice }}">
                                <td style="width:9.5rem;font-weight:600">{{ $voice }}<div class="hp-muted" style="font-weight:400">{{ \App\Ai\Narrator::isGoogle($voice) ? __('Google · বাংলা') : __('OpenAI · ইংরেজি-ভিত্তিক') }}</div></td>
                                <td>@if (! empty($d['samples'][$use][$voice]))<audio controls preload="none" src="{{ $d['samples'][$use][$voice] }}" style="width:100%;height:2.2rem"></audio>@else<span class="hp-muted">{{ __('নমুনা নেই') }}</span>@endif</td>
                                <td style="width:7rem;text-align:right">
                                    @if ($d['chosen'][$use] === $voice)
                                        <span style="font-weight:700">✓ {{ __('বাছা') }}</span>
                                    @else
                                        <x-filament::button size="xs" color="gray" wire:click="chooseVoice('{{ $use }}', '{{ $voice }}')">{{ __('এটি নিন') }}</x-filament::button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </table>
                </div>
            @endforeach
        </div>
        @if (array_sum($d['outdated']))
            <div style="margin-top:1rem;display:flex;gap:.8rem;align-items:center;flex-wrap:wrap">
                <span>{{ __('পুরনো কণ্ঠে করা পাঠ') }}: {{ __('বর্ণমালা') }} {{ \App\Support\Bn::num((string) $d['outdated']['alphabet']) }}, {{ __('ছড়া ও গল্প') }} {{ \App\Support\Bn::num((string) $d['outdated']['stories']) }}</span>
                <x-filament::button size="sm" color="warning" wire:click="revoice"
                    wire:confirm="{{ __('পুরনো কণ্ঠের পাঠ নতুন কণ্ঠে আবার করা হবে। প্রতিটি আবার আপনার অনুমোদনের অপেক্ষায় থাকবে, ততক্ষণ পাতায় সেই পাঠ দেখা যাবে না। এগোবেন?') }}">{{ __('নতুন কণ্ঠে আবার করুন') }}</x-filament::button>
            </div>
        @endif
    </x-filament::section>

    <x-filament::section icon="heroicon-o-language">
        <x-slot name="heading">{{ __('বর্ণমালা') }}</x-slot>
        <x-slot name="afterHeader">
            <x-filament::button size="sm" color="gray" wire:click="approveAll('pic')">{{ __('সব ছবি অনুমোদন') }}</x-filament::button>
            <x-filament::button size="sm" color="gray" wire:click="approveAll('audio')">{{ __('সব কণ্ঠ অনুমোদন') }}</x-filament::button>
            <x-filament::button size="sm" tag="a" :href="\App\Support\Url::to('/bornomala')" target="_blank" icon="heroicon-o-eye">{{ __('পাতা দেখুন') }}</x-filament::button>
        </x-slot>
        <div class="hp-k-grid">
            @foreach ($d['letters'] as $i => [$letter, $word])
                @php
                    $s = $d['state'][$i] ?? [];
                @endphp
                <div class="hp-k-card" wire:key="k-{{ $i }}">
                    <div class="hp-k-letter">{{ $letter }}</div>
                    <div>{{ $word }}</div>
                    @if (! empty($s['pic']))
                        <img src="{{ $s['pic'] }}" alt="" class="hp-k-pic">
                        <div class="hp-k-row">
                            @if (empty($s['pic_ok']))<x-filament::button size="xs" color="success" wire:click="decide({{ $i }}, 'pic', true)">{{ __('ছবি ঠিক আছে') }}</x-filament::button>@else<span class="hp-muted">✓ {{ __('ছবি') }}</span>@endif
                            <x-filament::button size="xs" color="gray" wire:click="decide({{ $i }}, 'pic', false)">{{ __('সরান') }}</x-filament::button>
                        </div>
                    @else
                        <div class="hp-muted">{{ __('ছবি নেই') }}</div>
                    @endif
                    @if (! empty($s['audio']))
                        <audio controls preload="none" src="{{ $s['audio'] }}" style="width:100%"></audio>
                        <div class="hp-k-row">
                            @if (empty($s['audio_ok']))<x-filament::button size="xs" color="success" wire:click="decide({{ $i }}, 'audio', true)">{{ __('কণ্ঠ ঠিক আছে') }}</x-filament::button>@else<span class="hp-muted">✓ {{ __('কণ্ঠ') }}</span>@endif
                            <x-filament::button size="xs" color="gray" wire:click="decide({{ $i }}, 'audio', false)">{{ __('সরান') }}</x-filament::button>
                        </div>
                    @else
                        <div class="hp-muted">{{ __('কণ্ঠ নেই') }}</div>
                    @endif
                </div>
            @endforeach
        </div>
    </x-filament::section>

    <x-filament::section icon="heroicon-o-book-open">
        <x-slot name="heading">{{ __('ছোটদের লেখা') }}</x-slot>
        @if ($d['pieces']->isEmpty())
            <div class="hp-muted">{{ __('এখনও কোনো লেখা নেই।') }}</div>
        @else
            <table class="hp-k-table" style="width:100%;border-collapse:collapse;font-size:.92rem">
                <tr><th>{{ __('লেখা') }}</th><th>{{ __('অবস্থা') }}</th><th>{{ __('বয়স') }}</th><th>{{ __('শব্দের মানে') }}</th><th>{{ __('পাঠ') }}</th></tr>
                @foreach ($d['pieces'] as $p)
                    <tr>
                        <td><a href="{{ \App\Filament\Resources\Posts\PostResource::getUrl('edit', ['record' => $p]) }}" style="text-decoration:underline;font-weight:600">{{ $p->title }}</a><div class="hp-muted">{{ $p->authors->first()?->publicName() }}</div></td>
                        <td>{{ __(['draft' => 'খসড়া', 'pending' => 'অপেক্ষায়', 'scheduled' => 'নির্ধারিত', 'published' => 'প্রকাশিত'][$p->status] ?? $p->status) }}</td>
                        <td>{{ $p->age_band ? __(\App\Ai\KidsTeacher::AGES[$p->age_band] ?? $p->age_band) : '—' }}</td>
                        <td>{{ $p->glossary ? \App\Support\Bn::num((string) count($p->glossary)).'টি' : '—' }}</td>
                        <td>{{ $p->audio_path ? __($p->audio_status === 'approved' ? 'অনুমোদিত' : 'শোনার অপেক্ষায়').($p->audio_ai ? ' ('.__('এআই').')' : '') : '—' }}</td>
                    </tr>
                @endforeach
            </table>
        @endif
    </x-filament::section>
</x-filament-panels::page>
