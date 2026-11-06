{{--
  আজকের কাজ (D36 + D42): one page for the owner/editor. The AI editor prepares, you approve.
  Inline styles + one scoped <style> (the desk only loads Filament's CSS).
--}}
@php
    use App\Support\Bn;
    use App\Ai\EditorPackage;
    $t = $this->today();
    $subs = $t['submissions'];
    $posts = \App\Filament\Resources\Posts\PostResource::class;
    $issues = \App\Filament\Resources\Issues\IssueResource::class;
    $words = fn ($p) => count(preg_split('/\s+/u', trim(strip_tags((string) $p->body_html)), -1, PREG_SPLIT_NO_EMPTY));
    $decision = ['publish' => ['প্রকাশযোগ্য', 'success'], 'changes' => ['বদল দরকার', 'warning'], 'decline' => ['এবার নয়', 'gray']];
    $approvals = $subs->count() + ($t['issue'] ? 1 : 0) + $t['editorials']->count() + ($t['images'] ? 1 : 0);
    $aiState = ! $t['aiEnabled'] ? ['বন্ধ', 'gray'] : ($t['aiPaused'] ? ['থামানো', 'warning'] : ['কাজ করছে', 'success']);
@endphp
<x-filament-panels::page>
    <style>
        .hp-dash { display: grid; gap: 1.25rem; grid-template-columns: minmax(0, 1fr); }
        @media (min-width: 1100px) { .hp-dash { grid-template-columns: minmax(0, 2fr) minmax(0, 1fr); } }
        .hp-stats { display: grid; gap: .75rem; grid-template-columns: repeat(auto-fit, minmax(10.5rem, 1fr)); }
        .hp-stat { display: block; border: 1px solid rgba(127,127,127,.22); border-radius: .9rem; padding: .9rem 1rem; background: rgba(127,127,127,.04); }
        .hp-stat:hover { border-color: rgba(127,127,127,.5); }
        .hp-stat b { display: block; font-size: 1.9rem; line-height: 1.2; }
        .hp-row { display: flex; flex-wrap: wrap; gap: .75rem; align-items: center; justify-content: space-between; padding: .8rem 0; border-bottom: 1px solid rgba(127,127,127,.18); }
        .hp-row:last-child { border-bottom: 0; }
        .hp-muted { font-size: .875rem; opacity: .72; }
        .hp-card { border: 1px solid rgba(127,127,127,.22); border-radius: .9rem; padding: 1rem; }
        .hp-col { display: grid; gap: 1.25rem; align-content: start; grid-template-columns: minmax(0, 1fr); min-width: 0; }
        .hp-col > * { min-width: 0; }
        .hp-day { display: flex; justify-content: space-between; gap: .75rem; padding: .45rem .1rem; border-bottom: 1px dashed rgba(127,127,127,.25); }
        .hp-day:last-child { border-bottom: 0; }
    </style>

    {{-- Greeting + the one-line status --}}
    <div>
        <div style="font-size:1.15rem">নমস্কার, <strong>{{ $t['name'] }}</strong>।</div>
        <div class="hp-muted" style="font-size:1rem;margin-top:.15rem">
            @if ($approvals + $t['commentsTotal'] === 0)
                আজ কিছু বাকি নেই। এআই সম্পাদক নতুন লেখা এলেই তৈরি করে রাখবে।
            @else
                এআই সম্পাদক সব তৈরি করে রেখেছে, এখন শুধু আপনার সিদ্ধান্ত।
            @endif
        </div>
    </div>

    {{-- Four numbers, each a link --}}
    <div class="hp-stats">
        <a class="hp-stat" href="#approvals"><span class="hp-muted">আপনার অনুমোদনের অপেক্ষায়</span><b>{{ Bn::num($approvals) }}</b></a>
        <a class="hp-stat" href="#comments"><span class="hp-muted">মন্তব্য</span><b>{{ Bn::num($t['commentsTotal']) }}</b></a>
        <a class="hp-stat" href="{{ $posts::getUrl('index', ['tab' => 'published']) }}"><span class="hp-muted">এই সপ্তাহে প্রকাশিত</span><b>{{ Bn::num($t['publishedWeek']) }}</b></a>
        <div class="hp-stat"><span class="hp-muted">এআই সম্পাদক</span><b style="font-size:1.25rem;margin-top:.45rem"><x-filament::badge :color="$aiState[1]" size="lg">{{ $aiState[0] }}</x-filament::badge></b></div>
    </div>

    <div class="hp-dash">
        {{-- LEFT: everything waiting for a decision --}}
        <div class="hp-col">
            <x-filament::section id="approvals" icon="heroicon-o-check-badge">
                <x-slot name="heading">আপনার অনুমোদনের অপেক্ষায়</x-slot>
                <x-slot name="description">খুলে দেখুন, দরকার হলে বদলান, তারপর এক ক্লিকে অনুমোদন।</x-slot>

                @if ($t['issue'])
                    <div class="hp-row">
                        <div style="min-width:0;flex:1 1 16rem">
                            <div style="font-weight:700">সংখ্যার প্রস্তাব: {{ $t['issue']->title }}</div>
                            <div class="hp-muted">{{ \App\Filament\Resources\Issues\Schemas\IssueForm::THEMES[$t['issue']->theme_key] ?? '' }} · {{ Bn::num(count((array) ($t['issue']->ai_plan['post_ids'] ?? []))) }}টি লেখা · প্রচ্ছদ ও সম্পাদকীয় খসড়া তৈরি</div>
                        </div>
                        <x-filament::button tag="a" :href="$issues::getUrl('edit', ['record' => $t['issue']])" icon="heroicon-o-book-open">সংখ্যা দেখুন</x-filament::button>
                    </div>
                @endif
                @foreach ($t['editorials'] as $e)
                    <div class="hp-row">
                        <div style="min-width:0;flex:1 1 16rem"><div style="font-weight:700">সম্পাদকীয় খসড়া: {{ $e->title }}</div><div class="hp-muted">আপনার নামে প্রকাশ হবে; পড়ে বদলে নিন</div></div>
                        <x-filament::button tag="a" :href="$this->editUrl($e)" icon="heroicon-o-pencil">পড়ুন ও প্রকাশ করুন</x-filament::button>
                    </div>
                @endforeach
                @if ($t['images'])
                    <div class="hp-row">
                        <div style="min-width:0;flex:1 1 16rem"><div style="font-weight:700">এআই-এর আঁকা {{ Bn::num($t['images']) }}টি প্রচ্ছদ</div><div class="hp-muted">ছবি ছাড়া পুরনো লেখার জন্য; ভালোগুলো একসঙ্গে বসান</div></div>
                        <x-filament::button tag="a" :href="\App\Filament\Pages\AiImages::getUrl()" icon="heroicon-o-photo" color="gray">ছবিগুলো দেখুন</x-filament::button>
                    </div>
                @endif
                @foreach ($subs as $p)
                    @php($r = EditorPackage::report($p))
                    <div class="hp-row" wire:key="s{{ $p->id }}">
                        @if ($img = $p->featured_image_path ?: $p->proposed_image_path)
                            <img src="{{ asset($img) }}" alt="" style="width:4.5rem;height:3rem;object-fit:cover;border-radius:.5rem;flex:none">
                        @endif
                        <div style="min-width:0;flex:1 1 14rem">
                            <div style="font-weight:700">{{ $p->title }}</div>
                            <div class="hp-muted">{{ $p->authors->first()?->publicName() ?? '—' }} · {{ $p->section?->name_bn }} · {{ Bn::num($words($p)) }} শব্দ · জমা {{ Bn::num((string) $p->submitted_at?->locale('bn')->diffForHumans()) }}</div>
                        </div>
                        <div style="display:flex;flex-wrap:wrap;gap:.4rem;align-items:center">
                            @if ($r)
                                @php($d = $decision[$r['decision'] ?? ''] ?? null)
                                @if ($d)<x-filament::badge :color="$d[1]">এআই: {{ $d[0] }}</x-filament::badge>@endif
                                <x-filament::badge color="gray">মান {{ Bn::num((string) $r['quality_score']) }}/১০</x-filament::badge>
                                @if (count($r['edits'] ?? []))<x-filament::badge color="info">{{ Bn::num(count($r['edits'])) }}টি সংশোধন</x-filament::badge>@endif
                            @else
                                <x-filament::badge color="gray">{{ $t['aiEnabled'] && ! $t['aiPaused'] ? 'এআই পড়ছে…' : 'এআই ছাড়া' }}</x-filament::badge>
                            @endif
                            <x-filament::button tag="a" :href="$this->editUrl($p)" icon="heroicon-o-book-open">দেখুন ও সিদ্ধান্ত</x-filament::button>
                        </div>
                    </div>
                @endforeach
                @if ($approvals === 0)
                    <p class="hp-muted">এখন অনুমোদনের কিছু নেই।</p>
                @endif
                @if ($t['backlogReady'] || $t['confirms'] || $t['withWriter'])
                    <div class="hp-muted" style="margin-top:.75rem;line-height:1.9">
                        @if ($t['backlogReady'])<div>ওয়ার্ডপ্রেসের পুরনো জমা: {{ Bn::num($t['backlogReady']) }}টি তৈরি ({{ Bn::num($t['backlog']) }}টির মধ্যে)। <a href="{{ $posts::getUrl('index', ['tab' => 'backlog']) }}" style="text-decoration:underline">সময় পেলে দেখুন</a></div>@endif
                        @if ($t['confirms'])<div>লেখকের সম্মতির অপেক্ষায়: {{ Bn::num($t['confirms']) }}টি</div>@endif
                        @if ($t['withWriter'])<div>বদল চেয়ে লেখকের কাছে: {{ Bn::num($t['withWriter']) }}টি</div>@endif
                    </div>
                @endif
            </x-filament::section>

            <x-filament::section id="comments" icon="heroicon-o-chat-bubble-left-right">
                <x-slot name="heading">পাঠকের মন্তব্য</x-slot>
                <x-slot name="description">এআই আগেই পড়ে রেখেছে। "ঠিক আছে" চিহ্নিতগুলো একসঙ্গে প্রকাশ করতে পারেন।</x-slot>
                @if ($t['commentsOk'])
                    <x-filament::button color="success" icon="heroicon-o-check" wire:click="approveAiOkComments" style="margin-bottom:.5rem">এআই যেগুলো ঠিক বলেছে ({{ Bn::num($t['commentsOk']) }}টি) সব প্রকাশ করুন</x-filament::button>
                @endif
                @forelse ($t['comments'] as $c)
                    <div class="hp-row" wire:key="c{{ $c->id }}">
                        <div style="min-width:0;flex:1 1 16rem">
                            <div class="hp-muted">{{ $c->author_name }} ({{ $c->user_id ? 'অ্যাকাউন্ট' : 'অতিথি' }}) · "{{ $c->post?->title }}"</div>
                            <div style="white-space:pre-line">{{ \Illuminate\Support\Str::limit($c->body, 400) }}</div>
                            @if ($c->ai_verdict)
                                <div style="margin-top:.3rem"><x-filament::badge :color="['ok' => 'success', 'spam' => 'danger', 'check' => 'warning'][$c->ai_verdict]">এআই: {{ ['ok' => 'ঠিক আছে', 'spam' => 'স্প্যাম মনে হচ্ছে', 'check' => 'দেখে নিন'][$c->ai_verdict] }}</x-filament::badge> <span class="hp-muted">{{ $c->ai_reason }}</span></div>
                            @endif
                        </div>
                        <div style="display:flex;gap:.5rem">
                            <x-filament::button color="success" icon="heroicon-o-check" wire:click="approveComment({{ $c->id }})">প্রকাশ</x-filament::button>
                            <x-filament::button color="gray" icon="heroicon-o-no-symbol" wire:click="spamComment({{ $c->id }})">স্প্যাম</x-filament::button>
                        </div>
                    </div>
                @empty
                    <p class="hp-muted">কোনো মন্তব্য অপেক্ষায় নেই।</p>
                @endforelse
                @if ($t['commentsTotal'] > $t['comments']->count())
                    <p style="margin-top:.5rem"><a href="{{ \App\Filament\Resources\Comments\CommentResource::getUrl('index') }}" style="text-decoration:underline">সব {{ Bn::num($t['commentsTotal']) }}টি মন্তব্য</a></p>
                @endif
            </x-filament::section>
        </div>

        {{-- RIGHT: the AI editor, the publishing calendar, the next issue --}}
        <div class="hp-col">
            <x-filament::section icon="heroicon-o-sparkles">
                <x-slot name="heading">এআই সম্পাদক</x-slot>
                <div style="line-height:1.9">
                    <div>অবস্থা: <x-filament::badge :color="$aiState[1]">{{ $aiState[0] }}</x-filament::badge></div>
                    <div class="hp-muted">সারাদিন কাজ করে: নতুন লেখা ও মন্তব্য (প্রতি ১০ মিনিটে), পুরনো লেখা ও ছবি (প্রতি ঘণ্টায়), পরের সংখ্যা (২০ তারিখ থেকে), আর সকাল ৮টায় আপনার জন্য সারাংশ। কিছুই নিজে প্রকাশ করে না।</div>
                    @php($b = $t['brief'])
                    <div style="margin-top:.4rem">গত ২৪ ঘণ্টায়: {{ Bn::num($b['prepared']) }}টি লেখা তৈরি · {{ Bn::num($b['pictures']) }}টি ছবি · {{ Bn::num($b['comments_screened']) }}টি মন্তব্য পড়েছে</div>
                    @if ($t['heartbeat'] && $t['heartbeat']->gt(now()->subMinutes(30)))
                        <div class="hp-muted">শেষ কাজ: {{ Bn::num((string) $t['heartbeat']->locale('bn')->diffForHumans()) }} · প্রতি ১০ মিনিটে নতুন কাজ দেখে</div>
                    @elseif ($t['isAdmin'])
                        <div style="margin-top:.4rem;padding:.6rem .8rem;border-radius:.6rem;border:1px solid #E39A6A">
                            <strong>এআই সম্পাদকের ঘড়ি চলছে না।</strong> নিয়মিত কাজের জন্য hPanel-এ একটি Cron লাইন যোগ করতে হবে (docs/DEPLOYMENT.md)। ততক্ষণ নতুন জমা এলে তখনই কাজ করে।
                        </div>
                    @endif
                    @if ($t['isAdmin'])
                        <div style="margin-top:.4rem">এই মাসের খরচ: <strong>{{ Bn::num(number_format($t['aiSpent'], 2)) }} ডলার</strong></div>
                        <div style="display:flex;flex-wrap:wrap;gap:.5rem;margin-top:.6rem">
                            @if ($t['aiPaused'])
                                <x-filament::button size="sm" color="success" icon="heroicon-o-play" wire:click="resumeAi">এআই চালু করুন</x-filament::button>
                            @else
                                <x-filament::button size="sm" color="danger" icon="heroicon-o-pause" wire:click="pauseAi" wire:confirm="এআই থামাবেন? নতুন কাজ আর খরচ বন্ধ থাকবে, বাকি সব আগের মতো চলবে।">এআই থামান</x-filament::button>
                            @endif
                            <x-filament::button size="sm" color="gray" tag="a" :href="\App\Filament\Pages\AiSettings::getUrl()">মানদণ্ড ও খরচ</x-filament::button>
                        </div>
                    @endif
                </div>
            </x-filament::section>

            @if ($t['isAdmin'])
                <x-filament::section icon="heroicon-o-book-open">
                    <x-slot name="heading">পরের সংখ্যা</x-slot>
                    @if ($t['issue'])
                        <p style="margin:0">প্রস্তাব তৈরি: <strong>{{ $t['issue']->title }}</strong>। "সংখ্যা দেখুন" চাপুন।</p>
                    @elseif ($t['issuePlanning'])
                        <p style="margin:0">এআই সম্পাদক {{ Bn::num($t['nextMonth']->locale('bn')->isoFormat('MMMM')) }} মাসের সংখ্যা সাজাচ্ছে… এক-দুই মিনিট পরে পাতাটি নতুন করে খুলুন।</p>
                    @elseif ($t['issueDue'])
                        <p style="margin:0 0 .6rem">{{ Bn::num($t['nextMonth']->locale('bn')->isoFormat('MMMM YYYY')) }}-এর সংখ্যা এখনও তৈরি হয়নি। প্রতি মাসের ২০ তারিখে এআই নিজে থেকেই প্রস্তাব তৈরি করে; চাইলে এখনই:</p>
                        <x-filament::button size="sm" icon="heroicon-o-sparkles" wire:click="proposeIssue">সংখ্যার প্রস্তাব তৈরি করুন</x-filament::button>
                    @else
                        <p style="margin:0">{{ Bn::num($t['nextMonth']->locale('bn')->isoFormat('MMMM')) }} মাসের সংখ্যা তৈরি হয়ে গেছে। <a href="{{ $issues::getUrl('index') }}" style="text-decoration:underline">সব সংখ্যা</a></p>
                    @endif
                </x-filament::section>
            @endif

            <x-filament::section icon="heroicon-o-calendar-days">
                <x-slot name="heading">প্রকাশের সূচি (দুই সপ্তাহ)</x-slot>
                <x-slot name="description">সোম/বুধ/শুক্র সকাল ৯টা নিয়মিত দিন। খালি দিনে নতুন লেখা বসবে।</x-slot>
                @foreach ($t['week']->filter(fn ($d) => $d['slot'] || $d['posts']->isNotEmpty()) as $d)
                    <div class="hp-day">
                        <span style="font-weight:700;white-space:nowrap">{{ Bn::num($d['day']->locale('bn')->isoFormat('ddd, D MMM')) }}</span>
                        @if ($d['posts']->isNotEmpty())
                            <span style="text-align:right;min-width:0">{{ $d['posts']->implode(' · ') }}</span>
                        @else
                            <span class="hp-muted">খালি</span>
                        @endif
                    </div>
                @endforeach
            </x-filament::section>

            @if ($t['isAdmin'] && $t['auditOpen'])
                <div class="hp-card hp-muted">পুরনো লেখার অডিট সুপারিশ, সিদ্ধান্ত বাকি: {{ Bn::num($t['auditOpen']) }}টি</div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
