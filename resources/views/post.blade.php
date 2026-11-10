<x-layouts.app :seo="$seo">
    <div class="h-1 bg-gradient-to-r from-pink via-red to-peach" aria-hidden="true"></div>
    <div class="fixed inset-x-0 top-0 z-50 h-[3px] origin-left scale-x-0 bg-red" data-progress aria-hidden="true"></div>
    <x-desk-edit :url="\App\Filament\Resources\Posts\PostResource::getUrl('edit', ['record' => $post], panel: 'desk')" />
    <article class="relative mx-auto max-w-[760px] px-5 pb-10 pt-8" @if ($post->isLive()) data-view="{{ $post->id }}" @endif>
        {{-- D111: wide computer screens only: a tall ad in the empty margin beside the text, starting below the first screen, never over the text --}}
        <div class="absolute left-full top-[85vh] ml-10 hidden h-[calc(100%-85vh)] w-[300px] min-[1440px]:block">
            <div class="sticky top-24"><x-ad-slot spot="sidebar" :height="600" class="!my-0" /></div>
        </div>
        <nav aria-label="অবস্থান" class="flex flex-wrap items-center text-sm text-ink2">
            @foreach (array_slice($crumbs, 0, -1) as $c)
                @if (! $loop->first)<span class="px-1" aria-hidden="true">›</span>@endif
                <a href="{{ $c[1] }}" class="inline-flex min-h-11 items-center hover:text-red">{{ $c[0] }}</a>
            @endforeach
        </nav>
        <div class="mt-4 flex flex-wrap items-center gap-2 text-sm font-semibold">
            @if ($post->section)<a href="{{ $post->section->url() }}" class="rounded-full bg-red px-3 py-1 font-bold text-white">{{ $post->section->name_bn }}</a>@endif
            <span class="text-ink2">{{ \App\Support\Bn::num($post->reading_minutes) }} মিনিট পড়া</span>
            @if ($post->serial_id && $post->serial?->isPublic())<a href="{{ $post->serial->url() }}" class="rounded-full border-2 border-navy px-3 py-0.5 font-bold hover:bg-peach-light">{{ $post->serial->title }} · পর্ব {{ \App\Support\Bn::num((string) $post->episode_no) }}</a>@endif
            @if ($post->isKids() && $post->ageLabel())<span class="rounded-full bg-sky-tint px-3 py-1 font-bold text-sky-night">বয়স {{ $post->ageLabel() }}</span>@endif
        </div>
        <h1 class="mt-4 font-display text-[2.4rem] font-extrabold leading-[1.1] sm:text-5xl sm:leading-[1.05] md:text-6xl" style="font-stretch: 80%">{{ $post->title }}</h1>
        <div class="mt-5 flex items-center gap-3">
            @foreach ($post->authors->take(1) as $a)
                <a href="{{ $a->url() }}" class="flex h-12 w-12 flex-none items-center justify-center rounded-full bg-gradient-to-br from-red to-red-dark text-xl font-extrabold text-white" aria-hidden="true">{{ $a->initial() }}</a>
            @endforeach
            <div class="min-w-0 flex-1">
                <div class="text-lg"><x-byline :post="$post" /></div>
                <div class="text-sm text-ink2">{{ \App\Support\Bn::date($post->published_at) }}@if ($post->issue) · <a href="{{ $post->issue->url() }}" class="underline">{{ $post->issue->title }}</a>@endif</div>
            </div>
            {{-- Share early: readers come from WhatsApp (mobile UX review). The phone's own share sheet when there is one. --}}
            <a href="https://wa.me/?text={{ rawurlencode(($post->share_text ?: $post->title).' — '.$post->url()) }}" data-share="{{ $post->url() }}" data-share-title="{{ $post->title }}"
                rel="noopener" target="_blank" class="flex h-11 w-11 flex-none items-center justify-center rounded-full border-2 border-navy hover:bg-peach-light" aria-label="শেয়ার করুন" title="শেয়ার করুন">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="18" cy="5" r="2.5"/><circle cx="6" cy="12" r="2.5"/><circle cx="18" cy="19" r="2.5"/><path d="m8.2 10.8 7.6-4.4M8.2 13.2l7.6 4.4"/></svg>
            </a>
        </div>

        @if ($post->featured_image_path)
            {{-- Every cover shows (owner, 2026-11-07): never larger than its own size, never taller than the screen. --}}
            <picture>
                @if ($set = \App\Support\Images::srcset('/'.$post->featured_image_path))<source type="image/webp" srcset="{{ $set }}" sizes="(max-width: 760px) 100vw, 720px">@endif
                <img src="{{ asset($post->featured_image_path) }}" alt="{{ $post->featured_image_alt }}" width="{{ $post->featured_image_width }}" height="{{ $post->featured_image_height }}" fetchpriority="high" class="mx-auto mt-7 block h-auto max-h-[80vh] w-auto max-w-full rounded-2xl">
            </picture>
            @if ($post->image_is_ai)<p class="mt-1 text-right text-xs text-ink2">ছবি: এআই-এর সাহায্যে আঁকা</p>@endif
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
        <div class="{{ $post->isPoem() ? 'poem-bn mt-8 text-center'.($post->poemLinesAreParagraphs() ? ' poem-tight' : '') : 'prose-bn mt-6' }}{{ $post->isKids() ? ' kids-read' : '' }}" lang="{{ $post->language }}">{!! \App\Support\Images::renderBody(\App\Support\Embeds::render($post->body_html)) !!}</div>

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

        @if ($post->serial_id && $post->serial?->isPublic())
            {{-- D108: a serial episode ends with "ক্রমশ" (to be continued), as Bengali magazines always printed it --}}
            <div class="my-10 text-center font-hand text-3xl text-red">{{ $post->isLastEpisode() ? 'সমাপ্ত' : 'ক্রমশ…' }}</div>
            <nav class="mb-8 rounded-2xl border-2 border-navy bg-sky-tint p-4" aria-label="ধারাবাহিকের পর্ব">
                <div class="text-sm font-bold text-ink2">ধারাবাহিক: <a href="{{ $post->serial->url() }}" class="underline">{{ $post->serial->title }}</a></div>
                @if ($post->episodeSibling(1))
                    <a href="{{ $post->episodeSibling(1)->url() }}" class="mt-2 flex min-h-14 items-center justify-between gap-3 rounded-xl bg-navy px-4 py-3 font-bold text-white">
                        <span>পরের পর্ব: {{ $post->episodeSibling(1)->episodeLabel() }}</span><span aria-hidden="true">→</span></a>
                @elseif (! $post->isLastEpisode())
                    <p class="mt-2 font-serif text-lg">পরের পর্ব আসবে {{ $post->serial->dayName() }}।</p>
                    <div class="mt-3">@include('serial-follow', ['serial' => $post->serial, 'following' => $post->serial->isFollowedBy(auth()->user())])</div>
                @endif
                <div class="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-sm font-bold">
                    @if ($post->episodeSibling(-1))<a href="{{ $post->episodeSibling(-1)->url() }}" class="inline-flex min-h-11 items-center underline">← আগের পর্ব</a>@endif
                    <a href="{{ $post->serial->url() }}" class="inline-flex min-h-11 items-center underline">সব পর্ব</a>
                </div>
            </nav>
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

        {{-- Reactions (D64): like and save (signed-in readers), share. Work without JavaScript too. --}}
        @php
            $me = auth()->user();
            $liked = $me && \Illuminate\Support\Facades\DB::table('post_likes')->where(['user_id' => $me->id, 'post_id' => $post->id])->exists();
            $saved = $me && \Illuminate\Support\Facades\DB::table('saved_posts')->where(['user_id' => $me->id, 'post_id' => $post->id])->exists();
            $loginNext = \App\Support\Url::to('/login').'?next='.rawurlencode(parse_url($post->url(), PHP_URL_PATH).'#reactions');
        @endphp
        <div id="reactions" class="scroll-mt-24 flex flex-wrap items-center justify-center gap-3 border-y border-rule py-4">
            @auth
                <form method="POST" action="{{ route('like', $post) }}" data-toggle="like">@csrf
                    <button class="flex h-12 items-center gap-2 rounded-full border-2 border-navy px-5 font-bold {{ $liked ? 'bg-red text-white border-red' : '' }}" aria-pressed="{{ $liked ? 'true' : 'false' }}">
                        <span aria-hidden="true">{{ $liked ? '♥' : '♡' }}</span> <span data-label>ভালো লেগেছে</span> <span data-count class="tabular-nums">{{ $post->likes_count ? \App\Support\Bn::num((string) $post->likes_count) : '' }}</span></button>
                </form>
                <form method="POST" action="{{ route('save', $post) }}" data-toggle="save">@csrf
                    <button class="flex h-12 items-center gap-2 rounded-full border-2 border-navy px-5 font-bold {{ $saved ? 'bg-navy text-white' : '' }}" aria-pressed="{{ $saved ? 'true' : 'false' }}"
                        data-on="✓ তালিকায় আছে" data-off="পরে পড়ব">{{ $saved ? '✓ তালিকায় আছে' : 'পরে পড়ব' }}</button>
                </form>
            @else
                <a href="{{ $loginNext }}" class="flex h-12 items-center gap-2 rounded-full border-2 border-navy px-5 font-bold"><span aria-hidden="true">♡</span> ভালো লেগেছে
                    @if ($post->likes_count)<span class="tabular-nums">{{ \App\Support\Bn::num((string) $post->likes_count) }}</span>@endif</a>
                <a href="{{ $loginNext }}" class="flex h-12 items-center rounded-full border-2 border-navy px-5 font-bold">পরে পড়ব</a>
            @endauth
            <a href="https://wa.me/?text={{ rawurlencode(($post->share_text ?: $post->title).' — '.$post->url()) }}" rel="noopener" target="_blank" class="flex h-12 items-center rounded-full bg-red px-5 font-bold text-white">WhatsApp</a>
            <a href="https://www.facebook.com/sharer/sharer.php?u={{ rawurlencode($post->url()) }}" rel="noopener" target="_blank" class="flex h-12 items-center rounded-full border-2 border-navy px-5 font-bold">Facebook</a>
        </div>

        @unless ($post->isKids())
            <x-ad-slot spot="article" :height="250" />
        @endunless

        @foreach ($post->authors as $a)
            <x-author-card :author="$a" />
        @endforeach


        @if ($related->isNotEmpty())
            {{-- "পরের লেখা" right after the writer (mobile UX review): compact rows, the same writer first. --}}
            <section class="mt-10" aria-labelledby="next-h">
                <h2 id="next-h" class="font-display text-3xl font-extrabold">পরের লেখা</h2>
                <ul class="mt-3 divide-y divide-rule border-y border-rule">
                    @foreach ($related as $r)
                        <li><x-post-row :post="$r" :meta="($r->authors->first()?->publicName() ?? '').($r->section ? ' · '.$r->section->name_bn : '')" /></li>
                    @endforeach
                </ul>
            </section>
        @endif

        {{-- D111: the second ad, between "পরের লেখা" and the comments --}}
        <x-ad-slot spot="article_2" :height="250" />
        @unless ($post->isKids())
        {{-- D101: no comments on children's pieces (children's privacy) --}}
        @php
            $writerUsers = $post->authors->pluck('user_id')->filter()->all();
            $me = auth()->user();
        @endphp
        {{-- Readers' comments (D39, D74): who, when, what; then a short form. Plain text, always escaped. --}}
        <section id="comments" class="mt-12 scroll-mt-24" aria-labelledby="comments-h">
            <div class="flex items-baseline justify-between gap-3 border-b-2 border-navy pb-3">
                <h2 id="comments-h" class="font-display text-3xl font-extrabold">পাঠকের মতামত</h2>
                @if ($comments->isNotEmpty())<span class="font-bold text-ink2">{{ \App\Support\Bn::num((string) $comments->count()) }}টি</span>@endif
            </div>

            @if ($comments->isEmpty())
                <p class="mt-4 text-ink2">এখনও কেউ লেখেননি। প্রথম মতামতটি আপনার হোক।</p>
            @else
                <ol class="mt-2">
                    @foreach ($comments as $c)
                        @php($isWriter = $c->user_id && in_array($c->user_id, $writerUsers, true))
                        @php($isDesk = ! $isWriter && $c->user?->hasRole(\App\Enums\Role::Editor))
                        <li id="comment-{{ $c->id }}" class="flex gap-3 border-b border-rule py-5 last:border-0">
                            <span class="flex h-10 w-10 flex-none items-center justify-center rounded-full font-extrabold {{ $isWriter ? 'bg-red text-white' : ($isDesk ? 'bg-navy text-white' : 'bg-peach-light text-red-dark') }}" aria-hidden="true">{{ mb_substr(trim((string) $c->author_name) ?: '?', 0, 1) }}</span>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-baseline gap-x-2">
                                    <span class="font-bold">{{ $c->author_name }}</span>
                                    @if ($isWriter)<span class="rounded-full bg-red px-2 py-0.5 text-xs font-bold text-white">লেখক</span>@endif
                                    @if ($isDesk)<span class="rounded-full bg-navy px-2 py-0.5 text-xs font-bold text-white">হাতপাখা</span>@endif
                                    <span class="text-sm text-ink2">{{ \App\Support\Bn::date($c->created_at) }}</span>
                                </div>
                                <p class="mt-1 whitespace-pre-line break-words leading-relaxed">{{ $c->body }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            @endif

            @if (session('comment_status'))
                <p class="mt-5 rounded-2xl bg-sky-tint p-4 font-semibold" role="status">{{ session('comment_status') }}</p>
            @endif

            {{-- Comment form (D39). Plain text; guests are always checked by an editor. --}}
            <form method="POST" action="{{ route('comment.store', $post) }}" class="mt-6 rounded-3xl border-2 border-navy bg-white p-4 sm:p-5">
                @csrf
                <input type="hidden" name="started" value="{{ \App\Http\Controllers\CommentController::startedToken() }}">
                <div aria-hidden="true" class="absolute -left-[9999px] h-px w-px overflow-hidden"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
                <div class="flex items-start gap-3">
                    <span class="hidden h-10 w-10 flex-none items-center justify-center rounded-full bg-peach-light font-extrabold text-red-dark sm:flex" aria-hidden="true">{{ $me ? mb_substr($me->name, 0, 1) : '✎' }}</span>
                    <div class="min-w-0 flex-1">
                        @auth
                            <p class="text-sm text-ink2"><strong class="text-navy">{{ $me->name }}</strong> হিসেবে লিখছেন</p>
                        @else
                            <label class="block">
                                <span class="mb-1 block text-sm font-bold">আপনার নাম</span>
                                <input name="name" type="text" value="{{ old('name') }}" required minlength="2" maxlength="80" autocomplete="name" placeholder="আপনার নাম"
                                    class="block h-12 w-full rounded-xl border-2 border-navy bg-white px-4 text-lg focus:border-sky-deep">
                            </label>
                        @endauth
                        <label class="mt-2 block">
                            <span class="mb-1 block text-sm font-bold">মতামত</span>
                            <textarea name="body" rows="3" enterkeyhint="send" required minlength="2" maxlength="3000" placeholder="লেখাটি পড়ে কেমন লাগল? দু-এক লাইন লিখুন…"
                                class="block w-full rounded-xl border-2 border-navy bg-paper p-4 text-lg leading-relaxed focus:border-sky-deep focus:bg-white">{{ old('body') }}</textarea>
                        </label>
                        @error('body')<p class="mt-2 font-semibold text-red" role="alert">{{ $message }}</p>@enderror
                        @error('name')<p class="mt-2 font-semibold text-red" role="alert">{{ $message }}</p>@enderror
                        @guest
                            <x-turnstile action="comment" class="mt-2" :lazy="true" appearance="interaction-only" />
                        @endguest
                        <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                            <label class="flex min-h-11 items-center gap-2 text-sm text-ink2">
                                <input type="checkbox" name="not_featured" value="1" class="h-6 w-6 accent-red" @checked(old('not_featured'))>
                                প্রথম পাতার "পাঠকের চিঠি"-তে দেখাবেন না
                            </label>
                            <button class="h-12 rounded-full bg-red px-6 font-bold text-white">মন্তব্য পাঠান</button>
                        </div>
                        <p class="mt-3 text-xs text-ink2">
                            @guest সম্পাদক দেখে প্রকাশ করেন। <a class="font-bold text-sky-deep underline" href="{{ \App\Support\Url::to('/login') }}?next={{ rawurlencode(parse_url($post->url(), PHP_URL_PATH).'#comments') }}">লগ-ইন করে লিখলে</a> তাড়াতাড়ি প্রকাশ পায়। @endguest
                            ভদ্র ও প্রাসঙ্গিক মন্তব্য প্রকাশ পায়। লিংক, ওয়েবসাইট বা ই-মেলের ঠিকানা দেওয়া যায় না; বিজ্ঞাপন বা কটু কথাও নয়।
                        </p>
                    </div>
                </div>
            </form>
        </section>
        @endunless

        <x-newsletter-signup variant="card" />
    </article>

</x-layouts.app>
