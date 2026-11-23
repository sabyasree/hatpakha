{{-- The editorials (owner, 2026-11-12): every issue with its editorial and the one next step. Logo palette only. --}}
@php
    use App\Filament\Resources\Posts\PostResource;
    use App\Support\Bn;
    $admin = auth()->user()->hasRole(\App\Enums\Role::Admin);
@endphp
<x-filament-panels::page>
    <style>
        .hp-ed { display: grid; gap: .6rem; }
        .hp-ed-row { display: grid; grid-template-columns: minmax(10rem, 14rem) 1fr auto; gap: .9rem; align-items: center; border: 1px solid rgba(127,127,127,.25); border-radius: .9rem; padding: .8rem 1rem; }
        @media (max-width: 760px) { .hp-ed-row { grid-template-columns: 1fr; } }
        .hp-ed-issue b { display: block; }
        .hp-ed-chip { display: inline-block; font-size: .75rem; font-weight: 700; padding: .05rem .55rem; border-radius: 999px; margin-top: .2rem; }
        .hp-ed-live { background: #13212C; color: #FFFBF4; } .hp-ed-draft { background: #EFC08D; color: #13212C; } .hp-ed-none { background: #CB1B32; color: #fff; } .hp-ed-scheduled { background: #DDF1FA; color: #13212C; border: 1px solid #2DAAE2; }
        .hp-ed-btns { display: flex; flex-wrap: wrap; gap: .4rem; justify-content: flex-end; }
        .hp-muted { font-size: .85rem; opacity: .75; }
    </style>

    <x-filament::section>
        <p>{{ __('প্রতিটি সংখ্যার শুরুতে থাকে সম্পাদকীয়। এখানে দেখুন কোনটি লেখা হয়েছে, কোনটি খসড়া, কোনটি বাকি। নিজে লিখতে পারেন, বা এআই সম্পাদককে খসড়া লিখতে বলে নিজে বদলে প্রকাশ করতে পারেন। প্রকাশ না করা পর্যন্ত পাঠকেরা দেখতে পান না।') }}</p>
    </x-filament::section>

    <div class="hp-ed">
        @forelse ($issues as $i)
            @php($post = $i->editorialPost())
            @php($state = ! $post ? 'none' : $post->publishState())
            <div class="hp-ed-row">
                <div class="hp-ed-issue">
                    <b>{{ $i->title }}</b>
                    <span class="hp-muted">{{ $i->slug }} · {{ $i->status === 'published' ? __('প্রকাশিত সংখ্যা') : __('আসছে') }}</span>
                </div>
                <div>
                    <span class="hp-ed-chip hp-ed-{{ $state }}">{{ $state === 'scheduled' ? $post->goesOutText() : ['live' => __('সম্পাদকীয় প্রকাশিত'), 'draft' => __('খসড়া'), 'none' => __('এখনও লেখা হয়নি')][$state] }}</span>
                    @if ($post)
                        <div style="font-weight:700;margin-top:.2rem">{{ $post->title }}</div>
                        <div class="hp-muted">{{ $post->authors->map->publicName()->implode(', ') }}@if ($post->published_at && $state === 'live') · {{ Bn::date($post->published_at) }}@endif</div>
                    @endif
                </div>
                <div class="hp-ed-btns">
                    @if ($post)
                        <x-filament::button size="sm" :color="$state === 'draft' ? 'danger' : 'gray'" tag="a" :href="PostResource::getUrl('edit', ['record' => $post])" icon="heroicon-o-pencil-square">{{ $state === 'draft' ? __('খসড়া খুলুন') : __('খুলুন') }}</x-filament::button>
                        @if ($state === 'live')<x-filament::button size="sm" color="gray" tag="a" :href="$post->url()" target="_blank" icon="heroicon-o-eye">{{ __('সাইটে দেখুন') }}</x-filament::button>@endif
                        {{-- D247: the AI's draft can be sent back with notes; the new version waits on the editorial's page --}}
                        @if (\App\Ai\Rewriter::isEditorial($post))<x-filament::button size="sm" color="warning" tag="a" :href="PostResource::getUrl('edit', ['record' => $post]).'?action=humanFeedback'" icon="heroicon-o-chat-bubble-left-right">{{ __('আবার লেখান') }}</x-filament::button>@endif
                    @else
                        <x-filament::button size="sm" icon="heroicon-o-pencil" wire:click="write({{ $i->id }})">{{ __('নিজে লিখুন') }}</x-filament::button>
                        @if ($admin)<x-filament::button size="sm" color="gray" icon="heroicon-o-sparkles" wire:click="draft({{ $i->id }})">{{ __('এআই খসড়া লিখুক') }}</x-filament::button>@endif
                    @endif
                </div>
            </div>
        @empty
            <x-filament::section><p>{{ __('এখনও কোনো সংখ্যা নেই।') }}</p></x-filament::section>
        @endforelse
    </div>

    @if ($others->isNotEmpty())
        <h3 style="font-weight:800;margin-top:.5rem">{{ __('অন্য সম্পাদকীয় (কোনো সংখ্যার সঙ্গে যুক্ত নয়)') }}</h3>
        <p class="hp-muted">{{ __('কোনো আসন্ন সংখ্যার সম্পাদকীয় করতে পাশের বোতাম টিপুন।') }}</p>
        <div class="hp-ed">
            @foreach ($others as $post)
                <div class="hp-ed-row">
                    <div class="hp-ed-issue"><b>{{ $post->title }}</b><span class="hp-muted">{{ $post->issue?->title ?? __('কোনো সংখ্যা নেই') }}</span></div>
                    <div><span class="hp-ed-chip hp-ed-{{ $post->publishState() }}">{{ ['live' => __('প্রকাশিত'), 'scheduled' => $post->goesOutText(), 'draft' => __('খসড়া')][$post->publishState()] }}</span></div>
                    <div class="hp-ed-btns"><x-filament::button size="sm" color="gray" tag="a" :href="PostResource::getUrl('edit', ['record' => $post])" icon="heroicon-o-pencil-square">{{ __('খুলুন') }}</x-filament::button>
                        {{-- D270: one click makes it an upcoming issue's editorial (the issue's earlier choice stays in this list) --}}
                        @foreach ($upcoming as $u)
                            <x-filament::button size="sm" color="primary" icon="heroicon-o-link" wire:click="useFor({{ $post->id }}, {{ $u->id }})" wire:confirm="{{ __('এটিকে এই সংখ্যার সম্পাদকীয় করবেন?') }}">{{ $u->title }}-{{ __('এর সম্পাদকীয় করুন') }}</x-filament::button>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
