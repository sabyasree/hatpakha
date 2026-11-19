{{-- D241 (owner, with two UX reviews: "the scroll is too much... he is not able to find it... first show me the
     design"; then "2nd ad at the end; cards in one line, horizontal scroll"). Everything under a piece, in the order
     a reader needs it:
       1. one card: how the piece left them, the comment box, the two newest comments
       2. sending the piece on, in worded buttons
       3. the first ad, after a clear gap (never against a button)
       4. the writer, in one row
       5. more pieces, as cards in one line that scrolls sideways
       6. every comment
       7. the second ad
       8. the two ways to hear of new pieces
     A children's piece has no comments and no first ad (D101). $post, $related, $comments. --}}
@php
    use App\Support\Bn;
    use App\Support\Feelings;
    use App\Support\Url;
    $me = auth()->user();
    $kids = $post->isKids();
    $saved = $me && \Illuminate\Support\Facades\DB::table('saved_posts')->where(['user_id' => $me->id, 'post_id' => $post->id])->exists();
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
            {{-- A guest's tap is remembered through the login (POST /feel), so it is not lost on the way. --}}
            <form method="POST" action="{{ auth()->check() ? route('like', $post) : Url::to('/feel/'.$post->id) }}" @auth data-toggle="feel" @endauth>@csrf<input type="hidden" name="feeling" value="{{ $key }}">
                <button data-feeling="{{ $key }}" class="flex min-h-12 items-center gap-2 rounded-full border-2 px-4 font-bold {{ $mine === $key ? 'border-red bg-red text-white' : 'border-navy bg-white' }}" aria-pressed="{{ $mine === $key ? 'true' : 'false' }}">
                    <span>{{ $label }}</span> <span data-count class="tabular-nums" @if ($feel[$key]) title="{{ Bn::num((string) $feel[$key]) }} জন" @endif>{{ $feel[$key] ? Bn::num((string) $feel[$key]) : '' }}</span></button>
            </form>
        @endforeach
    </div>
    <p class="mt-2 text-center text-sm text-ink2">
        @if ($post->likes_count){{ Bn::num((string) $post->likes_count) }} জন পাঠক জানিয়েছেন। @endif
        @auth একটি বেছে নিন; আবার টিপলে ফিরিয়ে নেওয়া যায়। @else বোতাম টিপতে একবার লগ ইন লাগে{{ \App\Auth\GoogleSignIn::ready() ? ' (Google দিয়ে এক টিপেই হয়)' : '' }}; তারপর এই লেখাতেই ফিরবেন। @endauth
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
                    class="block w-full rounded-xl border-2 border-navy bg-paper p-3 text-lg leading-relaxed focus:border-sky-deep focus:bg-white">{{ old('body') }}</textarea>
            </label>
            @error('body')<p class="mt-2 font-semibold text-red" role="alert">{{ $message }}</p>@enderror
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
                @guest সম্পাদক দেখে প্রকাশ করেন। <a class="font-bold text-sky-night underline" href="{{ Url::to('/login') }}?next={{ rawurlencode($here.'#reactions') }}">লগ-ইন করে লিখলে</a> তাড়াতাড়ি প্রকাশ পায়। @endguest
                ভদ্র ও প্রাসঙ্গিক মতামত প্রকাশ পায়। লিংক, ওয়েবসাইট বা ই-মেলের ঠিকানা দেওয়া যায় না।
            </p>
        </form>

        {{-- The two newest, so a reader sees that people do write; every comment is further down --}}
        <div class="mt-4 border-t border-rule pt-3">
            @if ($comments->isEmpty())
                <p class="text-ink2">এখনও কেউ লেখেননি। প্রথম মতামতটি আপনার হোক।</p>
            @else
                @foreach ($comments->reverse()->take(2) as $c)
                    @php [$isWriter, $isDesk] = $who($c); @endphp
                    <p class="mt-1 break-words leading-relaxed"><strong>{{ $c->author_name }}</strong>@if ($isWriter) <span class="rounded-full bg-red px-2 py-0.5 text-xs font-bold text-white">লেখক</span>@elseif ($isDesk) <span class="rounded-full bg-navy px-2 py-0.5 text-xs font-bold text-white">হাতপাখা</span>@endif<strong>:</strong> {{ \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/u', ' ', (string) $c->body)), 140) }}</p>
                @endforeach
                <a href="#comments" class="mt-1 inline-flex min-h-11 items-center font-bold text-sky-night underline">{{ Bn::num((string) $comments->count()) }} জন মতামত লিখেছেন, সব পড়ুন ↓</a>
            @endif
        </div>
    @endunless
</section>

{{-- 2. Sending it on: worded buttons; red outline here, so the one solid red button on screen is "মতামত পাঠান" --}}
<div data-md-skip class="mt-6">
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
</div>

{{-- 3. The first ad: a rule and clear space above it, so it is never under a thumb that was aiming at a button --}}
@unless ($kids)
    <div class="mt-10 border-t border-rule pt-2" data-md-skip><x-ad-slot spot="article" :height="250" /></div>
@endunless

{{-- 4. The writer, in one row --}}
@foreach ($post->authors as $a)
    <x-author-card :author="$a" compact />
@endforeach

{{-- 5. More to read: cards in one line (owner: "cards, in one line, horizontal scroll") --}}
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
                            <img src="{{ asset($r->featured_image_path) }}" alt="" loading="lazy" width="300" height="200" class="aspect-[3/2] w-full object-cover">
                        @else
                            <span class="flex aspect-[3/2] w-full items-center justify-center bg-peach-light font-display text-4xl font-extrabold text-red" aria-hidden="true">{{ mb_substr($r->title, 0, 1) }}</span>
                        @endif
                        <span class="block p-3">
                            <span class="block font-serif text-[17px] font-bold leading-snug">{{ $r->title }}</span>
                            <span class="mt-1 block text-sm text-ink2">{{ ($r->authors->first()?->publicName() ?? '').($r->section ? ' · '.$r->section->name_bn : '') }}</span>
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
    </section>
@endif

{{-- 6. Every comment (D39, D74): who, when, what. Plain text, always escaped. --}}
@if (! $kids && $comments->isNotEmpty())
    <section id="comments" class="mt-10 scroll-mt-24" aria-labelledby="comments-h">
        <div class="flex items-baseline justify-between gap-3 border-b-2 border-navy pb-3">
            <h2 id="comments-h" class="font-display text-3xl font-extrabold">পাঠকের মতামত <span class="text-ink2">({{ Bn::num((string) $comments->count()) }})</span></h2>
            <a href="#comment-form" class="inline-flex min-h-11 flex-none items-center font-bold text-sky-night underline">মতামত লিখুন ↑</a>
        </div>
        <ol class="mt-2">
            @foreach ($comments as $c)
                @php [$isWriter, $isDesk] = $who($c); @endphp
                <li id="comment-{{ $c->id }}" class="flex gap-3 border-b border-rule py-5 last:border-0">
                    <span class="flex h-10 w-10 flex-none items-center justify-center rounded-full font-extrabold {{ $isWriter ? 'bg-red text-white' : ($isDesk ? 'bg-navy text-white' : 'bg-peach-light text-red-dark') }}" aria-hidden="true">{{ mb_substr(trim((string) $c->author_name) ?: '?', 0, 1) }}</span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-baseline gap-x-2">
                            <span class="font-bold">{{ $c->author_name }}</span>
                            @if ($isWriter)<span class="rounded-full bg-red px-2 py-0.5 text-xs font-bold text-white">লেখক</span>@endif
                            @if ($isDesk)<span class="rounded-full bg-navy px-2 py-0.5 text-xs font-bold text-white">হাতপাখা</span>@endif
                            <span class="text-sm text-ink2">{{ Bn::date($c->created_at) }}</span>
                        </div>
                        <p class="mt-1 whitespace-pre-line break-words leading-relaxed">{{ $c->body }}</p>
                    </div>
                </li>
            @endforeach
        </ol>
    </section>
@elseif (! $kids)
    <span id="comments" class="block scroll-mt-24"></span>   {{-- old links to #comments still land near the form --}}
@endif

{{-- 7. The second ad, at the end (owner's choice, 2026-11-19; D111 had it above the comments) --}}
<x-ad-slot spot="article_2" :height="250" />

{{-- 8. Two ways to hear of new pieces, together --}}
<section class="mt-10" data-md-skip aria-labelledby="news-h">
    <h2 id="news-h" class="font-display text-2xl font-extrabold">নতুন লেখা বেরোলে খবর পান</h2>
    @if (! $kids && filled($wa))
        <a href="{{ $wa }}" rel="noopener" target="_blank" class="mt-3 flex min-h-14 flex-wrap items-center justify-between gap-3 rounded-2xl bg-navy px-5 py-4 text-white">
            <span><span class="block text-lg font-bold leading-snug text-white">হোয়াটসঅ্যাপ চ্যানেল</span><span class="block text-sm text-white">আপনার নম্বর কেউ দেখতে পায় না।</span></span>
            <span class="flex min-h-11 flex-none items-center rounded-full bg-paper px-5 font-bold text-navy">হোয়াটসঅ্যাপে খবর নিন →</span></a>
    @endif
    <x-newsletter-signup variant="card" />
</section>
