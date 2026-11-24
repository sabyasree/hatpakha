<x-layouts.app :seo="$seo">
    @php
        // D281: where the reader is in a serial (published episodes, in order), worked out once
        $nav = $post->serial_id && $post->serial?->isPublic() ? \App\Support\EpisodeNav::for($post) : null;
    @endphp
    @if (! empty($preview))
        {{-- D251: the desk's preview; readers never see this page --}}
        <div class="sticky top-0 z-40 bg-navy px-4 py-2.5 text-center font-bold text-white">প্রিভিউ: পাঠকেরা ঠিক এভাবেই দেখবেন। @if (! $post->isLive())এখনও প্রকাশিত হয়নি।@endif <span class="font-semibold text-peach">ডেস্কে যা সেভ করেছেন, সেটাই দেখাচ্ছে।</span></div>
    @endif
    <div class="h-1 bg-gradient-to-r from-pink via-red to-peach" aria-hidden="true"></div>
    <div class="fixed inset-x-0 top-0 z-50 h-[3px] origin-left scale-x-0 bg-red" data-progress aria-hidden="true"></div>
    <x-desk-edit :url="\App\Filament\Resources\Posts\PostResource::getUrl('edit', ['record' => $post], panel: 'desk')" />
    <article class="relative mx-auto max-w-[760px] px-5 pb-10 pt-8" @if ($post->isLive() && empty($preview)) data-view="{{ $post->id }}" @endif @if ($post->isLive() && ! $post->isKids()) data-quote-by="{{ $post->authors->map->publicName()->implode(', ') }}" data-quote-title="{{ $post->title }}" data-quote-url="{{ $post->url() }}" @endif @if ($post->isLive() && auth()->user()?->reading_history) data-read-track="{{ route('reading.progress', $post) }}" data-read-token="{{ csrf_token() }}" @endif>
        {{-- D111: wide computer screens only: a tall ad in the empty margin beside the text, starting below the first screen, never over the text.
             D167: from 1280px (common laptops) as a narrow 160px ad, the 300px one from 1500px; both fit the margin even with a 17px scrollbar. --}}
        <div class="absolute left-full top-[85vh] ml-8 hidden h-[calc(100%-85vh)] w-[160px] min-[1280px]:block min-[1500px]:ml-10 min-[1500px]:w-[300px]">
            <div class="sticky top-24"><x-ad-slot spot="sidebar" :height="600" class="!my-0" /></div>
        </div>
        <nav aria-label="অবস্থান" class="flex flex-wrap items-center text-base text-ink2">
            @foreach (array_slice($crumbs, 0, -1) as $c)
                @if (! $loop->first)<span class="px-1" aria-hidden="true">›</span>@endif
                <a href="{{ $c[1] }}" class="inline-flex min-h-11 items-center hover:text-red">{{ $c[0] }}</a>
            @endforeach
        </nav>
        <div class="mt-4 flex flex-wrap items-center gap-2 text-base font-semibold">
            @if ($post->section)<a href="{{ $post->section->url() }}" class="inline-flex min-h-11 items-center rounded-full bg-red px-4 font-bold text-white">{{ $post->section->name_bn }}</a>@endif
            <span class="text-ink2">{{ \App\Support\Bn::num($post->reading_minutes) }} মিনিট পড়া</span>
            @if ($post->readers_choice)<a href="{{ \App\Support\Url::to('/pathoker-pochhondo') }}" class="inline-flex min-h-11 items-center rounded-full bg-navy px-4 font-bold text-white">পাঠকের পছন্দ · {{ \App\Support\Feelings::monthLabel($post->readers_choice) }}</a>@endif
            @if ($post->isKids() && $post->ageLabel())<span class="rounded-full bg-sky-tint px-3 py-1 font-bold text-sky-night">বয়স {{ $post->ageLabel() }}</span>@endif
        </div>
        <h1 class="mt-4 font-display text-[2.4rem] font-extrabold leading-[1.1] sm:text-5xl sm:leading-[1.05] md:text-6xl" style="font-stretch: 80%">{{ $post->title }}</h1>
        <div class="mt-5 flex items-center gap-3">
            @foreach ($post->authors->take(1) as $a)
                <a href="{{ $a->url() }}" class="flex h-12 w-12 flex-none items-center justify-center rounded-full bg-gradient-to-br from-red to-red-dark text-xl font-extrabold text-white" aria-hidden="true" tabindex="-1">{{ $a->initial() }}</a>
            @endforeach
            <div class="min-w-0 flex-1">
                <div class="text-lg"><x-byline :post="$post" /></div>
                <div class="text-base text-ink2">{{ \App\Support\Bn::date($post->published_at) }}@if ($post->issue) · <a href="{{ $post->issue->url() }}" class="underline">{{ $post->issue->title }}</a>@endif</div>
            </div>
            {{-- Share early: readers come from WhatsApp (mobile UX review). The phone's own share sheet when there is one. --}}
            <a href="https://wa.me/?text={{ rawurlencode(($post->share_text ?: $post->title).' — '.$post->url()) }}" data-share="{{ $post->url() }}" data-share-title="{{ $post->title }}"
                rel="noopener" target="_blank" class="flex h-11 w-11 flex-none items-center justify-center rounded-full border-2 border-navy hover:bg-peach-light" aria-label="শেয়ার করুন" title="শেয়ার করুন">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="18" cy="5" r="2.5"/><circle cx="6" cy="12" r="2.5"/><circle cx="18" cy="19" r="2.5"/><path d="m8.2 10.8 7.6-4.4M8.2 13.2l7.6 4.4"/></svg>
            </a>
        </div>

        @if ($nav)
            {{-- D281 (owner: "no way to go to the previous episode or start from the 1st"): where you are, and the way back --}}
            <nav aria-label="ধারাবাহিক" class="mt-5 rounded-2xl border-2 border-navy bg-peach-light p-3">
                @if ($nav['index'] <= 0)
                    <div class="font-bold">ধারাবাহিক · <a href="{{ $post->serial->url() }}" class="underline">{{ $post->serial->title }}</a> · পর্ব ১ (শুরু)</div>
                @else
                    <div class="text-sm font-bold text-ink2">ধারাবাহিক · <a href="{{ $post->serial->url() }}" class="text-navy underline">{{ $post->serial->title }}</a></div>
                    <div class="mt-0.5 font-bold">এটি পর্ব {{ \App\Support\Bn::num((string) $post->episode_no) }} · মোট {{ \App\Support\Bn::num((string) $nav['count']) }}টি পর্ব বেরিয়েছে</div>
                    <a href="{{ $nav['first']->url() }}" class="mt-2 flex min-h-12 items-center justify-center rounded-xl bg-navy px-3 font-bold text-white">← প্রথম পর্ব থেকে পড়ুন</a>
                @endif
                @include('partials.episode-nav', ['nav' => $nav, 'where' => 'top'])
            </nav>
        @endif

        @if ($post->featured_image_path)
            {{-- Every cover shows (owner, 2026-11-07): never larger than its own size, never taller than the screen. --}}
            <picture>
                @if ($set = \App\Support\Images::srcset('/'.$post->featured_image_path))<source type="image/webp" srcset="{{ $set }}" sizes="(max-width: 760px) 100vw, 720px">@endif
                <img src="{{ asset($post->featured_image_path) }}" alt="{{ $post->featured_image_alt }}" width="{{ $post->featured_image_width }}" height="{{ $post->featured_image_height }}" fetchpriority="high" class="mx-auto mt-7 block h-auto max-h-[80vh] w-auto max-w-full rounded-2xl">
            </picture>
            @if ($post->image_is_ai)<p class="mt-1 text-right text-[11px] leading-none text-ink2">ছবি: এআই</p>@endif
        @elseif (! $post->isPoem())
            {{-- The made-up title card repeats the title: shown on wider screens only (mobile UX review). --}}
            <x-title-card :post="$post" class="mt-7 hidden !aspect-[16/7] sm:block" />
        @endif

        @if ($post->audio_path && $post->audio_status === 'approved')
            {{-- D67: the writer reads their own piece --}}
            <div class="mt-6 rounded-2xl border-2 border-navy bg-sky-tint p-4">
                @if ($post->audio_ai)
                    {{-- D101: a machine voice is always labelled as one --}}
                    <div class="mb-2 font-bold text-sky-night">🔊 পড়ে শোনানো হচ্ছে</div>
                    <p class="-mt-1 mb-2 text-sm text-ink2">কণ্ঠ: হাতপাখার যন্ত্রকণ্ঠ</p>
                @else
                    <div class="mb-2 font-bold text-sky-night">🎙 লেখকের কণ্ঠে শুনুন</div>
                @endif
                <audio controls preload="none" src="{{ $post->audio_path }}" class="w-full"></audio>
            </div>
        @endif

        {{-- body_html is sanitised on save (App\Support\HtmlSanitizer) — the only raw output allowed. --}}
        @php
            $bodyHtml = \App\Support\Images::renderBody(\App\Support\Embeds::render($post->body_html));
            $bodyClass = ($post->isPoem() ? 'poem-bn mt-8 text-center'.($post->poemLinesAreParagraphs() ? ' poem-tight' : '') : 'prose-bn mt-6').($post->isKids() ? ' kids-read' : '');
            $bodyHalves = \App\Support\Ads::splitForMid($bodyHtml, $post);   // D167: one ad near the middle of a long story only
        @endphp
        @if ($bodyHalves)
            <div class="{{ $bodyClass }}" lang="{{ $post->language }}">{!! $bodyHalves[0] !!}</div>
            <x-ad-slot spot="article_mid" :height="280" class="!mb-2 !mt-10" />
            <p class="mb-8 text-center text-sm font-semibold text-ink2">লেখাটি চলছে <span aria-hidden="true">↓</span></p>
            <div class="prose-bn" lang="{{ $post->language }}">{!! $bodyHalves[1] !!}</div>
        @else
            <div class="{{ $bodyClass }}" lang="{{ $post->language }}">{!! $bodyHtml !!}</div>
        @endif

        @if ($post->isKids() && $post->glossaryRows())
            {{-- D101: hard words, for children learning Bengali abroad --}}
            <section class="mt-10 rounded-2xl border-2 border-navy bg-peach-light p-4 sm:p-5" aria-labelledby="glossary-h">
                <h2 id="glossary-h" class="font-display text-2xl font-extrabold">শব্দের মানে <span class="text-base font-bold text-ink2" lang="en">(Word meanings)</span></h2>
                <dl class="mt-3 divide-y divide-rule">
                    @foreach ($post->glossaryRows() as $g)
                        <div class="flex flex-wrap items-baseline gap-x-3 py-2">
                            <dt class="text-xl font-bold">{{ $g['word'] }}</dt>
                            <dd class="text-lg">@if (filled($g['bn'] ?? null)){{ $g['bn'] }}@endif @if (filled($g['en'] ?? null))<span class="text-ink2" lang="en">({{ $g['en'] }})</span>@endif</dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        @endif

        @if ($nav)
            {{-- D108: a serial episode ends with "ক্রমশ" (to be continued), as Bengali magazines always printed it --}}
            <div class="my-10 text-center font-hand text-3xl text-red">{{ $post->isLastEpisode() ? 'সমাপ্ত' : 'ক্রমশ…' }}</div>
            {{-- D281: previous and next as two equal buttons, the way back to the start, and every episode --}}
            <nav class="mb-8 rounded-2xl border-2 border-navy bg-sky-tint p-4" aria-label="ধারাবাহিকের পর্ব">
                <div class="text-sm font-bold text-ink2">ধারাবাহিক: <a href="{{ $post->serial->url() }}" class="text-navy underline">{{ $post->serial->title }}</a></div>
                <div class="mt-2 grid gap-2 {{ $nav['prev'] ? 'grid-cols-2' : 'grid-cols-1' }}">
                    @if ($nav['prev'])
                        <a href="{{ $nav['prev']->url() }}" class="flex min-h-14 flex-col items-center justify-center rounded-xl border-2 border-navy bg-white px-2 py-1 text-center font-bold leading-snug">← আগের পর্ব<span class="text-sm font-semibold">পর্ব {{ \App\Support\Bn::num((string) $nav['prev']->episode_no) }}</span></a>
                    @endif
                    @if ($nav['next'])
                        <a href="{{ $nav['next']->url() }}" class="flex min-h-14 flex-col items-center justify-center rounded-xl bg-navy px-2 py-1 text-center font-bold leading-snug text-white">পরের পর্ব →<span class="text-sm font-semibold">{{ $nav['prev'] ? 'পর্ব '.\App\Support\Bn::num((string) $nav['next']->episode_no) : $nav['next']->episodeLabel() }}</span></a>
                    @elseif ($post->isLastEpisode())
                        <span class="flex min-h-14 items-center justify-center rounded-xl border-2 border-navy bg-white px-2 text-center font-bold">গল্প শেষ</span>
                    @else
                        <span class="flex min-h-14 flex-col items-center justify-center rounded-xl border-2 border-dashed border-navy bg-white px-2 py-1 text-center font-bold leading-snug text-ink2">পরের পর্ব আসবে<span class="text-sm font-semibold">{{ $post->serial->dayName() }}</span></span>
                    @endif
                </div>
                @if (! $nav['next'] && ! $post->isLastEpisode())
                    @if ($post->episode_teaser)
                        {{-- D172: one line about what comes next, so readers come back --}}
                        <p class="mt-2 rounded-xl bg-white p-3 font-serif text-lg"><strong class="font-sans text-sm text-red">পরের পর্বে</strong><br>{{ $post->episode_teaser }}</p>
                    @endif
                    <div class="mt-3">@include('serial-follow', ['serial' => $post->serial, 'following' => $post->serial->isFollowedBy(auth()->user())])</div>
                @endif
                @if ($nav['index'] >= 2)
                    <a href="{{ $nav['first']->url() }}" class="mt-2 flex min-h-12 items-center justify-center rounded-xl border-2 border-navy bg-peach-light px-3 font-bold">শুরু থেকে পড়ুন (পর্ব ১)</a>
                @endif
                @if ($post->isLastEpisode())
                    <a href="{{ \App\Support\Url::to('/dharabahik') }}" class="mt-2 flex min-h-12 items-center justify-center rounded-xl bg-navy px-3 font-bold text-white">সব ধারাবাহিক দেখুন →</a>
                @endif
                @include('partials.episode-nav', ['nav' => $nav, 'where' => 'end'])
            </nav>
            @include('case-guess', ['serial' => $post->serial])
        @else
        <div class="my-10 text-center font-hand text-3xl text-red">সমাপ্ত</div>
        @endif
        @foreach (\App\Models\Collection::forPost($post->id) as $col)
            {{-- D97 (UX review): right where the piece ends, the next piece of its collection --}}
            @foreach ([\App\Models\Collection::nextAfter($col['ids'], $post->id)] as $nextPost)
            <a href="{{ $nextPost ? $nextPost->url() : \App\Support\Url::to('/sonkolon/'.$col['slug']) }}" class="mb-8 flex min-h-14 items-center justify-between gap-3 rounded-2xl border-2 border-navy bg-sky-tint px-4 py-3 hover:bg-peach-light">
                <span><span class="block text-sm font-bold text-ink2">“{{ $col['title'] }}” সংকলনের লেখা</span>
                    <span class="block font-bold">{{ $nextPost ? 'পরেরটি: '.$nextPost->title : 'সংকলনের সব লেখা দেখুন' }}</span></span><span aria-hidden="true">→</span></a>
            @endforeach
        @endforeach

        @include('partials.after-piece')
    </article>

    @if ($post->isLive() && ! $post->isKids())
        {{-- D173 (owner): a reader picks a line and shares it as a Hatpakha card. The card is drawn in the browser. --}}
        <button type="button" data-quote-pill hidden class="fixed bottom-[calc(4.75rem+env(safe-area-inset-bottom))] left-1/2 z-40 flex h-12 -translate-x-1/2 items-center gap-2 whitespace-nowrap rounded-full bg-navy px-5 font-bold text-white shadow-[3px_3px_0_#CB1B32] md:bottom-6">
            <span aria-hidden="true" class="font-serif text-2xl leading-none text-peach">❝</span> এই লাইন শেয়ার করুন</button>
        <div data-quote-dialog hidden class="fixed inset-0 z-[70] flex items-end justify-center bg-navy/60 p-0 sm:items-center sm:p-4" role="dialog" aria-modal="true" aria-labelledby="quote-dialog-h">
            <div class="max-h-[100dvh] w-full max-w-[460px] overflow-y-auto rounded-t-3xl border-2 border-navy bg-paper p-4 pb-[calc(1rem+env(safe-area-inset-bottom))] sm:rounded-3xl">
                <div class="flex items-center justify-between gap-3">
                    <h2 id="quote-dialog-h" class="font-display text-xl font-extrabold">আপনার বেছে নেওয়া লাইন</h2>
                    <button type="button" data-quote-close class="flex h-11 items-center rounded-full border-2 border-navy bg-white px-4 font-bold">✕ বন্ধ</button>
                </div>
                <canvas data-quote-canvas width="1080" height="1080" class="mt-3 block h-auto w-full rounded-2xl border border-rule" aria-label="শেয়ার করার ছবি"></canvas>
                <div class="mt-3 grid gap-2">
                    <button type="button" data-quote-share class="flex h-12 items-center justify-center rounded-full bg-red px-6 text-lg font-bold text-white shadow-[3px_3px_0_#13212C]">শেয়ার করুন</button>
                    <div class="grid grid-cols-2 gap-2">
                        <button type="button" data-quote-download class="flex h-12 items-center justify-center rounded-full border-2 border-navy bg-white px-3 font-bold">ছবি নামান</button>
                        <a data-quote-wa href="#" target="_blank" rel="noopener" class="flex h-12 items-center justify-center rounded-full border-2 border-navy bg-white px-3 font-bold">WhatsApp-এ লেখা</a>
                    </div>
                </div>
                <p data-quote-msg role="status" class="mt-2 min-h-5 text-center text-sm font-semibold text-sky-night"></p>
            </div>
        </div>
    @endif
</x-layouts.app>
