{{--
  আজকের কাজ (D36, D42; D279 owner: "as admin I should not spend time finding where things are"; three UX reviews):
  one inbox. Every waiting thing is one line (App\Support\DeskInbox): what it is, who, when, and one button that opens
  exactly that item. Most urgent first: আজই, এই সপ্তাহে, জেনে রাখুন. The menu's numbers count the same things.
  The AI editor's state, the next issue, the calendar and the editor's pick are below, folded.
--}}
@php
    use App\Support\Bn;
    use App\Support\DeskInbox;
    $t = $this->today();
    $items = DeskInbox::items();
    $counts = DeskInbox::counts($items);
    $issues = \App\Filament\Resources\Issues\IssueResource::class;
    $aiState = ! $t['aiEnabled'] ? [__('বন্ধ'), 'gray'] : ($t['aiPaused'] ? [__('থামানো'), 'warning'] : [__('কাজ করছে'), 'success']);
    $tasks = \App\Models\EditorTask::with('subject')->whereIn('id', $items->pluck('action')->filter(fn ($a) => ($a[0] ?? null) === 'task')->map(fn ($a) => $a[1]))->get()->keyBy('id');
@endphp
<x-filament-panels::page>
    <style>
        .hp-in-counts { display: flex; flex-wrap: wrap; gap: .5rem; }
        .hp-in-counts a { border-radius: 999px; padding: .45rem 1.1rem; font-weight: 700; text-decoration: none; }
        .hp-in-box { border: 1px solid rgba(127,127,127,.25); border-radius: 1rem; overflow: hidden; background: rgba(127,127,127,.03); }
        .hp-in-box.today { border-left: 5px solid #CB1B32; }
        .hp-in-box > h2 { font-size: .95rem; font-weight: 800; padding: .7rem 1.1rem; border-bottom: 1px solid rgba(127,127,127,.18); margin: 0; }
        .hp-in-box.today > h2 { color: #CB1B32; }
        .hp-in-row { display: flex; flex-wrap: wrap; gap: .5rem 1rem; align-items: center; padding: .8rem 1.1rem; border-bottom: 1px solid rgba(127,127,127,.14); }
        .hp-in-row:last-child { border-bottom: 0; }
        .hp-in-tag { font-size: .78rem; font-weight: 700; padding: .1rem .55rem; border-radius: .4rem; color: #13212C; white-space: nowrap; }
        .hp-in-text { flex: 1 1 18rem; min-width: 0; }
        .hp-in-text b { font-weight: 700; }
        .hp-in-sub { font-size: .875rem; opacity: .75; margin-top: .1rem; }
        .hp-in-due { display: inline-block; font-size: .78rem; font-weight: 700; padding: .05rem .5rem; border-radius: .4rem; margin-left: .35rem; background: #DDF1FA; color: #13212C; }
        .hp-in-due.soon { background: #F08D90; }
        .hp-in-act { display: flex; flex-wrap: wrap; gap: .4rem; align-items: center; }
        .hp-in-fyi { background: rgba(239,192,141,.14); }
        .hp-in-empty { text-align: center; padding: 2rem 1rem; }
        .hp-row { display: flex; flex-wrap: wrap; gap: .75rem; align-items: center; justify-content: space-between; padding: .8rem 0; border-bottom: 1px solid rgba(127,127,127,.18); }
        .hp-row:last-child { border-bottom: 0; }
        .hp-muted { font-size: .875rem; opacity: .72; }
        .hp-day { display: flex; justify-content: space-between; gap: .75rem; padding: .45rem .1rem; border-bottom: 1px dashed rgba(127,127,127,.25); }
        .hp-day:last-child { border-bottom: 0; }
        .hp-state { display: grid; gap: 1.25rem; grid-template-columns: minmax(0, 1fr); }
        @media (min-width: 1100px) { .hp-state { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    </style>

    <div>
        <div style="font-size:1.15rem">{{ __('নমস্কার,') }} <strong>{{ $t['name'] }}</strong>{{ __('।') }}</div>
        <div class="hp-muted" style="font-size:1rem;margin-top:.15rem">{{ __('যা আপনার একটি ক্লিকের অপেক্ষায়, জরুরি আগে। প্রতিটি লাইনের বোতাম ঠিক সেই জিনিসটি খোলে।') }}</div>
    </div>

    <div class="hp-in-counts">
        <a href="#in-today" style="background:{{ $counts['today'] ? '#CB1B32' : '#DDF1FA' }};color:{{ $counts['today'] ? '#fff' : '#13212C' }}">{{ __('আজই') }} {{ Bn::num((string) $counts['today']) }}</a>
        <a href="#in-week" style="background:#13212C;color:#fff">{{ __('এই সপ্তাহে') }} {{ Bn::num((string) $counts['week']) }}</a>
        <a href="#in-fyi" style="background:#FBEBD8;color:#13212C">{{ __('জেনে রাখুন') }} {{ Bn::num((string) $counts['fyi']) }}</a>
    </div>

    @if ($items->isEmpty())
        <div class="hp-in-box hp-in-empty">
            <div style="font-size:1.25rem;font-weight:700">{{ __('আজ কিছু বাকি নেই।') }}</div>
            <div class="hp-muted">{{ __('নতুন লেখা, প্রস্তাব, পর্ব, মন্তব্য বা প্রশ্ন এলে এখানেই দেখাবে।') }}</div>
        </div>
    @endif

    @foreach (DeskInbox::GROUPS as $g => $label)
        @php($rows = $items->where('group', $g))
        @continue($rows->isEmpty())
        <section id="in-{{ $g }}" class="hp-in-box {{ $g }} {{ $g === 'fyi' ? 'hp-in-fyi' : '' }}">
            <h2>{{ __($label) }} · {{ Bn::num((string) $rows->count()) }}</h2>
            @foreach ($rows as $r)
                @php($tag = DeskInbox::TAGS[$r['tag']] ?? DeskInbox::TAGS['ai'])
                <div class="hp-in-row" wire:key="in-{{ $r['key'] }}">
                    <span class="hp-in-tag" style="background:{{ $tag[1] }}">{{ __($tag[0]) }}</span>
                    <div class="hp-in-text">
                        <div><b>{{ $r['title'] }}</b>@if ($r['who'] !== '') · {{ $r['who'] }}@endif @if ($r['due'])<span class="hp-in-due {{ $r['soon'] ? 'soon' : '' }}">{{ $r['due'] }}</span>@endif</div>
                        @if ($r['sub'])<div class="hp-in-sub">{{ $r['sub'] }}</div>@endif
                    </div>
                    <div class="hp-in-act">
                        @php($a = $r['action'])
                        @if (! $a)
                            <x-filament::button tag="a" :href="$r['url']">{{ $r['button'] }}</x-filament::button>
                        @elseif ($a[0] === 'approveAiOkComments')
                            <x-filament::button wire:click="approveAiOkComments">{{ $r['button'] }}</x-filament::button>
                            <x-filament::button tag="a" color="gray" outlined :href="$r['url']">{{ __('এক এক করে') }}</x-filament::button>
                        @elseif ($a[0] === 'comment')
                            <x-filament::button wire:click="approveComment({{ $a[1] }})">{{ __('প্রকাশ') }}</x-filament::button>
                            <x-filament::button color="gray" outlined wire:click="spamComment({{ $a[1] }})">{{ __('স্প্যাম') }}</x-filament::button>
                        @elseif ($a[0] === 'replyQuestion')
                            {{ ($this->replyQuestionAction)(['task' => $a[1]]) }}
                        @elseif ($a[0] === 'task' && ($task = $tasks[$a[1]] ?? null))
                                    @if (in_array($task->type, ['followup', 'welcome'], true)) {{ ($this->sendNoteAction)(['task' => $task->id]) }} @endif
                                    @if ($task->type === 'pick') {{ ($this->approvePickAction)(['task' => $task->id]) }} @endif
                                    @if ($task->type === 'newsletter' && $task->subject) <x-filament::button tag="a" size="sm" icon="heroicon-o-envelope" :href="\App\Filament\Resources\Newsletters\NewsletterResource::getUrl('edit', ['record' => $task->subject])">{{ __('দেখুন ও পাঠান') }}</x-filament::button> @endif
                                    @if ($task->type === 'audio' && $task->subject) <x-filament::button tag="a" size="sm" icon="heroicon-o-speaker-wave" :href="\App\Filament\Resources\Posts\PostResource::getUrl('edit', ['record' => $task->subject])">{{ __('শুনুন ও অনুমোদন দিন') }}</x-filament::button> @endif
                                    @if ($task->type === 'kolom') <x-filament::button tag="a" size="sm" icon="heroicon-o-megaphone" :href="\App\Filament\Resources\ReaderEntries\ReaderEntryResource::getUrl('index')">{{ __('দেখে প্রকাশ করুন') }}</x-filament::button> @endif
                                    @if ($task->type === 'honour') {{ ($this->honourAction)(['task' => $task->id]) }} @endif
                                    @if ($task->type === 'certificates') {{ ($this->issueCertificatesAction)(['task' => $task->id]) }} @endif
                                    @if ($task->type === 'certificate_request') <x-filament::button tag="a" size="sm" icon="heroicon-o-academic-cap" :href="\App\Filament\Pages\Certificates::getUrl().'#requests'">{{ __('দেখে শংসাপত্র দিন') }}</x-filament::button> @endif
                                    @if ($task->type === 'collection') {{ ($this->approveCollectionAction)(['task' => $task->id]) }}
                                        @if ($task->subject) <x-filament::button tag="a" size="sm" color="gray" icon="heroicon-o-arrows-up-down" :href="\App\Filament\Resources\Collections\CollectionResource::getUrl('edit', ['record' => $task->subject])">{{ __('সাজিয়ে দেখুন') }}</x-filament::button> @endif
                                    @endif
                                    @if (in_array($task->type, ['serial', 'serial_episode'], true)) <x-filament::button tag="a" size="sm" icon="heroicon-o-book-open" :href="$task->subject instanceof \App\Models\Serial ? \App\Filament\Resources\Serials\SerialResource::getUrl('edit', ['record' => $task->subject]) : ($task->subject instanceof \App\Models\Post ? \App\Filament\Resources\Posts\PostResource::getUrl('edit', ['record' => $task->subject]) : \App\Filament\Resources\Serials\SerialResource::getUrl())">{{ __('ধারাবাহিক দেখুন') }}</x-filament::button> @endif
                                    @if ($task->type === 'kids_alphabet') <x-filament::button tag="a" size="sm" icon="heroicon-o-face-smile" :href="\App\Filament\Pages\KidsCorner::getUrl()">{{ __('দেখে অনুমোদন দিন') }}</x-filament::button> @endif
                                    @if ($task->type === 'collection_add' && $task->subject) <x-filament::button tag="a" size="sm" icon="heroicon-o-plus" :href="\App\Filament\Resources\Collections\CollectionResource::getUrl()">{{ __('দেখে যোগ করুন') }}</x-filament::button> @endif
                                    @if ($task->type === 'tags') {{ ($this->mergeTagsAction)(['task' => $task->id]) }} @endif
                                    @if ($task->type === 'question') {{ ($this->replyQuestionAction)(['task' => $task->id]) }} @endif
                                    @if (in_array($task->type, ['report', 'health', 'alert', 'quality', 'plagiarism', 'insights', 'seo_refresh', 'team_review', 'strategy'], true)) {{ ($this->readTaskAction)(['task' => $task->id]) }} @endif
                                    {{ ($this->dismissTaskAction)(['task' => $task->id]) }}
                        @endif
                    </div>
                </div>
            @endforeach
        </section>
    @endforeach

    {{-- The rest, folded: the editor's pick, answered questions, the AI editor, the next issue, the calendar --}}
    <x-filament::section icon="heroicon-o-adjustments-horizontal" collapsible collapsed persist-collapsed collapse-id="hp-today-state">
        <x-slot name="heading">{{ __('অবস্থা: এআই, পরের সংখ্যা, প্রকাশের সূচি') }}</x-slot>
        <x-slot name="description">{{ __('এআই') }}: {{ $aiState[0] }} · {{ __('এই সপ্তাহে প্রকাশিত') }} {{ Bn::num((string) $t['publishedWeek']) }}</x-slot>
        <div class="hp-state">
            @if (auth()->user()?->hasRole(\App\Enums\Role::Admin))
                {{-- Owner (2026-11-16): the editor's pick is always in sight and can be changed, whatever the AI proposed --}}
                @php($nowPick = $this->currentPick())
                <x-filament::section icon="heroicon-o-star" compact>
                    <x-slot name="heading">{{ __('প্রথম পাতায় সম্পাদকের পছন্দ') }}</x-slot>
                    <div style="display:flex;flex-wrap:wrap;gap:.6rem;align-items:center;justify-content:space-between">
                        <span>@if ($nowPick['post'])<a href="{{ $nowPick['post']->url() }}" target="_blank" rel="noopener" style="font-weight:700;text-decoration:underline">{{ $nowPick['post']->title }}</a>
                            <span style="opacity:.75"> — {{ $nowPick['post']->authors->first()?->publicName() }}</span>
                            @if ($nowPick['note'])<div style="opacity:.8">{{ $nowPick['note'] }}</div>@endif
                            @else<span style="opacity:.75">{{ __('এখন কোনো লেখা বসানো নেই।') }}</span>@endif</span>
                        <span style="display:flex;gap:.4rem">{{ $this->changePickAction }}
                            @if ($nowPick['post'])<x-filament::button size="sm" color="gray" outlined wire:click="removePick" wire:confirm="{{ __('প্রথম পাতা থেকে সম্পাদকের পছন্দ সরাবেন?') }}">{{ __('সরান') }}</x-filament::button>@endif</span>
                    </div>
                </x-filament::section>
            @endif
            @php($answered = $this->answeredQuestions())
            @if ($answered->isNotEmpty())
                {{-- D224: questions already answered stay in sight, with what was said --}}
                <x-filament::section icon="heroicon-o-chat-bubble-left-right" collapsible collapsed persist-collapsed collapse-id="hp-today-qa">
                    <x-slot name="heading">{{ __('লেখকদের প্রশ্ন ও আপনার উত্তর') }} ({{ Bn::num((string) $answered->count()) }})</x-slot>
                    <x-slot name="description">{{ __('উত্তরের ই-মেলের হুবহু কপি আছে "পাঠানো চিঠি"-তে।') }}</x-slot>
                    @foreach ($answered as $qa)
                        <div class="hp-row" style="display:block">
                            <div style="display:flex;flex-wrap:wrap;gap:.5rem;justify-content:space-between;align-items:center">
                                <strong>{{ $qa->payload['name'] ?? $qa->subject?->name }}</strong>
                                <span class="hp-muted">{{ $qa->payload['email'] ?? '' }} · {{ Bn::num(($qa->done_at ?? $qa->updated_at)->copy()->timezone('Asia/Kolkata')->format('d/m, H:i')) }}</span>
                            </div>
                            <div style="margin-top:.3rem"><span class="hp-muted">{{ __('প্রশ্ন:') }}</span> {{ $qa->payload['question'] ?? '' }}</div>
                            <div style="margin-top:.3rem;white-space:pre-line"><span class="hp-muted">{{ __('উত্তর:') }}</span> {{ $qa->payload['reply'] ?? __('(এই উত্তরটি তখন রাখা হয়নি; "পাঠানো চিঠি"-তে দেখুন)') }}</div>
                            <div style="margin-top:.4rem">{{ ($this->replyAgainAction)(['task' => $qa->id]) }}</div>
                        </div>
                    @endforeach
                </x-filament::section>
            @endif
            <x-filament::section icon="heroicon-o-sparkles" collapsible persist-collapsed collapse-id="hp-today-4">
                <x-slot name="heading">{{ __('এআই সম্পাদক') }}</x-slot>
                <div style="line-height:1.9">
                    <div>{{ __('অবস্থা:') }} <x-filament::badge :color="$aiState[1]">{{ $aiState[0] }}</x-filament::badge></div>
                    <div class="hp-muted">{{ __('সারাদিন কাজ করে: নতুন লেখা ও মন্তব্য (প্রতি ১০ মিনিটে), পুরনো লেখা ও ছবি (প্রতি ঘণ্টায়), পরের সংখ্যা (২০ তারিখ থেকে), আর সকাল ৮টায় আপনার জন্য সারাংশ। কিছুই নিজে প্রকাশ করে না।') }}</div>
                    @php($b = $t['brief'])
                    <div style="margin-top:.4rem">{{ __('গত ২৪ ঘণ্টায়:') }} {{ Bn::num($b['prepared']) }}{{ __('টি লেখা তৈরি ·') }} {{ Bn::num($b['pictures']) }}{{ __('টি ছবি ·') }} {{ Bn::num($b['comments_screened']) }}{{ __('টি মন্তব্য পড়েছে') }}</div>
                    @if ($t['heartbeat'] && $t['heartbeat']->gt(now()->subMinutes(30)))
                        <div class="hp-muted">{{ __('শেষ কাজ:') }} {{ Bn::num((string) $t['heartbeat']->locale(app()->getLocale())->diffForHumans()) }} {{ __('· প্রতি ১০ মিনিটে নতুন কাজ দেখে') }}</div>
                    @elseif ($t['isAdmin'])
                        <div style="margin-top:.4rem;padding:.6rem .8rem;border-radius:.6rem;border:1px solid #E39A6A">
                            <strong>{{ __('এআই সম্পাদকের ঘড়ি চলছে না।') }}</strong> {{ __('নিয়মিত কাজের জন্য hPanel-এ একটি Cron লাইন যোগ করতে হবে (docs/DEPLOYMENT.md)। ততক্ষণ নতুন জমা এলে তখনই কাজ করে।') }}
                        </div>
                    @endif
                    @if ($t['isAdmin'])
                        <div style="margin-top:.4rem">{{ __('এই মাসের খরচ:') }} <strong>{{ Bn::num(number_format($t['aiSpent'], 2)) }} {{ __('ডলার') }}</strong></div>
                        <div style="display:flex;flex-wrap:wrap;gap:.5rem;margin-top:.6rem">
                            @if ($t['aiPaused'])
                                <x-filament::button size="sm" color="success" icon="heroicon-o-play" wire:click="resumeAi">{{ __('এআই চালু করুন') }}</x-filament::button>
                            @else
                                <x-filament::button size="sm" color="danger" icon="heroicon-o-pause" wire:click="pauseAi" wire:confirm="{{ __('এআই থামাবেন? নতুন কাজ আর খরচ বন্ধ থাকবে, বাকি সব আগের মতো চলবে।') }}">{{ __('এআই থামান') }}</x-filament::button>
                            @endif
                            <x-filament::button size="sm" color="gray" tag="a" :href="\App\Filament\Pages\AiTeam::getUrl()">{{ __('এআই দল') }}</x-filament::button>
                            <x-filament::button size="sm" color="gray" tag="a" :href="\App\Filament\Pages\AiSettings::getUrl()">{{ __('মানদণ্ড ও খরচ') }}</x-filament::button>
                        </div>
                    @endif
                </div>
            </x-filament::section>

            @if ($t['isAdmin'])
                <x-filament::section icon="heroicon-o-book-open" collapsible persist-collapsed collapse-id="hp-today-5">
                    <x-slot name="heading">{{ __('পরের সংখ্যা') }}</x-slot>
                    @if ($t['issue'])
                        <p style="margin:0">{{ __('প্রস্তাব তৈরি:') }} <strong>{{ $t['issue']->title }}</strong>{{ __('। "সংখ্যা দেখুন" চাপুন।') }}</p>
                    @elseif ($t['issuePlanning'])
                        <p style="margin:0">{{ __('এআই সম্পাদক') }} {{ Bn::num($t['nextMonth']->locale(app()->getLocale())->isoFormat('MMMM')) }} {{ __('মাসের সংখ্যা সাজাচ্ছে… এক-দুই মিনিট পরে পাতাটি নতুন করে খুলুন।') }}</p>
                    @elseif ($t['issueDue'])
                        <p style="margin:0 0 .6rem">{{ Bn::num($t['nextMonth']->locale(app()->getLocale())->isoFormat('MMMM YYYY')) }}{{ __('-এর সংখ্যা এখনও তৈরি হয়নি। প্রতি মাসের ২০ তারিখে এআই নিজে থেকেই প্রস্তাব তৈরি করে; চাইলে এখনই:') }}</p>
                        <x-filament::button size="sm" icon="heroicon-o-sparkles" wire:click="proposeIssue">{{ __('সংখ্যার প্রস্তাব তৈরি করুন') }}</x-filament::button>
                    @else
                        <p style="margin:0">{{ Bn::num($t['nextMonth']->locale(app()->getLocale())->isoFormat('MMMM')) }} {{ __('মাসের সংখ্যা তৈরি হয়ে গেছে।') }} <a href="{{ $issues::getUrl('index') }}" style="text-decoration:underline">{{ __('সব সংখ্যা') }}</a></p>
                    @endif
                </x-filament::section>
            @endif

            <x-filament::section icon="heroicon-o-calendar-days" collapsible persist-collapsed collapse-id="hp-today-6">
                <x-slot name="heading">{{ __('প্রকাশের সূচি (দুই সপ্তাহ)') }}</x-slot>
                <x-slot name="description">{{ __('সোম/বুধ/শুক্র সকাল ৯টা নিয়মিত দিন। খালি দিনে নতুন লেখা বসবে।') }}</x-slot>
                @foreach ($t['week']->filter(fn ($d) => $d['slot'] || $d['posts']->isNotEmpty()) as $d)
                    <div class="hp-day">
                        <span style="font-weight:700;white-space:nowrap">{{ Bn::num($d['day']->locale(app()->getLocale())->isoFormat('ddd, D MMM')) }}</span>
                        @if ($d['posts']->isNotEmpty())
                            <span style="text-align:right;min-width:0">{{ $d['posts']->implode(' · ') }}</span>
                        @else
                            <span class="hp-muted">{{ __('খালি') }}</span>
                        @endif
                    </div>
                @endforeach
            </x-filament::section>

            @if ($t['isAdmin'] && $t['auditOpen'])
                <div class="hp-card hp-muted">{{ __('পুরনো লেখার অডিট সুপারিশ, সিদ্ধান্ত বাকি:') }} {{ Bn::num($t['auditOpen']) }}{{ __('টি') }}</div>
            @endif
        </div>
    </x-filament::section>
</x-filament-panels::page>
