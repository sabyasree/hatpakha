{{-- Each month's issue as a checklist (owner, 2026-11-12): cover → editorial → magazine. The AI team makes each part by
     itself (App\Ai\IssueWatch); the owner chooses and publishes. Readers read on the site; the EPUB is for the owner. --}}
@php
    use App\Ai\IssueWatch;
    use App\Support\Bn;
    $admin = auth()->user()->hasRole(\App\Enums\Role::Admin);
@endphp
<x-filament-panels::page>
    <style>
        .hp-mz { display: grid; gap: 1rem; }
        .hp-mz-card { border: 1px solid rgba(127,127,127,.25); border-radius: 1rem; padding: 1rem 1.1rem; display: grid; gap: 1rem; grid-template-columns: 9rem 1fr; }
        @media (max-width: 640px) { .hp-mz-card { grid-template-columns: 1fr; } }
        .hp-mz-cover { width: 9rem; aspect-ratio: 1/1.41; border-radius: .4rem; overflow: hidden; background: #13212C; position: relative; color: #FFFBF4; }
        .hp-mz-cover img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
        .hp-mz-cover .m { position: absolute; left: .5rem; top: .35rem; font-family: 'Galada', cursive; font-size: 1.35rem; text-shadow: 0 1px 3px rgba(19,33,44,.6); }
        .hp-mz-cover .t { position: absolute; left: 0; right: 0; bottom: 0; background: #13212C; padding: .35rem .5rem; font-weight: 800; font-size: .8rem; line-height: 1.25; }
        .hp-mz-cover .fan { position: absolute; right: -35%; top: 25%; width: 85%; aspect-ratio: 1; border-radius: 50%; background: repeating-conic-gradient(#CB1B32 0 7deg, #F08D90 7deg 14deg); }
        .hp-mz-tag { display: inline-block; font-size: .75rem; font-weight: 700; padding: .05rem .5rem; border-radius: 999px; background: #EFC08D; color: #13212C; margin-top: .3rem; }
        .hp-mz-steps { display: grid; gap: .6rem; margin: 0; padding: 0; list-style: none; }
        .hp-mz-step { display: grid; grid-template-columns: 2rem 1fr; gap: .6rem; align-items: start; }
        .hp-mz-dot { width: 2rem; height: 2rem; border-radius: 999px; display: grid; place-items: center; font-weight: 800; }
        .hp-mz-ok { background: #13212C; color: #FFFBF4; } .hp-mz-wait { background: #EFC08D; color: #13212C; } .hp-mz-no { background: #CB1B32; color: #fff; }
        .hp-mz-step b { display: block; }
        .hp-mz-btns { display: flex; flex-wrap: wrap; gap: .4rem; margin-top: .35rem; }
        .hp-muted { font-size: .85rem; opacity: .75; }
        .hp-mz-past { display: grid; gap: .5rem; grid-template-columns: repeat(auto-fill, minmax(15rem, 1fr)); }
        .hp-mz-past > div { border: 1px solid rgba(127,127,127,.25); border-radius: .8rem; padding: .7rem .9rem; }
    </style>

    <x-filament::section>
        <p>{{ __('প্রতি মাসের ২০ তারিখে এআই সম্পাদক পরের সংখ্যার প্রস্তাব দেন, সম্পাদকীয়র খসড়া লেখেন, আর ডিজাইনার প্রচ্ছদ আঁকেন। কিছু বাদ পড়লে রোজ আবার চেষ্টা হয়; ২৫ আর ২৮ তারিখে ফোনে মনে করানো হয়। আপনি শুধু প্রচ্ছদ বেছে নিন আর সম্পাদকীয় পড়ে প্রকাশ করুন।') }}</p>
    </x-filament::section>

    <div class="hp-mz">
        @forelse ($current as $i)
            @php($s = IssueWatch::status($i))
            @php($img = $i->cover_path ?: $i->proposed_cover_path)
            <div class="hp-mz-card">
                <div>
                    <div class="hp-mz-cover" aria-hidden="true">
                        @if ($img)<img src="{{ asset(ltrim($img, '/')) }}" alt="">@else<div class="fan"></div>@endif
                        <span class="m">হাতপাখা</span>
                        <span class="t">{{ $i->title }}</span>
                    </div>
                    @if ($s['cover'] === 'proposed')<span class="hp-mz-tag">{{ __('ডিজাইনারের প্রস্তাব') }}</span>@endif
                </div>
                <div>
                    <h3 style="font-size:1.15rem;font-weight:800">{{ $i->title }}</h3>
                    <p class="hp-muted">{{ $i->status === 'published' ? __('প্রকাশিত') : __('আসছে') }} · {{ $i->slug }}</p>
                    <ol class="hp-mz-steps" style="margin-top:.6rem">
                        {{-- 1. Cover --}}
                        <li class="hp-mz-step">
                            <span class="hp-mz-dot {{ ['set' => 'hp-mz-ok', 'proposed' => 'hp-mz-wait', 'missing' => 'hp-mz-no'][$s['cover']] }}" aria-hidden="true">{{ $s['cover'] === 'set' ? '✓' : '১' }}</span>
                            <div>
                                <b>{{ __('প্রচ্ছদ') }}: {{ ['set' => __('ঠিক হয়ে গেছে'), 'proposed' => __('ডিজাইনারের প্রচ্ছদ দেখে বেছে নিন'), 'missing' => __('এখনও আঁকা হয়নি')][$s['cover']] }}</b>
                                @if ($admin)
                                    <div class="hp-mz-btns">
                                        @if ($s['cover'] === 'proposed')
                                            <x-filament::button size="sm" color="danger" icon="heroicon-o-check" wire:click="useCover({{ $i->id }})">{{ __('এই প্রচ্ছদ নিন') }}</x-filament::button>
                                        @endif
                                        <x-filament::button size="sm" :color="$s['cover'] === 'missing' ? 'primary' : 'gray'" icon="heroicon-o-paint-brush" wire:click="makeCover({{ $i->id }})">{{ $s['cover'] === 'missing' ? __('এখনই আঁকান') : __('আরেকটি আঁকান') }}</x-filament::button>
                                    </div>
                                @endif
                            </div>
                        </li>
                        {{-- 2. Editorial --}}
                        <li class="hp-mz-step">
                            <span class="hp-mz-dot {{ ['live' => 'hp-mz-ok', 'draft' => 'hp-mz-wait', 'missing' => 'hp-mz-no'][$s['editorial']] }}" aria-hidden="true">{{ $s['editorial'] === 'live' ? '✓' : '২' }}</span>
                            <div>
                                <b>{{ __('সম্পাদকীয়') }}: {{ ['live' => __('প্রকাশিত'), 'draft' => __('খসড়া তৈরি; পড়ে, দরকারে বদলে প্রকাশ করুন'), 'missing' => __('এখনও লেখা হয়নি')][$s['editorial']] }}</b>
                                @if ($s['post'])<span class="hp-muted">{{ $s['post']->title }}</span>@endif
                                <div class="hp-mz-btns">
                                    @if ($s['post'])
                                        <x-filament::button size="sm" :color="$s['editorial'] === 'draft' ? 'danger' : 'gray'" tag="a" :href="\App\Filament\Resources\Posts\PostResource::getUrl('edit', ['record' => $s['post']->id])" icon="heroicon-o-pencil-square">{{ $s['editorial'] === 'draft' ? __('খসড়া খুলুন') : __('খুলুন') }}</x-filament::button>
                                    @elseif ($admin)
                                        <x-filament::button size="sm" icon="heroicon-o-pencil" wire:click="makeEditorial({{ $i->id }})">{{ __('এখনই খসড়া লেখান') }}</x-filament::button>
                                    @endif
                                </div>
                            </div>
                        </li>
                        {{-- 3. The magazine --}}
                        <li class="hp-mz-step">
                            <span class="hp-mz-dot {{ $i->status === 'published' && $s['editorial'] === 'live' ? 'hp-mz-ok' : 'hp-mz-wait' }}" aria-hidden="true">{{ $i->status === 'published' && $s['editorial'] === 'live' ? '✓' : '৩' }}</span>
                            <div>
                                <b>{{ __('পত্রিকা') }}: {{ $i->status === 'published' ? __('পাঠকেরা সাইটে পড়ছেন') : __('সংখ্যা অনুমোদনের পর সাইটে খুলবে') }}</b>
                                <div class="hp-mz-btns">
                                    @if ($i->status === 'published')
                                        <x-filament::button size="sm" color="gray" tag="a" :href="\App\Support\Url::to('/sonkha/'.$i->slug.'/boi')" target="_blank" icon="heroicon-o-book-open">{{ __('পত্রিকা দেখুন') }}</x-filament::button>
                                    @else
                                        {{-- Owner (2026-11-12): see the magazine before it is published --}}
                                        <x-filament::button size="sm" tag="a" :href="\App\Support\Url::to('/issue-preview/'.$i->id)" target="_blank" icon="heroicon-o-eye">{{ __('পত্রিকা কেমন দেখাবে') }}</x-filament::button>
                                        <x-filament::button size="sm" color="gray" tag="a" :href="\App\Filament\Resources\Issues\IssueResource::getUrl('edit', ['record' => $i->id])" icon="heroicon-o-document-text">{{ __('সংখ্যার প্রস্তাব দেখুন') }}</x-filament::button>
                                    @endif
                                    @if ($i->ebook_path)
                                        <x-filament::button size="sm" color="gray" icon="heroicon-o-arrow-down-tray" wire:click="download({{ $i->id }})">{{ __('ই-বুক (শুধু আপনার জন্য)') }}</x-filament::button>
                                    @endif
                                </div>
                                @if ($i->status === 'published' && $s['editorial'] !== 'live')<p class="hp-muted">{{ __('সম্পাদকীয় প্রকাশ হলেই ই-বুক তৈরি হবে; প্রতিটি ই-বুকে সম্পাদকীয় থাকে।') }}</p>@endif
                            </div>
                        </li>
                        {{-- 4. Tell readers and writers (owner, 2026-11-12): only once the issue is live, each once, on a click --}}
                        @if ($i->status === 'published')
                            @php($told = \App\Support\IssueNews::writersTold($i))
                            @php($announced = \App\Support\IssueNews::announced($i))
                            @php($nWriters = \App\Support\IssueNews::writers($i)->count())
                            <li class="hp-mz-step">
                                <span class="hp-mz-dot {{ $told && $announced ? 'hp-mz-ok' : 'hp-mz-wait' }}" aria-hidden="true">{{ $told && $announced ? '✓' : '৪' }}</span>
                                <div>
                                    <b>{{ __('খবর দিন') }}: {{ $told && $announced ? __('পাঠক আর লেখকেরা জেনেছেন') : __('পাঠকদের ফোনে আর লেখকদের জানান') }}</b>
                                    @if ($admin)
                                        <div class="hp-mz-btns">
                                            @if ($announced)
                                                <span class="hp-muted">✓ {{ __('পাঠকদের ফোনে পাঠানো হয়েছে') }}</span>
                                            @elseif (\App\Support\WebPush::configured())
                                                @php($msg = \App\Support\IssueNews::message($i))
                                                <x-filament::button size="sm" icon="heroicon-o-megaphone" wire:click="announce({{ $i->id }})"
                                                    wire:confirm="{{ __('সবার ফোনে যাবে') }} ({{ Bn::num((string) \App\Support\PushNotifier::audience('all')->count()) }}): “{{ $msg['title'] }}”, {{ $msg['body'] }}">{{ __('পাঠকদের ফোনে জানান') }}</x-filament::button>
                                            @endif
                                            @if ($told)
                                                <span class="hp-muted">✓ {{ __('লেখকদের জানানো হয়েছে') }}</span>
                                            @elseif ($nWriters)
                                                <x-filament::button size="sm" icon="heroicon-o-envelope" wire:click="tellWriters({{ $i->id }})"
                                                    wire:confirm="{{ Bn::num((string) $nWriters) }} {{ __('জন লেখক ই-মেল আর ফোনে জানবেন যে তাঁদের লেখা এই সংখ্যায় আছে। পাঠাবেন?') }}">{{ __('লেখকদের জানান') }} ({{ Bn::num((string) $nWriters) }})</x-filament::button>
                                            @else
                                                <span class="hp-muted">{{ __('জানানোর মতো লেখক নেই (অ্যাকাউন্ট নেই)') }}</span>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </li>
                        @endif
                    </ol>
                </div>
            </div>
        @empty
            <x-filament::section><p>{{ __('এই মাসের বা পরের মাসের সংখ্যা এখনও তৈরি হয়নি। ২০ তারিখে এআই সম্পাদক প্রস্তাব দেবেন।') }}</p></x-filament::section>
        @endforelse
    </div>

    @if ($past->isNotEmpty())
        <h3 style="font-weight:800;margin-top:.5rem">{{ __('আগের সংখ্যা') }}</h3>
        <div class="hp-mz-past">
            @foreach ($past as $i)
                <div>
                    <b>{{ $i->title }}</b>
                    <div class="hp-mz-btns">
                        <x-filament::button size="sm" color="gray" tag="a" :href="\App\Support\Url::to('/sonkha/'.$i->slug.'/boi')" target="_blank" icon="heroicon-o-book-open">{{ __('পত্রিকা দেখুন') }}</x-filament::button>
                        @if ($i->ebook_path)
                            <x-filament::button size="sm" color="gray" icon="heroicon-o-arrow-down-tray" wire:click="download({{ $i->id }})">EPUB</x-filament::button>
                        @else
                            <x-filament::button size="sm" color="gray" icon="heroicon-o-arrow-path" wire:click="rebuild({{ $i->id }})">{{ __('ই-বুক তৈরি করুন') }}</x-filament::button>
                        @endif
                    </div>
                    @if (! $i->liveEditorial())<p class="hp-muted">{{ __('সম্পাদকীয় নেই; ই-বুক হবে না') }}</p>@endif
                </div>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
