<x-layouts.app :seo="$seo">
    {{-- HERO: sunrise fan (design board ১) --}}
    <section class="relative overflow-hidden border-b-2 border-navy" style="background: linear-gradient(160deg,#7CCBEE 0%,#2DAAE2 45%,#1A86C2 100%)">
        <div class="halftone absolute inset-0" aria-hidden="true"></div>
        <div class="relative mx-auto max-w-[1320px] px-5 pb-10 pt-8 md:min-h-[640px] md:pb-0 md:pt-16">
            <div class="relative z-10 max-w-[640px]">
                <span class="inline-block -rotate-3 rounded-full border-2 border-navy bg-paper px-4 pt-1 font-hand text-2xl shadow-[3px_3px_0_#13212C]">২০১২ থেকে</span>
                <h1 class="mt-5 font-display text-[3.6rem] font-extrabold leading-[.85] [word-spacing:.22em] sm:text-7xl sm:tracking-tight sm:[word-spacing:normal] md:text-[150px]" style="font-stretch: 75%">বাংলা সাহিত্যের<br><span class="text-paper [-webkit-text-stroke:3px_#13212C] [paint-order:stroke]">নতুন হাওয়া</span></h1>
                <p class="mt-6 max-w-[460px] font-serif text-lg font-medium leading-relaxed">কবিতা, গল্প, প্রবন্ধ আর ভ্রমণকথার অনলাইন পত্রিকা। লেখা পাঠান — প্রকাশ সবসময় বিনামূল্যে।</p>
                <div class="mt-6 flex flex-wrap gap-3">
                    <a href="{{ \App\Support\Url::to('/category/poetry') }}" class="flex h-14 items-center rounded-full bg-navy px-6 text-lg font-bold text-paper shadow-[4px_4px_0_#CB1B32]">পড়া শুরু করুন</a>
                    <a href="{{ \App\Support\Url::to('/submit') }}" class="flex h-14 items-center rounded-full border-2 border-navy bg-paper px-6 text-lg font-bold">লেখা পাঠান</a>
                </div>
                <p class="mt-6 text-base font-semibold">{{ \App\Support\Bn::num($counts['years']) }} বছর ধরে <b>{{ \App\Support\Bn::num($counts['writers']) }} জন লেখক</b>, <b>{{ \App\Support\Bn::num($counts['pieces']) }}টি লেখা</b> — সবাই আপনার মতোই পাঠক ছিলেন একদিন।</p>
            </div>
            <x-fan class="absolute -right-[2%] bottom-0 hidden w-[min(58vw,820px)] md:block" stroke="#13212C" />
        </div>
    </section>

    @if ($issue)
    {{-- The month's issue, as a magazine on the stand: cover, name, editorial, and "পত্রিকা খুলুন" (owner, 2026-11-12) --}}
    <section class="border-b-2 border-navy bg-navy text-paper" aria-labelledby="issue-h">
        <div class="mx-auto flex max-w-[1320px] flex-wrap items-center gap-5 px-5 py-6 sm:flex-nowrap">
            <a href="{{ \App\Support\Url::to('/sonkha/'.$issue->slug.'/boi') }}" class="relative block aspect-[1/1.41] w-24 flex-none overflow-hidden rounded-md border-2 border-paper bg-navy shadow-[4px_4px_0_#CB1B32] sm:w-28" aria-hidden="true" tabindex="-1">
                @if ($issue->cover_path)<img src="{{ asset(ltrim($issue->cover_path, '/')) }}" alt="" class="absolute inset-0 h-full w-full object-cover" loading="lazy">@else<x-fan class="absolute -bottom-3 -right-6 w-28" stroke="#FFFBF4" />@endif
                <span class="absolute left-1.5 top-1 font-hand text-lg text-paper [text-shadow:0_1px_3px_#13212C]">হাতপাখা</span>
            </a>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-bold text-peach">এই মাসের সংখ্যা</p>
                <h2 id="issue-h" class="font-display text-3xl font-extrabold leading-tight">{{ $issue->title }}</h2>
                <p class="mt-1 text-peach-light">{{ \App\Support\Bn::num((string) $issueCount) }}টি লেখা@if ($issueEditorial) · সম্পাদকীয়: {{ $issueEditorial->title }}@endif</p>
            </div>
            <div class="flex w-full flex-wrap gap-3 sm:w-auto">
                <a href="{{ \App\Support\Url::to('/sonkha/'.$issue->slug.'/boi') }}" class="flex h-12 items-center rounded-full bg-red px-6 font-bold text-white">পত্রিকা খুলুন</a>
                <a href="{{ $issue->url() }}" class="flex h-12 items-center rounded-full border-2 border-paper px-5 font-bold text-paper">সূচি দেখুন</a>
            </div>
        </div>
    </section>
    @endif

    @if ($onThisDay)
    <section class="border-b-2 border-dashed border-navy">
        <div class="mx-auto flex max-w-[1320px] flex-wrap items-center gap-x-4 gap-y-2 px-5 py-4">
            <span class="font-hand text-2xl text-red">এই দিনে, {{ \App\Support\Bn::num(now()->year - $onThisDay->published_at->year) }} বছর আগে</span>
            <span class="text-lg">হাতপাখায় ছাপা হয়েছিল <a href="{{ $onThisDay->url() }}" class="border-b-2 border-red font-serif font-bold">“{{ $onThisDay->title }}”</a> — <x-byline :post="$onThisDay" /></span>
        </div>
    </section>
    @endif

    {{-- D174: the daily word game, one tap from the front page --}}
    <section class="border-b-2 border-navy bg-peach-light">
        <a href="{{ \App\Support\Url::to('/khela') }}" class="mx-auto flex max-w-[1320px] items-center gap-4 px-5 py-4 hover:bg-white">
            <span class="flex flex-none gap-1" aria-hidden="true">@foreach (['শ', 'ব্দ', 'খে', 'লা'] as $t)<span class="flex h-11 w-11 items-center justify-center rounded-xl border-2 border-navy bg-white font-serif text-lg font-bold shadow-[2px_2px_0_#13212C] {{ $loop->even ? '-rotate-3' : 'rotate-2' }}">{{ $t }}</span>@endforeach</span>
            <span class="min-w-0 flex-1"><span class="block text-lg font-extrabold leading-tight">আজকের শব্দ-খেলা</span><span class="block text-sm text-ink2">এলোমেলো অক্ষর সাজিয়ে পাঁচটা শব্দ। দু মিনিটের খেলা, রোজ নতুন।</span></span>
            <span class="flex h-11 flex-none items-center rounded-full bg-red px-5 font-bold text-white">খেলুন</span>
        </a>
    </section>

    {{-- D175: পাঠকের কলম, this week's theme: anyone can write in a minute --}}
    @if ($kolom = \App\Models\ReaderPrompt::current())
    <section class="border-b-2 border-navy">
        <a href="{{ \App\Support\Url::to('/kolom') }}" class="mx-auto flex max-w-[1320px] flex-wrap items-center gap-x-4 gap-y-2 px-5 py-4 hover:bg-peach-light">
            <span class="font-hand text-2xl text-red">পাঠকের কলম</span>
            <span class="min-w-0 flex-1 text-lg"><strong class="font-serif">“{{ $kolom->title }}”</strong> <span class="text-ink2">এ সপ্তাহের বিষয়। এক মিনিটে লিখুন, সেরা দশটি রবিবার ছাপা হবে।</span></span>
            <span class="flex h-11 flex-none items-center rounded-full border-2 border-navy bg-white px-5 font-bold">লিখুন →</span>
        </a>
    </section>
    @endif

    @if ($poemOfDay)
    {{-- Poem poster (the editor's pick when there is one, D78: with the editor's reason) --}}
    <section class="relative overflow-hidden text-paper" style="background: linear-gradient(160deg,#13212C,#12304A)" aria-labelledby="poem-h">
        <div class="absolute -right-10 -top-40 font-serif text-[520px] leading-none text-red" aria-hidden="true">“</div>
        <div class="relative mx-auto grid max-w-[1320px] gap-8 px-5 py-14 md:grid-cols-[1fr_2fr] md:gap-10 md:py-24">
            <div class="flex flex-col gap-3">
                <span class="w-fit -rotate-3 rounded-full bg-sky px-4 pt-1 font-hand text-2xl text-navy">{{ $isPick ? 'সম্পাদকের পছন্দ' : 'আজকের '.$poemOfDay->kindLabel() }}</span>
                <h2 id="poem-h" class="font-display text-5xl font-extrabold leading-none"><a href="{{ $poemOfDay->url() }}">{{ $poemOfDay->title }}</a></h2>
                <span class="text-peach-light"><x-byline :post="$poemOfDay" /> · {{ \App\Support\Bn::num($poemOfDay->reading_minutes) }} মিনিট</span>
                @if ($isPick && $pickNote)
                    <figure class="mt-1 border-l-4 border-peach pl-4">
                        <figcaption class="text-base font-bold text-peach">কেন পছন্দ</figcaption>
                        <blockquote class="mt-1 font-serif text-lg leading-relaxed text-paper">{{ $pickNote }}</blockquote>
                    </figure>
                @endif
                <a href="{{ $poemOfDay->url() }}" class="mt-2 hidden h-12 w-fit items-center rounded-full bg-paper px-5 font-bold text-navy md:flex">{{ 'পুরো '.$poemOfDay->kindLabel().' পড়ুন' }}</a>
            </div>
            <div>
                @if ($poemOfDay->isPoem())
                    <div class="poem-bn poem-tight max-w-[34ch] text-2xl md:text-4xl">{!! \App\Support\Excerpt::lines($poemOfDay->body_html, 6) !!}</div>
                @else
                    {{-- Prose (owner, 2026-11-09): only the opening, never the whole piece --}}
                    <div class="max-w-[40ch] font-serif text-xl leading-relaxed md:text-2xl md:leading-relaxed">{!! \App\Support\Excerpt::prose($poemOfDay->body_html, 320) !!}</div>
                @endif
                <a href="{{ $poemOfDay->url() }}" class="mt-6 flex h-12 w-fit items-center rounded-full bg-paper px-5 font-bold text-navy md:hidden">{{ 'পুরো '.$poemOfDay->kindLabel().' পড়ুন' }}</a>
            </div>
        </div>
    </section>
    @endif

    @if ($serials->isNotEmpty())
        {{-- D108 (owner): ধারাবাহিক near the top, each card straight to its newest episode --}}
        <section class="border-b-2 border-navy bg-paper" aria-labelledby="serials-h">
            <div class="mx-auto max-w-[1320px] px-5 py-12">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div><div class="font-hand text-2xl text-red">সপ্তাহে একটি করে পর্ব</div>
                        <h2 id="serials-h" class="font-display text-5xl font-extrabold leading-none md:text-6xl" style="font-stretch: 78%">ধারাবাহিক</h2></div>
                    <a href="{{ \App\Support\Url::to('/dharabahik') }}" class="inline-flex min-h-11 items-center text-lg font-bold"><span class="border-b-[3px] border-red">সব ধারাবাহিক</span></a>
                </div>
                <ul class="mt-7 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($serials as $s)
                        @foreach ([$s->latestEpisode()] as $ep)
                            <li class="lift flex flex-col overflow-hidden rounded-2xl border-2 border-navy bg-white shadow-[5px_5px_0_#13212C]">
                                <a href="{{ $s->url() }}" class="relative block">
                                    @if ($s->cover_path)<img src="{{ asset($s->cover_path) }}" alt="" width="{{ $s->cover_width }}" height="{{ $s->cover_height }}" loading="lazy" class="aspect-[3/2] w-full object-cover">@endif
                                    @if ($ep && $ep->published_at->gt(now()->subDays(7)))<span class="absolute left-3 top-3 rounded-full bg-red px-3 py-1 text-sm font-extrabold text-white">নতুন পর্ব</span>@endif
                                </a>
                                <div class="flex flex-1 flex-col p-4">
                                    <span class="text-sm font-bold text-red">{{ $s->genre }} · প্রতি {{ $s->dayName() }}</span>
                                    <a href="{{ $s->url() }}" class="mt-1 font-display text-2xl font-extrabold leading-tight hover:text-red">{{ $s->title }}</a>
                                    @if ($s->logline)<p class="mt-1 line-clamp-2 font-serif text-ink2">{{ $s->logline }}</p>@endif
                                    @if ($ep)
                                        <a href="{{ $ep->url() }}" class="mt-auto flex min-h-12 items-center justify-between gap-2 pt-3 font-bold text-sky-night hover:text-red">
                                            <span class="line-clamp-1">{{ $ep->episodeLabel() }}</span><span aria-hidden="true">→</span></a>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    @if ($spotlight)
        {{-- D78: the writer of the month (or the latest honour). Reviewed by a UX pass: one link into the piece, praise set as a quote. --}}
        @php
            $sp = $spotlight;
            $quote = $sp->honour_quote && $spotlightPost && $sp->honour_post_id === $spotlightPost->id ? $sp->honour_quote : null;
            // A line that stops at a comma is shown as cut (…), never completed with invented words.
            $quote = $quote && preg_match('/[,;:—–-]$/u', $quote) ? $quote.' …' : $quote;
        @endphp
        <section class="border-b-2 border-navy bg-peach-light" aria-labelledby="spotlight-h">
            <div class="mx-auto grid max-w-[1320px] gap-6 px-5 py-12 md:grid-cols-[auto_1fr] md:items-center md:gap-12 md:py-16">
                <div class="flex items-center gap-4 md:flex-col md:text-center">
                    @if ($sp->avatar_path)
                        <img src="{{ $sp->avatar_path }}" alt="" width="200" height="200" loading="lazy" class="h-24 w-24 flex-none rounded-full border-4 border-red object-cover shadow-[4px_4px_0_#13212C] md:h-52 md:w-52 md:shadow-[6px_6px_0_#13212C]">
                    @else
                        <span class="flex h-24 w-24 flex-none items-center justify-center rounded-full border-4 border-red bg-gradient-to-br from-peach to-peach-deep font-display text-5xl font-extrabold shadow-[4px_4px_0_#13212C] md:h-52 md:w-52 md:text-7xl md:shadow-[6px_6px_0_#13212C]" aria-hidden="true">{{ $sp->initial() }}</span>
                    @endif
                    <div class="min-w-0 md:-mt-8">
                        <div class="font-hand text-xl text-red md:hidden">হাতপাখা সম্মান জানায়</div>
                        <div class="font-display text-3xl font-extrabold leading-tight md:hidden">{{ $sp->publicName() }}</div>
                        <span class="mt-1 inline-block -rotate-2 rounded-full bg-red px-3 py-1 text-sm font-extrabold text-white shadow-[3px_3px_0_#13212C] md:px-4 md:py-1.5 md:text-base"><span aria-hidden="true">★</span> {{ $sp->honour_label }}</span>
                    </div>
                </div>
                <div class="min-w-0">
                    <div class="hidden font-hand text-2xl text-red md:block">হাতপাখা সম্মান জানায়</div>
                    <h2 id="spotlight-h" class="sr-only font-display text-6xl font-extrabold leading-none md:not-sr-only md:block" style="font-stretch: 80%">{{ $sp->publicName() }}</h2>
                    @if ($sp->honour_note)
                        <figure class="relative mt-2 max-w-[62ch] pl-9 md:mt-4 md:pl-11">
                            <span class="absolute left-0 top-[-.6rem] font-serif text-6xl leading-none text-red" aria-hidden="true">“</span>
                            <blockquote class="font-serif text-xl leading-relaxed">{{ $sp->honour_note }}</blockquote>
                            <figcaption class="mt-2 font-hand text-xl text-red">— সম্পাদকমণ্ডলী, হাতপাখা</figcaption>
                        </figure>
                    @endif
                    @if ($quote)
                        <figure class="mt-6 max-w-[62ch] rounded-2xl border-2 border-navy bg-white p-5 shadow-[4px_4px_0_#13212C]">
                            <blockquote class="font-serif text-xl leading-relaxed [text-wrap:balance] md:text-2xl">“{{ $quote }}”</blockquote>
                            <figcaption class="mt-2 text-sm font-bold text-ink2">“{{ $spotlightPost->title }}” থেকে</figcaption>
                        </figure>
                    @endif
                    <div class="mt-6 flex flex-wrap gap-3">
                        @if ($spotlightPost)<a href="{{ $spotlightPost->url() }}" class="flex h-12 items-center rounded-full bg-navy px-6 font-bold text-white">{{ $quote ? 'পুরো লেখাটি পড়ুন' : '“'.\Illuminate\Support\Str::limit($spotlightPost->title, 40).'” পড়ুন' }}</a>@endif
                        <a href="{{ $sp->url() }}" class="flex h-12 items-center rounded-full border-2 border-navy bg-white px-6 font-bold">{{ $sp->publicName() }}-এর সব লেখা →</a>
                    </div>
                </div>
            </div>
        </section>
    @endif

    @php
        $milestones = $milestones->reject(fn ($m) => $spotlight && $m->author_id === $spotlight->id)->values();
    @endphp
    @if ($milestones->isNotEmpty())
        {{-- D78: writers' milestones, approved by the owner --}}
        <section class="border-b-2 border-dashed border-navy" aria-labelledby="milestones-h">
            <div class="mx-auto max-w-[1320px] px-5 py-10">
                <div class="font-hand text-2xl text-red">হাতপাখা পরিবার</div>
                <h2 id="milestones-h" class="font-display text-4xl font-extrabold">অভিনন্দন</h2>
                <ul class="mt-5 flex flex-wrap gap-3">
                    @foreach ($milestones as $m)
                        @php
                            [$badge, $bg] = match (true) {
                                $m->kind === 'first' => ['১ম', 'bg-red'],
                                str_starts_with($m->kind, 'pieces_') => [\App\Support\Bn::num(substr($m->kind, 7)), 'bg-red'],
                                default => [\App\Support\Bn::num(substr($m->kind, 6)).'ব', 'bg-navy'],
                            };
                            $text = match (true) {
                                $m->kind === 'first' => 'প্রথম লেখা প্রকাশিত',
                                str_starts_with($m->kind, 'pieces_') => $m->label.' প্রকাশিত',
                                default => $m->label.' পূর্ণ',
                            };
                        @endphp
                        <li>
                            <a href="{{ $m->post?->isLive() ? $m->post->url() : $m->author->url() }}" class="flex min-h-12 items-center gap-3 rounded-full border-2 border-navy bg-white py-1.5 pl-1.5 pr-5 hover:bg-peach-light">
                                <span class="flex h-11 w-11 flex-none items-center justify-center rounded-full {{ $bg }} text-sm font-extrabold text-white" aria-hidden="true">{{ $badge }}</span>
                                <span><span class="block font-bold leading-tight">{{ $m->author->publicName() }}</span><span class="block text-sm text-ink2">{{ $text }}</span></span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    {{-- Stories: taped pages. Owner (2026-11-08): new first, then archive pieces that change daily; read ones swapped out. --}}
    @if ($stories->isNotEmpty())
    <section class="border-y-2 border-navy bg-peach-light">
        <div class="mx-auto max-w-[1320px] px-5 py-16">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <h2 class="font-display text-6xl font-extrabold leading-none md:text-7xl" style="font-stretch: 78%">গল্পের পাতা</h2>
                <a href="{{ \App\Support\Url::to('/category/short-stories') }}" class="inline-flex min-h-11 items-center text-lg font-bold"><span class="border-b-[3px] border-red">সব গল্প</span></a>
            </div>
            <div class="hp-first-only mt-9 grid gap-7 sm:grid-cols-2 lg:grid-cols-4" data-rotate="4">
                @foreach ($stories as $i => $story)
                    <a href="{{ $story->url() }}" data-pid="{{ $story->id }}" @if ($i === 0) data-first @endif @if ($i >= 4) hidden @endif class="lift relative flex flex-col gap-3 border-2 border-navy bg-white p-4 shadow-[6px_6px_0_#13212C] {{ $i % 2 ? 'tilt-b' : 'tilt-a' }}">
                        <span class="absolute -top-3 left-6 h-5 w-20 -rotate-6 bg-peach/90" aria-hidden="true"></span>
                        @include('partials.shelf-badge', ['p' => $story])
                        <x-title-card :post="$story" class="!rounded-none border-2 border-navy" />
                        @if ($story->hasCardImage())<span class="font-serif text-xl font-bold leading-snug">{{ $story->title }}</span>@endif
                        <span class="flex justify-between text-sm font-semibold"><span>{{ $story->authors->first()?->publicName() }}</span><span class="text-red">{{ \App\Support\Bn::num($story->reading_minutes) }} মিনিট</span></span>
                    </a>
                @endforeach
            </div>
            {{-- Phones: one taped card, then compact rows (mobile UX review); wider screens: four cards. --}}
            @if ($stories->count() > 1)
                <ul class="mt-6 divide-y divide-rule border-y border-navy/20 sm:hidden" data-rotate="3" data-skip-first>
                    @foreach ($stories as $i => $story)
                        <li data-pid="{{ $story->id }}" @if ($i === 0 || $i > 3) hidden @endif><x-post-row :post="$story" /></li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>
    @endif

    <x-ad-slot spot="home" :height="250" class="mx-auto max-w-[1100px] px-5" />

    @if ($collection && $collectionPosts->isNotEmpty())
        {{-- D97: the collection in season --}}
        <section class="border-y-2 border-navy bg-paper" aria-labelledby="col-h">
            <div class="mx-auto max-w-[1100px] px-5 py-12">
                <div class="font-hand text-2xl text-red">সংকলন</div>
                <h2 id="col-h" class="font-display text-4xl font-extrabold leading-tight md:text-5xl"><a href="{{ $collection->url() }}" class="hover:text-red">{{ $collection->title }}</a></h2>
                @if ($collection->intro)<p class="mt-3 line-clamp-2 max-w-[65ch] font-serif text-lg text-ink2 md:line-clamp-none">{{ \Illuminate\Support\Str::limit($collection->intro, 220) }}</p>@endif
                <ul class="mt-5 divide-y divide-rule border-y border-rule bg-paper px-3 sm:grid sm:grid-cols-2 sm:gap-x-6 sm:divide-y-0">
                    @foreach ($collectionPosts as $p)
                        <li class="sm:border-b sm:border-rule"><x-post-row :post="$p" :meta="($p->authors->first()?->publicName() ?? '').($p->section ? ' · '.$p->section->name_bn : '')" /></li>
                    @endforeach
                </ul>
                <a href="{{ $collection->url() }}" class="mt-5 inline-flex h-12 items-center rounded-full bg-navy px-6 font-bold text-white">পুরো সংকলন ({{ \App\Support\Bn::num((string) count((array) $collection->post_ids)) }}টি লেখা) →</a>
            </div>
        </section>
    @endif

    @if ($kids)
        {{-- D101: ছোটদের হাতপাখা --}}
        <section class="border-b-2 border-navy bg-sky-tint" aria-labelledby="kids-h">
            <div class="mx-auto grid max-w-[1100px] gap-6 px-5 py-12 md:grid-cols-[1fr_1.2fr] md:items-center">
                <div>
                    <div class="font-hand text-2xl text-red">বিদেশে বড় হওয়া ছোটদের জন্যও</div>
                    <h2 id="kids-h" class="font-display text-4xl font-extrabold leading-tight md:text-5xl"><a href="{{ $kids->url() }}" class="hover:text-red">{{ $kids->name_bn }}</a></h2>
                    <p class="mt-3 max-w-[50ch] font-serif text-lg text-ink2">ছড়া, গল্প আর বর্ণমালা। কঠিন শব্দের মানে পাশে লেখা।</p>
                    <div class="mt-5 flex flex-wrap gap-3">
                        <a href="{{ \App\Support\Url::to('/bornomala') }}" class="inline-flex h-12 items-center rounded-full bg-navy px-6 font-bold text-white">অ আ ক খ — বর্ণমালা</a>
                        <a href="{{ $kids->url() }}" class="inline-flex h-12 items-center rounded-full border-2 border-navy bg-paper px-6 font-bold hover:bg-peach-light">সব লেখা →</a>
                    </div>
                </div>
                @if ($kidsPosts->isNotEmpty())
                    <ul class="divide-y divide-rule rounded-2xl border-2 border-navy bg-paper px-3">
                        @foreach ($kidsPosts as $p)
                            <li><x-post-row :post="$p" :meta="($p->authors->first()?->publicName() ?? '').($p->ageLabel() ? ' · বয়স '.$p->ageLabel() : '')" /></li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>
    @endif

    {{-- Reader letters (real comments, opt-out respected) --}}
    @if ($letters->isNotEmpty())
    <section class="mx-auto max-w-[1320px] px-5 py-16">
        <div class="font-hand text-3xl text-red">ডাকবাক্স থেকে</div>
        <h2 class="font-display text-6xl font-extrabold leading-none" style="font-stretch: 78%">পাঠকের চিঠি</h2>
        <div class="mt-8 grid gap-7 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($letters as $i => $letter)
                <figure class="lined relative m-0 border-[1.5px] border-navy bg-white px-5 pb-5 pt-7 shadow-[4px_5px_0_rgba(19,33,44,.85)] {{ $i % 2 ? 'tilt-b' : 'tilt-a' }}">
                    <span class="absolute -top-3 left-1/2 h-5 w-20 -translate-x-1/2 -rotate-3 bg-sky/55" aria-hidden="true"></span>
                    <blockquote class="font-serif text-lg leading-relaxed">“{{ $letter->body }}”</blockquote>
                    <figcaption class="mt-3"><span class="font-hand text-2xl text-red">— {{ $letter->author_name }}</span><span class="block text-xs text-ink2">“{{ $letter->post?->title }}” পড়ে · {{ \App\Support\Bn::num((string) $letter->created_at->year) }}</span></figcaption>
                </figure>
            @endforeach
        </div>
    </section>
    @endif

    @php
        $honoured = $honoured->reject(fn ($h) => $spotlight && $h->id === $spotlight->id)->take(3)->values();
    @endphp
    @if ($honoured->isNotEmpty())
        {{-- Writers the magazine has honoured (D66, D76): photo, honour, the editor's words, two pieces to read. --}}
        <section class="mx-auto max-w-[1320px] px-5 py-10" aria-labelledby="honoured-h">
            <div class="font-hand text-2xl text-red">হাতপাখার সম্মান</div>
            <h2 id="honoured-h" class="font-display text-4xl font-extrabold">সম্মানিত লেখক</h2>
            <div class="mt-6 grid gap-5 {{ [1 => '', 2 => 'lg:grid-cols-2', 3 => 'lg:grid-cols-3'][min(3, $honoured->count())] }}">
                @foreach ($honoured as $h)
                    @php
                        $pieces = $h->posts()->published()->latest('published_at')->limit(2)->get(['posts.id', 'posts.slug', 'posts.title', 'posts.published_at']);
                    @endphp
                    <article class="relative overflow-hidden rounded-3xl border-2 border-navy bg-white {{ $honoured->count() === 1 ? 'md:grid md:grid-cols-[auto_1fr]' : '' }}">
                        <div class="flex items-center gap-4 bg-peach-light p-5 {{ $honoured->count() === 1 ? 'md:flex-col md:justify-center md:px-10 md:text-center' : '' }}">
                            @if ($h->avatar_path)
                                <img src="{{ $h->avatar_path }}" alt="" loading="lazy" width="96" height="96" class="h-20 w-20 flex-none rounded-full border-[3px] border-red object-cover md:h-24 md:w-24">
                            @else
                                <span class="flex h-20 w-20 flex-none items-center justify-center rounded-full border-[3px] border-red bg-gradient-to-br from-peach to-peach-deep text-3xl font-extrabold md:h-24 md:w-24" aria-hidden="true">{{ $h->initial() }}</span>
                            @endif
                            <div class="min-w-0">
                                <span class="inline-block rounded-full bg-red px-3 py-1 text-sm font-extrabold text-white">★ {{ $h->honour_label }}</span>
                                <div class="mt-2 font-display text-2xl font-extrabold leading-tight"><a href="{{ $h->url() }}" class="hover:text-red">{{ $h->publicName() }}</a></div>
                            </div>
                        </div>
                        <div class="p-5 md:p-7">
                            @if ($h->honour_note)
                                <blockquote class="font-serif text-lg leading-relaxed md:text-xl">“{{ $h->honour_note }}”</blockquote>
                                <div class="mt-2 text-sm font-bold text-ink2">— হাতপাখার সম্পাদকমণ্ডলী</div>
                            @endif
                            @if ($pieces->isNotEmpty())
                                <ul class="mt-4 grid gap-2 border-t border-rule pt-4">
                                    @foreach ($pieces as $pc)
                                        <li><a href="{{ $pc->url() }}" class="font-serif text-lg font-bold hover:text-red">{{ $pc->title }}</a></li>
                                    @endforeach
                                </ul>
                            @endif
                            <a href="{{ $h->url() }}" class="mt-4 inline-flex h-11 items-center rounded-full bg-navy px-5 font-bold text-white">সব লেখা পড়ুন →</a>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    <div class="mx-auto max-w-[1320px] px-5 pb-6"><x-newsletter-signup variant="home" /></div>


    {{-- Travel stamps. Owner (2026-11-08): new first, then archive pieces that change daily; read ones swapped out. --}}
    @if ($travel->isNotEmpty())
    <section class="mx-auto max-w-[1320px] px-5 pb-12">
        <h2 class="font-display text-6xl font-extrabold leading-none" style="font-stretch: 78%">ভ্রমণের ডাকটিকিট</h2>
        <div class="mt-6 flex snap-x gap-6 overflow-x-auto pb-4 pt-4" data-rotate="5">
            @foreach ($travel as $i => $t)
                <a href="{{ $t->url() }}" data-pid="{{ $t->id }}" @if ($i >= 5) hidden @endif class="lift relative flex h-[300px] w-[240px] flex-none snap-start flex-col bg-white p-3 outline-4 outline-dotted outline-navy {{ $i % 2 ? 'tilt-b' : 'tilt-a' }}">
                    @include('partials.shelf-badge', ['p' => $t])
                    @if ($t->hasCardImage())
                        {{-- The cover as the stamp's picture, the title on a coloured strip (white text only on red/navy). --}}
                        <span class="flex flex-1 flex-col overflow-hidden border-2 border-navy">
                            <picture class="relative block min-h-0 flex-1">
                                @if ($set = \App\Support\Images::srcset('/'.$t->featured_image_path))<source type="image/webp" srcset="{{ $set }}" sizes="240px">@endif
                                <img src="{{ asset($t->featured_image_path) }}" alt="" loading="lazy" decoding="async" width="{{ $t->featured_image_width }}" height="{{ $t->featured_image_height }}" class="absolute inset-0 h-full w-full object-cover">
                            </picture>
                            <span class="block p-3 {{ ['bg-navy text-paper', 'bg-red text-white'][$i % 2] }}"><span class="line-clamp-2 font-display text-lg font-extrabold leading-tight">{{ $t->title }}</span></span>
                        </span>
                    @else
                        <span class="flex flex-1 flex-col justify-end border-2 border-navy p-4 {{ ['bg-navy text-paper', 'bg-sky', 'bg-red text-white', 'bg-peach'][$i % 4] }}"><span class="font-display text-2xl font-extrabold leading-tight">{{ $t->title }}</span></span>
                    @endif
                    <span class="flex justify-between pt-2 text-xs font-bold"><span>হাতপাখা ডাক</span><span>{{ $t->authors->first()?->publicName() }}</span></span>
                </a>
            @endforeach
        </div>
    </section>
    @endif
    {{-- D111: the second home ad, after the travel stamps, before the footer --}}
    <x-ad-slot spot="home_2" :height="250" class="mx-auto max-w-[1100px] px-5" />
</x-layouts.app>
