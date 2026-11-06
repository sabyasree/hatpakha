@php
    use App\Support\Bn;
    $t = $this->today();
    $subs = $t['submissions'];
    $todo = $subs->count() + $t['commentsTotal'];
    $posts = \App\Filament\Resources\Posts\PostResource::class;
    $words = fn ($p) => count(preg_split('/\s+/u', trim(strip_tags((string) $p->body_html)), -1, PREG_SPLIT_NO_EMPTY));
@endphp
<x-filament-panels::page>
    {{-- One sentence: what is waiting. --}}
    <div style="font-size:1.125rem;line-height:1.6">
        @if ($todo === 0)
            <strong>আজ কোনো কাজ বাকি নেই।</strong> নতুন লেখা বা মন্তব্য এলে এখানেই দেখা যাবে।
        @else
            @php($parts = array_filter([$subs->count() ? Bn::num($subs->count()).'টি নতুন লেখা' : null, $t['commentsTotal'] ? Bn::num($t['commentsTotal']).'টি মন্তব্য' : null]))
            আপনার জন্য অপেক্ষায়: <strong>{{ implode(' আর ', $parts) }}</strong>। পুরনোটি আগে দেওয়া আছে।
        @endif
    </div>

    {{-- 1. New submissions: read, then decide on the same page. --}}
    <x-filament::section icon="heroicon-o-document-text">
        <x-slot name="heading">১. নতুন লেখা পড়ুন</x-slot>
        <x-slot name="description">"পড়ুন ও সিদ্ধান্ত নিন" চাপলে লেখাটি খুলবে; উপরে মনোনীত / বদল চাই / বাতিল বোতাম থাকবে।</x-slot>
        @forelse ($subs as $p)
            <div style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:center;justify-content:space-between;padding:.75rem 0;{{ $loop->last ? '' : 'border-bottom:1px solid rgba(127,127,127,.2)' }}">
                <div style="min-width:0;flex:1 1 18rem">
                    <div style="font-weight:700;font-size:1.05rem">{{ $p->title }}</div>
                    <div style="font-size:.875rem;opacity:.75">
                        {{ $p->authors->first()?->publicName() ?? '—' }}
                        · {{ $p->section?->name_bn ?? 'বিভাগ নেই' }}
                        · {{ Bn::num($words($p)) }} শব্দ
                        · জমা {{ Bn::num((string) $p->submitted_at?->locale('bn')->diffForHumans()) }}
                    </div>
                </div>
                <div style="display:flex;gap:.5rem;align-items:center">
                    @if ($p->latestAiReview?->score !== null)
                        <x-filament::badge :color="$p->latestAiReview->score >= 7 ? 'success' : ($p->latestAiReview->score >= 5 ? 'warning' : 'gray')">
                            এআই {{ Bn::num($p->latestAiReview->score) }}/১০
                        </x-filament::badge>
                    @endif
                    <x-filament::button tag="a" :href="$this->editUrl($p)" icon="heroicon-o-book-open">পড়ুন ও সিদ্ধান্ত নিন</x-filament::button>
                </div>
            </div>
        @empty
            <p style="opacity:.75">কোনো নতুন লেখা পড়া বাকি নেই।</p>
        @endforelse
    </x-filament::section>

    {{-- 2. Comments: approve or spam right here. --}}
    <x-filament::section icon="heroicon-o-chat-bubble-left-right">
        <x-slot name="heading">২. পাঠকের মন্তব্য</x-slot>
        <x-slot name="description">ঠিক থাকলে "প্রকাশ", বিজ্ঞাপন বা আজেবাজে হলে "স্প্যাম"। কিছুই মুছে যায় না।</x-slot>
        @forelse ($t['comments'] as $c)
            <div wire:key="c{{ $c->id }}" style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:center;justify-content:space-between;padding:.75rem 0;{{ $loop->last ? '' : 'border-bottom:1px solid rgba(127,127,127,.2)' }}">
                <div style="min-width:0;flex:1 1 18rem">
                    <div style="font-size:.875rem;opacity:.75">{{ $c->author_name }} ({{ $c->user_id ? 'অ্যাকাউন্ট' : 'অতিথি' }}) · "{{ $c->post?->title }}"</div>
                    <div style="white-space:pre-line">{{ \Illuminate\Support\Str::limit($c->body, 400) }}</div>
                </div>
                <div style="display:flex;gap:.5rem">
                    <x-filament::button color="success" icon="heroicon-o-check" wire:click="approveComment({{ $c->id }})">প্রকাশ</x-filament::button>
                    <x-filament::button color="gray" icon="heroicon-o-no-symbol" wire:click="spamComment({{ $c->id }})">স্প্যাম</x-filament::button>
                </div>
            </div>
        @empty
            <p style="opacity:.75">কোনো মন্তব্য অপেক্ষায় নেই।</p>
        @endforelse
        @if ($t['commentsTotal'] > $t['comments']->count())
            <p style="margin-top:.5rem"><a href="{{ \App\Filament\Resources\Comments\CommentResource::getUrl('index') }}" style="text-decoration:underline">সব {{ Bn::num($t['commentsTotal']) }}টি মন্তব্য দেখুন</a></p>
        @endif
    </x-filament::section>

    {{-- 3. Just to know: nothing to do here. --}}
    <x-filament::section icon="heroicon-o-information-circle" collapsible>
        <x-slot name="heading">জানার জন্য (কিছু করতে হবে না)</x-slot>
        <ul style="list-style:disc;padding-inline-start:1.25rem;line-height:1.9">
            <li>এই সপ্তাহে প্রকাশিত: {{ Bn::num($t['publishedWeek']) }}টি</li>
            <li>বদল চেয়ে লেখকের কাছে ফেরত: {{ Bn::num($t['withWriter']) }}টি। লেখক আবার জমা দিলে উপরের তালিকায় আসবে।</li>
            @foreach ($t['scheduled'] as $s)
                <li>নির্ধারিত: "{{ $s->title }}" প্রকাশ হবে {{ Bn::num($s->published_at->locale('bn')->isoFormat('D MMMM, h:mm A')) }}</li>
            @endforeach
            @if ($t['backlog'])
                <li>ওয়ার্ডপ্রেসের পুরনো অপেক্ষার লেখা: {{ Bn::num($t['backlog']) }}টি। তাড়া নেই, সময় পেলে <a href="{{ $posts::getUrl('index', ['tab' => 'backlog']) }}" style="text-decoration:underline">এখানে</a>।</li>
            @endif
        </ul>
    </x-filament::section>

    @if ($t['isAdmin'])
        <x-filament::section icon="heroicon-o-shield-check" collapsible collapsed>
            <x-slot name="heading">অ্যাডমিন</x-slot>
            <ul style="list-style:disc;padding-inline-start:1.25rem;line-height:1.9">
                <li style="display:flex;flex-wrap:wrap;gap:.5rem;align-items:center">
                    এআই: <strong>{{ ! $t['aiEnabled'] ? 'বন্ধ' : ($t['aiPaused'] ? 'থামানো' : 'চালু') }}</strong>
                    · এই মাসের খরচ {{ \App\Support\Bn::num(number_format($t['aiSpent'], 2)) }} ডলার
                    @if ($t['aiPaused'])
                        <x-filament::button size="sm" color="success" icon="heroicon-o-play" wire:click="resumeAi">এআই চালু করুন</x-filament::button>
                    @else
                        <x-filament::button size="sm" color="danger" icon="heroicon-o-pause" wire:click="pauseAi" wire:confirm="এআই থামাবেন? নতুন রিপোর্ট আর খরচ বন্ধ থাকবে, বাকি সব আগের মতো চলবে।">এআই থামান</x-filament::button>
                    @endif
                    <a href="{{ \App\Filament\Pages\AiSettings::getUrl() }}" style="text-decoration:underline">এআই সহকারী</a>
                </li>
                <li>অডিট সুপারিশ, সিদ্ধান্ত বাকি: {{ Bn::num($t['auditOpen']) }}টি</li>
                <li><a href="{{ \App\Filament\Resources\Users\UserResource::getUrl('index') }}" style="text-decoration:underline">কারা ডেস্ক খুলতে পারেন</a></li>
            </ul>
        </x-filament::section>
    @endif
</x-filament-panels::page>
