{{-- D241 (owner, with two UX reviews: "the scroll is too much... he is not able to find it... first show me the
     design"; then "2nd ad at the end; cards in one line, horizontal scroll"). Everything under a piece, in the order
     a reader needs it:
       1. one card: how the piece left them, the comment box, and the way to the comments
       2. more pieces, as cards in one line that scrolls sideways (A5: right after the card)
       3. sending the piece on, in worded buttons
       4. the first ad, after a clear gap (never against a button)
       5. the writer, in one row
       6. every comment (shown once: A5 dropped the two-comment preview from the card)
       7. the second ad
       8. ONE way to hear of new pieces (A5): the WhatsApp channel, with e-mail folded under "ই-মেলে চান?"
     A children's piece has no comments and no first ad (D101). $post, $related, $comments. --}}
@php
    use App\Support\Bn;
    use App\Support\Feelings;
    use App\Support\Url;
    $me = auth()->user();
    $kids = $post->isKids();
    $saved ??= $me && \Illuminate\Support\Facades\DB::table('saved_posts')->where(['user_id' => $me->id, 'post_id' => $post->id])->exists();
    $here = parse_url($post->url(), PHP_URL_PATH);
    $loginNext = Url::to('/login').'?next='.rawurlencode($here.'#reactions');
    $mine = Feelings::mine($me, $post);
    $feel = Feelings::counts($post);
    $writerUsers = $post->authors->pluck('user_id')->filter()->all();
    $who = function ($c) use ($writerUsers) {
        $isWriter = $c->user_id && in_array($c->user_id, $writerUsers, true);

        return [$isWriter, ! $isWriter && $c->user?->hasRole(\App\Enums\Role::Editor)];
    };
    $wa = config('services.social.whatsapp');
@endphp

{{-- 1. The response card --}}
<section id="reactions" data-md-skip class="mt-8 scroll-mt-24 rounded-3xl border-2 border-navy bg-white p-4 shadow-[4px_4px_0_#13212C] sm:p-6" aria-labelledby="reactions-h">
    <h2 id="reactions-h" class="text-center font-hand text-3xl font-normal text-red">পড়ে কেমন লাগল?</h2>
    <div class="mt-3 flex flex-wrap items-center justify-center gap-2" data-feelings>
        @foreach (Feelings::LIST as $key => [$label])
            {{-- D252: a reader without an account taps too, no sign-in (POST /feel, once a phone); the phone remembers which --}}
            <form method="POST" action="{{ auth()->check() ? route('like', $post) : Url::to('/feel/'.$post->id) }}" data-toggle="feel" @guest data-guest="{{ $post->id }}" @endguest>@csrf<input type="hidden" name="feeling" value="{{ $key }}">
                <button data-feeling="{{ $key }}" class="flex min-h-12 items-center gap-2 rounded-full border-2 px-4 font-bold {{ $mine === $key ? 'border-red bg-red text-white' : 'border-navy bg-white' }}" aria-pressed="{{ $mine === $key ? 'true' : 'false' }}">
                    <span>{{ $label }}</span> <span data-count class="tabular-nums" @if ($feel[$key]) title="{{ Bn::num((string) $feel[$key]) }} জন" @endif>{{ $feel[$key] ? Bn::num((string) $feel[$key]) : '' }}</span></button>
            </form>
        @endforeach
    </div>
    <p class="mt-2 text-center text-sm text-ink2">
        @if ($post->likes_count){{ Bn::num((string) $post->likes_count) }} জন পাঠক জানিয়েছেন। @endif
        একটি বেছে নিন; আবার টিপলে ফিরিয়ে নেওয়া যায়।
    </p>

    @unless ($kids)
        {{-- Shown the moment a feeling is chosen: the best time to ask for a line (nothing is focused, nothing jumps) --}}
        <p data-feel-thanks @if (! session('felt')) hidden @endif class="mt-3 rounded-xl bg-sky-tint p-3 font-semibold text-sky-night" role="status">ধন্যবাদ! আপনার কথাটা লেখকের কাছে পৌঁছবে। কোন জায়গাটা সবচেয়ে ভালো লাগল, নিচে দু লাইনে লিখুন।</p>
        @if (session('comment_status'))
            <p class="mt-3 rounded-xl bg-sky-tint p-3 font-semibold" role="status">{{ session('comment_status') }}</p>
        @endif

        {{-- Comment form (D39). Plain text; guests write with a name only and are always checked by an editor. --}}
        <form id="comment-form" method="POST" action="{{ route('comment.store', $post) }}" class="mt-4 scroll-mt-24">
            @csrf
            <input type="hidden" name="started" value="{{ \App\Http\Controllers\CommentController::startedToken() }}">
            <div aria-hidden="true" class="absolute -left-[9999px] h-px w-px overflow-hidden"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
            <label class="block">
                <span class="mb-1 block text-lg font-bold">লেখককে দু লাইন লিখুন</span>
                <textarea name="body" rows="2" enterkeyhint="send" required minlength="2" maxlength="3000" placeholder="লেখাটি পড়ে কেমন লাগল? এখানে লিখুন…"
                    class="block w-full rounded-xl border-2 border-navy bg-paper p-3 text-lg leading-relaxed focus:border-sky-deep focus:bg-white">{{ old('parent_id') ? '' : old('body') }}</textarea>
            </label>
            @if (! old('parent_id'))@error('body')<p class="mt-2 font-semibold text-red" role="alert">{{ $message }}</p>@enderror @endif
            @auth
                <p class="mt-2 text-sm text-ink2"><strong class="text-navy">{{ $me->name }}</strong> হিসেবে লিখছেন</p>
            @else
                <label class="mt-2 block">
                    <span class="sr-only">আপনার নাম</span>
                    <input name="name" type="text" value="{{ old('name') }}" required minlength="2" maxlength="80" autocomplete="name" placeholder="আপনার নাম"
                        class="block h-12 w-full rounded-xl border-2 border-navy bg-paper px-4 text-lg focus:border-sky-deep focus:bg-white">
                </label>
                @error('name')<p class="mt-2 font-semibold text-red" role="alert">{{ $message }}</p>@enderror
                <x-turnstile action="comment" class="mt-2" :lazy="true" appearance="interaction-only" />
            @endauth
            <label class="mt-1 flex min-h-11 items-center gap-2 text-sm text-ink2">
                <input type="checkbox" name="not_featured" value="1" class="h-6 w-6 accent-red" @checked(old('not_featured'))>
                প্রথম পাতার "পাঠকের চিঠি"-তে দেখাবেন না
            </label>
            <button class="mt-1 flex h-12 w-full items-center justify-center rounded-full bg-red px-6 text-lg font-bold text-white shadow-[3px_3px_0_#13212C]">মতামত পাঠান</button>
            <p class="mt-2 text-center text-xs text-ink2">
                @guest সম্পাদক দেখে প্রকাশ করেন। <a class="inline-flex min-h-11 items-center font-bold text-sky-night underline" href="{{ Url::to('/login') }}?next={{ rawurlencode($here.'#reactions') }}">লগ-ইন করে লিখলে</a> তাড়াতাড়ি প্রকাশ পায়। @endguest
                ভদ্র ও প্রাসঙ্গিক মতামত প্রকাশ পায়। লিংক, ওয়েবসাইট বা ই-মেলের ঠিকানা দেওয়া যায় না।
            </p>
        </form>

        {{-- A5 (research 2026-11): the comments are shown once, in the list further down; here only the way there --}}
        <div class="mt-4 border-t border-rule pt-3">
            @if ($comments->isEmpty())
                <p class="text-ink2">এখনও কেউ লেখেননি। প্রথম মতামতটি আপনার হোক।</p>
            @else
                <a href="#comments" class="inline-flex min-h-11 items-center font-bold text-sky-night underline">{{ Bn::num((string) ($comments->count() + $comments->sum(fn ($c) => $c->replies->count()))) }}টি মতামত পড়ুন ↓</a>
            @endif
        </div>
    @endunless
</section>

{{-- 2. More to read, right after the card (A5): cards in one line (owner: "cards, in one line, horizontal scroll") --}}
@if ($related->isNotEmpty())
    <section class="mt-10" aria-labelledby="next-h">
        <div class="flex items-baseline justify-between gap-3">
            <h2 id="next-h" class="font-display text-3xl font-extrabold">পরের লেখা</h2>
            @if ($related->count() > 2)<span class="text-sm font-bold text-ink2 sm:hidden" aria-hidden="true">পাশে সরান →</span>@endif
        </div>
        <ul class="-mx-5 mt-3 flex snap-x snap-mandatory gap-3 overflow-x-auto px-5 pb-3" tabindex="0" aria-label="পরের লেখা">
            @foreach ($related as $r)
                <li class="w-[44%] flex-none snap-start sm:w-[calc(25%-.6rem)]">
                    <a href="{{ $r->url() }}" class="lift block h-full overflow-hidden rounded-2xl border-2 border-navy bg-white hover:bg-peach-light">
                        @if ($r->hasCardImage())
                            <picture class="contents">{!! \App\Support\Images::source($r->featured_image_path, '300px') !!}<img src="{{ asset($r->featured_image_path) }}" alt="" loading="lazy" width="300" height="200" class="aspect-[3/2] w-full object-cover"></picture>
                        @else
                            {{-- A7: no picture: the house title card (the title is on it), never a lone letter --}}
                            <x-title-card :post="$r" class="!aspect-[3/2] !rounded-none border-b-2 border-navy" />
                        @endif
                        <span class="block p-3">
                            @if ($r->hasCardImage())<span class="block font-serif text-[17px] font-bold leading-snug">{{ $r->title }}</span>@endif
                            <span class="mt-1 block text-sm text-ink2">{{ ($r->authors->first()?->publicName() ?? '').($r->section ? ' · '.$r->section->name_bn : '') }}</span>
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
    </section>
@endif

{{-- 3. Sending it on: worded buttons; red outline here, so the one solid red button on screen is "মতামত পাঠান" --}}
<div data-md-skip id="share" data-share-block class="mt-6 scroll-mt-24 rounded-2xl transition-shadow">
    <p class="text-center text-sm font-bold text-ink2">ভালো লাগলে বন্ধুকে পাঠান</p>
    <a href="https://wa.me/?text={{ rawurlencode(($post->share_text ?: $post->title).' — '.$post->url()) }}" rel="noopener" target="_blank" class="mt-2 flex h-12 w-full items-center justify-center rounded-full border-2 border-red bg-white px-5 text-lg font-bold text-red">হোয়াটসঅ্যাপে পাঠান</a>
    <div class="mt-2 grid grid-cols-2 gap-2">
        <a href="https://www.facebook.com/sharer/sharer.php?u={{ rawurlencode($post->url()) }}" rel="noopener" target="_blank" class="flex h-12 items-center justify-center rounded-full border-2 border-navy bg-white px-3 font-bold">ফেসবুকে শেয়ার</a>
        @auth
            <form method="POST" action="{{ route('save', $post) }}" data-toggle="save">@csrf
                <button class="flex h-12 w-full items-center justify-center rounded-full border-2 border-navy px-3 font-bold {{ $saved ? 'bg-navy text-white' : 'bg-white' }}" aria-pressed="{{ $saved ? 'true' : 'false' }}"
                    data-on="✓ তালিকায় আছে" data-off="পরে পড়ব">{{ $saved ? '✓ তালিকায় আছে' : 'পরে পড়ব' }}</button>
            </form>
        @else
            <a href="{{ $loginNext }}" class="flex h-12 items-center justify-center rounded-full border-2 border-navy bg-white px-3 font-bold">পরে পড়ব</a>
        @endauth
    </div>
    @if ($post->isLive() && ! $kids)
        <p class="mt-3 text-center text-sm text-ink2"><span aria-hidden="true" class="font-serif text-lg text-red">❝</span> কোনো লাইন ভালো লাগল? আঙুল চেপে ধরে লাইনটা বেছে নিন, তারপর “এই লাইন শেয়ার করুন” টিপুন। হাতপাখার কার্ড হয়ে যাবে।</p>
    @endif
    <a href="{{ Url::to('/pathoker-pochhondo') }}" class="mx-auto flex min-h-11 w-fit items-center font-bold text-sky-night underline">পাঠকের পছন্দের লেখা দেখুন</a>
    {{-- D330 (UX panel): one quiet way from a piece into the games --}}
    @unless ($kids)
        @php
            $hpQuiz = \App\Support\WeeklyQuiz::live();
        @endphp
        <a href="{{ Url::to($hpQuiz ? '/khela/quiz' : '/khela/dhadha') }}" class="mx-auto flex min-h-11 w-fit items-center text-center font-bold text-sky-night underline">{{ $hpQuiz ? 'এ সপ্তাহের লেখা থেকে পাঁচটা প্রশ্ন: পত্রিকা-কুইজ →' : 'পড়া শেষ? আজকের ধাঁধাটা একবার দেখবেন? →' }}</a>
    @endunless
</div>

{{-- 4. The first ad: a rule and clear space above it, so it is never under a thumb that was aiming at a button --}}
@unless ($kids)
    <div class="mt-10 border-t border-rule pt-2" data-md-skip><x-ad-slot spot="article" :height="250" /></div>
@endunless

{{-- 5. The writer, in one row --}}
@foreach ($post->authors as $a)
    <x-author-card :author="$a" compact />
@endforeach


{{-- 6. Every comment (D39, D74): who, when, what. Plain text, always escaped.
     D252: each with its published replies under it, and "উত্তর দিন" (a fold-out box: works without JavaScript). --}}
@if (! $kids && $comments->isNotEmpty())
    @php
        $total = $comments->count() + $comments->sum(fn ($c) => $c->replies->count());
        $replied = session('reply_status');
    @endphp
    <section id="comments" class="mt-10 scroll-mt-24" aria-labelledby="comments-h">
        <div class="flex items-baseline justify-between gap-3 border-b-2 border-navy pb-3">
            <h2 id="comments-h" class="font-display text-3xl font-extrabold">পাঠকের মতামত <span class="text-ink2">({{ Bn::num((string) $total) }})</span></h2>
            <a href="#comment-form" class="inline-flex min-h-11 flex-none items-center font-bold text-sky-night underline">মতামত লিখুন ↑</a>
        </div>
        <ol class="mt-2">
            @foreach ($comments as $c)
                @php [$isWriter, $isDesk] = $who($c); @endphp
                <li id="comment-{{ $c->id }}" class="scroll-mt-24 border-b border-rule py-5 last:border-0 target:rounded-xl target:border-l-4 target:border-navy target:bg-sky-tint target:px-3">
                    @include('partials.comment-one', ['c' => $c, 'isWriter' => $isWriter, 'isDesk' => $isDesk, 'small' => false])
                    @if ($c->replies->isNotEmpty())
                        <ol class="ml-6 mt-3 border-l-4 border-peach pl-4" aria-label="উত্তর">
                            @foreach ($c->replies as $r)
                                @php [$rWriter, $rDesk] = $who($r); @endphp
                                <li id="comment-{{ $r->id }}" class="scroll-mt-24 py-2 target:rounded-xl target:border-l-4 target:border-navy target:bg-sky-tint target:px-3">
                                    @include('partials.comment-one', ['c' => $r, 'isWriter' => $rWriter, 'isDesk' => $rDesk, 'small' => true])
                                </li>
                            @endforeach
                        </ol>
                    @endif
                    @if (is_array($replied) && (int) ($replied['to'] ?? 0) === $c->id)
                        <p class="ml-6 mt-3 rounded-xl bg-sky-tint p-3 font-semibold" role="status">{{ $replied['text'] }}</p>
                    @endif
                    {{-- reply box --}}
                    @php($mineFailed = (int) old('parent_id') === $c->id && $errors->any())
                    <details class="ml-6 mt-2 group" @if ($mineFailed) open @endif>
                        <summary class="inline-flex min-h-11 cursor-pointer list-none items-center rounded-full border-2 border-navy px-4 font-bold hover:bg-peach-light">উত্তর দিন</summary>
                        <form method="POST" action="{{ route('comment.store', $post) }}" class="mt-3">
                            @csrf
                            <input type="hidden" name="parent_id" value="{{ $c->id }}">
                            <input type="hidden" name="started" value="{{ \App\Http\Controllers\CommentController::startedToken() }}">
                            <div aria-hidden="true" class="absolute -left-[9999px] h-px w-px overflow-hidden"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
                            @if (! $me && $writerUsers)
                                <p class="mb-2 rounded-xl bg-peach-light p-3 text-sm">লেখক? লেখক হিসেবে উত্তর দিতে আগে <a href="{{ Url::to('/login') }}?next={{ rawurlencode($here.'#comment-'.$c->id) }}" class="font-bold text-sky-night underline">লগ-ইন করুন</a>।</p>
                            @endif
                            @auth<p class="mb-2 text-sm text-ink2"><strong class="text-navy">{{ $me->name }}</strong>@if (in_array($me->id, $writerUsers, true)) (লেখক)@endif হিসেবে লিখছেন</p>@endauth
                            <label class="block"><span class="sr-only">আপনার উত্তর</span>
                                <textarea name="body" rows="2" required minlength="2" maxlength="3000" placeholder="{{ $c->author_name }}-কে উত্তর লিখুন…"
                                    class="block w-full rounded-xl border-2 border-navy bg-paper p-3 text-lg leading-relaxed focus:border-sky-deep focus:bg-white">{{ $mineFailed ? old('body') : '' }}</textarea>
                            </label>
                            @if ($mineFailed)@error('body')<p class="mt-2 font-semibold text-red" role="alert">{{ $message }}</p>@enderror @endif
                            @guest
                                <label class="mt-2 block"><span class="sr-only">আপনার নাম</span>
                                    <input name="name" type="text" required minlength="2" maxlength="80" autocomplete="name" placeholder="আপনার নাম" class="block h-12 w-full rounded-xl border-2 border-navy bg-paper px-4 text-lg focus:border-sky-deep focus:bg-white">
                                </label>
                                <x-turnstile action="comment" class="mt-2" :lazy="true" appearance="interaction-only" />
                            @endguest
                            <div class="mt-2 flex flex-wrap items-center gap-3">
                                <button class="flex h-12 flex-1 items-center justify-center rounded-full bg-red px-6 text-lg font-bold text-white shadow-[3px_3px_0_#13212C] sm:flex-none">উত্তর পাঠান</button>
                                <button type="button" data-close-details class="flex h-12 items-center px-3 font-bold text-ink2 underline">বাতিল</button>
                            </div>
                            <p class="mt-2 text-xs text-ink2">@guest সম্পাদক দেখে প্রকাশ করেন। কেউ উত্তর দিলে ই-মেলে খবর পেতে চান? আগে <a class="font-bold text-sky-night underline" href="{{ Url::to('/login') }}?next={{ rawurlencode($here.'#comment-'.$c->id) }}">লগ-ইন করে</a> লিখুন। @else কেউ আপনার উত্তরের পরে লিখলে ই-মেলে খবর পাবেন। @endguest</p>
                        </form>
                    </details>
                </li>
            @endforeach
        </ol>
    </section>
@elseif (! $kids)
    <span id="comments" class="block scroll-mt-24"></span>   {{-- old links to #comments still land near the form --}}
@endif

{{-- 7. The second ad, at the end (owner's choice, 2026-11-19; D111 had it above the comments) --}}
<x-ad-slot spot="article_2" :height="250" />

{{-- 8. One way to hear of new pieces (A5). The WhatsApp channel when there is one (not for children's pieces); e-mail
     stays one tap away, folded (opened again after a sign-up from it, to show the answer). Without a channel, the e-mail card. --}}
<section class="mt-10" data-md-skip aria-labelledby="news-h">
    <h2 id="news-h" class="font-display text-2xl font-extrabold">নতুন লেখা বেরোলে খবর পান</h2>
    @if (! $kids && filled($wa))
        <a href="{{ $wa }}" rel="noopener" target="_blank" class="mt-3 flex min-h-14 flex-wrap items-center justify-between gap-3 rounded-2xl bg-navy px-5 py-4 text-white">
            <span><span class="block text-lg font-bold leading-snug text-white">হোয়াটসঅ্যাপ চ্যানেল</span><span class="block text-sm text-white">আপনার নম্বর কেউ দেখতে পায় না।</span></span>
            <span class="flex min-h-11 flex-none items-center rounded-full bg-paper px-5 font-bold text-navy">হোয়াটসঅ্যাপে খবর নিন →</span></a>
        <details class="group mt-2" data-mail-fold @if (session('newsletter_from') === 'card' || ($errors->has('email') && old('from') === 'card')) open @endif>
            <summary class="inline-flex min-h-11 cursor-pointer list-none items-center gap-1 font-bold text-sky-night underline [&::-webkit-details-marker]:hidden">{{ $me && ! $me->mail_opt_out ? 'ই-মেলের চিঠি' : 'ই-মেলে চান?' }} <span class="transition group-open:rotate-90" aria-hidden="true">›</span></summary>
            <x-newsletter-signup variant="card" />
        </details>
    @else
        <x-newsletter-signup variant="card" />
    @endif
</section>
