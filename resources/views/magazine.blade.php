{{-- The issue as a magazine (owner, 2026-11-12): full screen, pages that turn in 3D (public/js/hp-magazine.js).
     Cover, inside cover, contents with page numbers, editorial, section openers, pieces, the issue's writers, back cover.
     Without JavaScript the same content reads as one long page. Only real data: titles, writers, bios, the editor's note. --}}
@php
    use App\Support\Bn;
    // An issue by default; a collection (বর্ষসেরা) passes its own words (App\Support\MagazineBook::WORDS).
    $w = ($words ?? []) + ['in' => 'এই সংখ্যায়', 'of' => 'এই সংখ্যার', 'back' => 'সংখ্যার পাতায় ফিরুন', 'ed' => 'সম্পাদকীয়', 'note' => 'সম্পাদকের কথা'];
    $bySection = $rest->groupBy(fn ($p) => $p->section?->name_bn ?? 'অন্যান্য');
    $coverLines = $rest->take(3);
@endphp
<!doctype html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, follow">
    <meta name="theme-color" content="#13212C">
    <title>{{ $issue->title }} · হাতপাখা</title>
    <link rel="canonical" href="{{ $issue->url() }}">
    <link rel="icon" href="{{ asset('images/logo.png') }}">
    <link rel="stylesheet" href="{{ asset('fonts/fonts.css') }}?v=1">
    @vite(['resources/css/app.css'])
    <style>
        html, body { height: 100%; }
        body { margin: 0; background: #13212C; color: #13212C; overflow: hidden; }
        .mag { position: fixed; inset: 0; display: flex; flex-direction: column; }
        .mag-preview { background: #EFC08D; color: #13212C; font: 700 .9rem/1.4 'Anek Bangla', sans-serif; text-align: center; padding: .45rem 1rem; }
        .mag-bar { display: flex; align-items: center; gap: .5rem; padding: .5rem max(.75rem, env(safe-area-inset-left)); color: #FFFBF4; min-height: 52px; }
        .mag-bar a, .mag-bar button { color: #FFFBF4; border: 2px solid rgba(255,251,244,.35); border-radius: 999px; min-height: 44px; min-width: 44px; padding: 0 .8rem; font-weight: 700; background: transparent; display: inline-flex; align-items: center; justify-content: center; gap: .3rem; }
        .mag-bar .mag-title { flex: 1; min-width: 0; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; text-align: center; }
        .mag-stage { flex: 1; display: flex; align-items: center; justify-content: center; overflow: hidden; }
        .mag-nav { display: flex; align-items: center; justify-content: center; gap: .75rem; padding: .4rem 0 max(.6rem, env(safe-area-inset-bottom)); color: #FFFBF4; }
        .mag-nav button { min-height: 44px; padding: 0 1.2rem; border-radius: 999px; font-weight: 700; }
        .mag-nav [data-mag-prev] { border: 2px solid rgba(255,251,244,.5); color: #FFFBF4; }
        .mag-nav [data-mag-next] { background: #CB1B32; color: #fff; }
        .mag-nav button:disabled { opacity: .4; cursor: default; }
        .mag-wide { display: none; }
        @media (min-width: 760px) { .mag-wide { display: inline; } .mag-narrow { display: none; } }
        .mag-hint { display: flex; align-items: center; justify-content: center; gap: .75rem; padding: .5rem 1rem; background: #FBEBD8; color: #13212C; font-weight: 700; text-align: center; }
        .mag-hint button { min-height: 44px; min-width: 44px; border: 2px solid #13212C; border-radius: 999px; background: transparent; color: #13212C; font-weight: 700; padding: 0 .9rem; }
        .mag-count { min-width: 9rem; text-align: center; font-variant-numeric: tabular-nums; font-size: .9rem; }

        /* The book and its leaves */
        .mag-book { position: relative; width: var(--pw); height: var(--ph); perspective: 2600px; transition: transform .6s ease; }
        .mag-book.mag-spread { width: calc(var(--pw) * 2); }
        .mag-book.mag-closed-front { transform: translateX(-25%); }
        .mag-book.mag-closed-back { transform: translateX(25%); }
        .mag-leaf { position: absolute; top: 0; left: 0; width: 100%; height: 100%; transform-style: preserve-3d; transform-origin: left center; transition: transform .65s cubic-bezier(.45,.05,.25,1); cursor: pointer; }
        .mag-spread .mag-leaf { left: 50%; width: 50%; }
        .mag-leaf.turned { transform: rotateY(-180deg); }
        .mag-leaf.gone { visibility: hidden; }
        .mag-leaf.far { display: none; }
        .mag-face { position: absolute; inset: 0; backface-visibility: hidden; -webkit-backface-visibility: hidden; overflow: hidden; background: #FFFBF4; box-shadow: 0 10px 30px rgba(0,0,0,.35); }
        @media (max-width: 759px) { .mag-face { box-shadow: 0 4px 14px rgba(0,0,0,.3); } }
        .mag-back-face { transform: rotateY(180deg); }
        .mag-front::after { content: ''; position: absolute; inset: 0 auto 0 0; width: 26px; background: linear-gradient(90deg, rgba(19,33,44,.22), transparent); pointer-events: none; }
        .mag-back-face::after { content: ''; position: absolute; inset: 0 0 0 auto; width: 26px; background: linear-gradient(270deg, rgba(19,33,44,.22), transparent); pointer-events: none; }
        @media (prefers-reduced-motion: reduce) { .mag-leaf, .mag-book { transition: none; } }

        /* A page */
        .mag-page { width: var(--pw); height: var(--ph); box-sizing: border-box; display: flex; flex-direction: column; padding: 5.5% 8% 4.5%; font-family: 'Noto Serif Bengali', serif; line-height: 1.75; }
        .mag-stage-measure { position: absolute; left: -99999px; top: 0; visibility: hidden; }
        .mag-head { display: flex; justify-content: space-between; gap: 1rem; font: 600 .68em 'Anek Bangla', sans-serif; color: #4D5A63; border-bottom: 1px solid #E8DCCB; padding-bottom: .4em; margin-bottom: 1.1em; }
        .mag-head span:last-child { color: #CB1B32; }
        .mag-body { flex: 1; overflow: hidden; min-height: 0; }
        .mag-foot { display: grid; grid-template-columns: 1fr auto 1fr; align-items: baseline; gap: .5em; font: 600 .72em 'Anek Bangla', sans-serif; color: #4D5A63; padding-top: .6em; border-top: 1px solid #E8DCCB; margin-top: .3em; }
        .mag-foot .mag-pn { text-align: center; color: #13212C; font-size: 1.1em; }
        .mag-foot .mag-issn { text-align: right; font-size: .9em; }
        .mag-brand { font-family: 'Galada', cursive; font-weight: 400; color: #CB1B32; font-size: 1.25em; line-height: 1; }
        /* Pages without header and footer still carry the name and the ISSN, small, in a corner */
        .mag-mark { position: absolute; left: 8%; bottom: 3.5%; display: flex; gap: .6em; align-items: baseline; font: 600 .62em 'Anek Bangla', sans-serif; color: #4D5A63; z-index: 2; }
        .mag-mark.on-dark { color: #EFC08D; }
        .mag-mark.on-dark .mag-brand { color: #FFFBF4; }
        .mag-blank .mag-body { position: relative; height: 100%; }
        .mag-body p { margin: 0 0 .85em; }
        .mag-body p.mag-cont { text-indent: 0; }
        .mag-kicker { font: 700 .75em 'Anek Bangla', sans-serif; color: #CB1B32; letter-spacing: .02em; margin: 0 0 .3em !important; }
        .mag-h { font: 800 1.75em/1.25 'Anek Bangla', sans-serif; margin: 0 0 .25em; }
        .mag-by { color: #4D5A63; margin: 0 0 1.2em !important; font-family: 'Anek Bangla', sans-serif; }
        .mag-by::after { content: ''; display: block; width: 3em; border-bottom: 3px solid #CB1B32; margin-top: .6em; }
        .mag-poem .mag-body { text-align: center; }
        /* Pictures, placed by the page designer (App\Support\PageDesigner). Every picture has its exact shape, so pages
           are laid out before pictures arrive; none is taller than half a page inside the text. */
        .mag-body figure { margin: .3em 0 1em; text-align: center; }
        .mag-body figure img { display: block; margin: 0 auto; height: auto; border-radius: 2px; background: #FBEBD8; object-fit: cover; }
        .mag-body figcaption { font: .72em/1.5 'Anek Bangla', sans-serif; color: #4D5A63; margin-top: .45em; text-align: center; }
        .mag-fig-small img { max-width: 45%; }
        .mag-credit { font: .62em 'Anek Bangla', sans-serif; color: #4D5A63; text-align: right; margin: .3em 0 0 !important; }
        .mag-hero { margin: 0 0 1.1em !important; }
        .mag-hero img { width: 100% !important; aspect-ratio: auto !important; height: calc(var(--ph) * .34) !important; object-fit: cover; border-radius: 0; }
        .mag-poem .mag-hero img { height: calc(var(--ph) * .26) !important; }
        .mag-vignette { margin: 0 0 1em !important; }
        .mag-vignette img { border-radius: 50% / 46%; box-shadow: 0 0 0 6px #FBEBD8; }
        .mag-poem .mag-vignette img { border-radius: 3px; box-shadow: none; }
        .mag-page.mag-feature { padding: 0; }
        .mag-page.mag-feature .mag-body { height: 100%; }
        .mag-feat-in { position: relative; height: 100%; background: #13212C; overflow: hidden; }
        .mag-feat-in .mag-feature-img { position: absolute; inset: 0; width: 100% !important; height: 100% !important; max-height: none; aspect-ratio: auto !important; object-fit: cover; border-radius: 0; }
        .mag-feature-panel { position: absolute; left: 0; right: 0; bottom: 0; background: #13212C; color: #FFFBF4; padding: 1.1em 8% 1.4em; }
        .mag-feature-panel .mag-h { color: #FFFBF4; font-size: 1.9em; }
        .mag-feature-panel .mag-kicker { color: #F08D90; }
        .mag-feature-by { font-family: 'Anek Bangla', sans-serif; color: #EFC08D; margin: .2em 0 0 !important; }
        .mag-feature-panel .mag-credit { color: #EFC08D; text-align: left; }
        .mag-poem .mag-by::after { margin-left: auto; margin-right: auto; }

        /* Cover, inside cover, openers, back cover */
        .mag-cover, .mag-back, .mag-opener { padding: 0; }
        .mag-cover .mag-body, .mag-back .mag-body, .mag-opener .mag-body { height: 100%; }
        .mag-cover-in { position: relative; height: 100%; color: #fff; background: #13212C; overflow: hidden; display: flex; flex-direction: column; justify-content: space-between; padding: 7% 8%; box-sizing: border-box; }
        .mag-cover-in img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
        .mag-cover-in::after { content: ''; position: absolute; inset: 0; background: linear-gradient(180deg, rgba(19,33,44,.55) 0%, rgba(19,33,44,0) 35%, rgba(19,33,44,0) 55%, rgba(19,33,44,.85) 100%); }
        .mag-cover-in > * { position: relative; z-index: 1; }
        .mag-mast { font-family: 'Galada', cursive; font-size: 4.2em; line-height: 1; color: #fff; margin: 0; }
        .mag-mast-sub { font: 600 .72em 'Anek Bangla', sans-serif; color: #EFC08D; margin: .2em 0 0; }
        .mag-cover-title { font: 800 1.6em/1.2 'Anek Bangla', sans-serif; margin: 0; }
        .mag-cover-lines { list-style: none; padding: 0; margin: .6em 0 0; font-family: 'Anek Bangla', sans-serif; }
        .mag-cover-lines li { border-top: 1px solid rgba(255,251,244,.35); padding: .35em 0; }
        .mag-cover-lines b { display: block; font-size: 1.05em; }
        .mag-cover-lines span { color: #EFC08D; font-size: .85em; }
        /* UX review: cover lines sit on a navy panel, never on the pink fan or a photo */
        .mag-cover-in > div:last-child { background: #13212C; margin: 0 calc(-8% - 4px) -8%; padding: 6% calc(8% + 4px) 8% 8%; }
        .mag-fanbg { position: absolute; right: -30%; top: 30%; width: 80%; aspect-ratio: 1; border-radius: 50%; background: repeating-conic-gradient(#CB1B32 0 7deg, #F08D90 7deg 14deg); opacity: .9; }
        .mag-inside .mag-body { display: flex; flex-direction: column; justify-content: center; text-align: center; font-family: 'Anek Bangla', sans-serif; }
        .mag-opener-in { position: relative; height: 100%; background: #FBEBD8; display: flex; flex-direction: column; justify-content: flex-end; padding: 10% 9%; box-sizing: border-box; }
        .mag-opener-ed { background: #13212C; } .mag-opener-ed h2 { color: #FFFBF4; } .mag-opener-ed .mag-mark { color: #EFC08D; } .mag-opener-ed .mag-mark .mag-brand { color: #FFFBF4; }
        .mag-opener-in p { margin: 0; font: 700 .9em 'Anek Bangla', sans-serif; color: #CB1B32; }
        .mag-opener-in.mag-opener-ed p { color: #F08D90; }
        .mag-opener-in h2 { margin: .1em 0 0; font: 800 2.6em/1.1 'Anek Bangla', sans-serif; }
        .mag-back-in { height: 100%; background: #13212C; color: #FFFBF4; display: flex; flex-direction: column; justify-content: center; text-align: center; padding: 10%; box-sizing: border-box; font-family: 'Anek Bangla', sans-serif; }
        .mag-back-in a { color: #EFC08D; }
        .mag-contents ol { list-style: none; padding: 0; margin: 0; font-family: 'Anek Bangla', sans-serif; }
        .mag-contents li a { display: flex; gap: .6em; align-items: baseline; padding: .28em 0; min-height: 44px; box-sizing: border-box; color: inherit; text-decoration: none; }
        .mag-contents li a .t { flex: 1; min-width: 0; }
        .mag-contents li a .t small { display: block; color: #4D5A63; }
        .mag-contents li a .dots { flex: 1; border-bottom: 1px dotted #4D5A63; transform: translateY(-.3em); min-width: 1em; }
        .mag-contents h3 { font: 700 .8em 'Anek Bangla', sans-serif; color: #CB1B32; margin: .9em 0 .2em; }
        .mag-writers h3 { font: 700 1em 'Anek Bangla', sans-serif; margin: .8em 0 .1em; }
        .mag-writers p { font-size: .9em; color: #4D5A63; }
        /* Without JavaScript: one long readable page */
        .mag-source { background: #FFFBF4; max-width: 720px; margin: 0 auto; padding: 1.5rem; height: calc(100% - 52px); overflow: auto; }
        .mag-ready .mag-source { display: none; }
    </style>
</head>
<body>
@php($preview = $preview ?? false)
<div class="mag" data-mag="{{ $preview ? 'preview-' : '' }}{{ $issue->slug }}" data-title="{{ $issue->title }}">
    @if ($preview)
        {{-- Desk preview (owner, 2026-11-12): how the issue will look, before readers can see it --}}
        <div class="mag-preview" role="status">খসড়া দেখছেন: পাঠকেরা এখনও এটি দেখতে পান না। খসড়া লেখা আর প্রস্তাবিত প্রচ্ছদও এখানে দেখানো হচ্ছে।</div>
    @endif
    <div class="mag-bar">
        @if ($preview)
            <a href="{{ $backUrl ?? url('/desk/ebooks') }}" aria-label="ডেস্কে ফিরুন">← <span class="mag-wide">ডেস্কে ফিরুন</span></a>
        @else
            <a href="{{ $issue->url() }}" aria-label="{{ $w['back'] }}">← <span class="mag-wide">ফিরুন</span></a>
        @endif
        <div class="mag-title">হাতপাখা · {{ $issue->title }}</div>
        <button type="button" data-mag-contents aria-label="সূচি">সূচি</button>
        <button type="button" data-mag-font="-1" aria-label="অক্ষর ছোট করুন"><span class="mag-narrow">অ-</span><span class="mag-wide">ছোট অক্ষর</span></button>
        <button type="button" data-mag-font="1" aria-label="অক্ষর বড় করুন"><span class="mag-narrow">অ+</span><span class="mag-wide">বড় অক্ষর</span></button>
        <button type="button" data-mag-fullscreen hidden aria-pressed="false" aria-label="পুরো পর্দায়"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/></svg></button>
    </div>
    <div class="mag-stage"><div class="mag-book" data-mag-book aria-live="polite"></div></div>
    <div class="mag-hint" data-mag-hint hidden>
        <span>পাতা ওল্টাতে আঙুল দিয়ে ডানে-বাঁয়ে টানুন, বা ‘পরের’ চাপুন।</span>
        <button type="button" data-mag-hint-close aria-label="বুঝেছি, বন্ধ করুন">বুঝেছি</button>
    </div>
    <div class="mag-nav">
        <button type="button" data-mag-prev>← আগের</button>
        <span class="mag-count" data-mag-count role="status">পাতা সাজানো হচ্ছে…</span>
        <button type="button" data-mag-next>পরের →</button>
    </div>

    {{-- The content, in order. hp-magazine.js lays it out into pages; without it, this is the page. --}}
    <div class="mag-source" data-mag-source>
        <section data-kind="cover" data-head="none">
            <div class="mag-cover-in">
                @if ($coverImg = $issue->cover_path ?: (($preview ?? false) ? $issue->proposed_cover_path : null))<img src="{{ asset(ltrim($coverImg, '/')) }}" alt="">@else<div class="mag-fanbg" aria-hidden="true"></div>@endif
                <div>
                    <p class="mag-mast">হাতপাখা</p>
                    <p class="mag-mast-sub">বাংলা সাহিত্যের ওয়েব পত্রিকা · ISSN 2321-3035</p>
                </div>
                <div>
                    <p class="mag-cover-title">{{ $issue->title }}</p>
                    <ul class="mag-cover-lines">
                        @foreach ($coverLines as $p)<li><b>{{ $p->title }}</b><span>{{ $p->authors->map->publicName()->implode(', ') }}</span></li>@endforeach
                    </ul>
                </div>
            </div>
        </section>
        <section data-kind="inside" data-head="none">
            <p style="font-family:'Galada',cursive;font-size:2.4em;color:#CB1B32;margin:0">হাতপাখা</p>
            <p style="margin:.2em 0 1.2em">বাংলা সাহিত্যের ওয়েব পত্রিকা, ২০১২ থেকে</p>
            <p><b>{{ $issue->title }}</b></p>
            <p>{{ $w['in'] }} {{ Bn::num((string) $pieces->count()) }}টি লেখা · {{ Bn::num((string) count($writers)) }} জন লেখক</p>
            @if ($issue->published_at)<p>প্রকাশ: {{ Bn::date($issue->published_at) }}</p>@endif
            <p style="margin-top:2em;font-size:.85em;color:#4D5A63">ISSN 2321-3035 · hatpakha.com<br>প্রতিটি লেখার স্বত্ব তার লেখকের। লেখা পাঠানো ও প্রকাশ বিনামূল্যে।</p>
        </section>
        <section data-kind="contents" data-section="সূচি" data-anchor="contents">
            <p class="mag-kicker">{{ $w['in'] }}</p>
            <h2 class="mag-h">সূচি</h2>
            <ol>
                {{-- The editorial is its own section, first (owner, 2026-11-12) --}}
                @if ($editorial)
                    <li><h3>{{ $w['ed'] }}</h3></li>
                    <li><a href="#" data-goto="piece-{{ $editorial->id }}"><span class="t">{{ $editorial->title }}<small>{{ $editorial->authors->map->publicName()->implode(', ') }}</small></span><span class="dots"></span><span data-pn="piece-{{ $editorial->id }}"></span></a></li>
                @elseif ($issue->editor_note)
                    <li><h3>{{ $w['ed'] }}</h3></li>
                    <li><a href="#" data-goto="editor-note"><span class="t">{{ $w['note'] }}</span><span class="dots"></span><span data-pn="editor-note"></span></a></li>
                @endif
                @foreach ($bySection as $sec => $list)
                    <li><h3>{{ $sec }}</h3></li>
                    @foreach ($list as $p)
                        <li><a href="#" data-goto="piece-{{ $p->id }}"><span class="t">{{ $p->title }}<small>{{ $p->authors->map->publicName()->implode(', ') }}</small></span><span class="dots"></span><span data-pn="piece-{{ $p->id }}"></span></a></li>
                    @endforeach
                @endforeach
                @if ($writers)<li><a href="#" data-goto="writers"><span class="t">{{ $w['of'] }} লেখকেরা</span><span class="dots"></span><span data-pn="writers"></span></a></li>@endif
            </ol>
        </section>
        @if ($editorial || $issue->editor_note)
            <section data-kind="opener" data-head="none"><div class="mag-opener-in mag-opener-ed"><p>{{ $issue->title }}</p><h2>{{ $w['ed'] }}</h2><div class="mag-mark" style="left:auto;right:9%;top:6%;bottom:auto"><span class="mag-brand">হাতপাখা</span><span>ISSN 2321-3035</span></div></div></section>
        @endif
        @if ($editorial)
            @include('partials.magazine-piece', ['p' => $editorial, 'sec' => $w['ed']])
        @elseif ($issue->editor_note)
            <section data-kind="piece" data-section="{{ $w['ed'] }}" data-anchor="editor-note">
                <p class="mag-kicker">{{ $w['ed'] }}</p>
                <h2 class="mag-h">{{ $w['note'] }}</h2>
                @foreach (preg_split('/\n{2,}/', trim($issue->editor_note)) as $para)<p>{{ trim($para) }}</p>@endforeach
            </section>
        @endif
        @foreach ($bySection as $sec => $list)
            <section data-kind="opener" data-head="none"><div class="mag-opener-in"><p>{{ $issue->title }}</p><h2>{{ $sec }}</h2><div class="mag-mark" style="left:auto;right:9%;top:6%;bottom:auto"><span class="mag-brand">হাতপাখা</span><span>ISSN 2321-3035</span></div></div></section>
            @foreach ($list as $p)
                @include('partials.magazine-piece', ['p' => $p, 'sec' => $sec])
            @endforeach
        @endforeach
        @if ($writers)
            <section data-kind="writers" data-section="লেখকেরা" data-anchor="writers">
                <p class="mag-kicker">{{ $w['of'] }}</p>
                <h2 class="mag-h">লেখকেরা</h2>
                @foreach ($writers as $w)
                    <h3>{{ $w->publicName() }}</h3>
                    @if (filled($w->bio))<p>{{ \Illuminate\Support\Str::limit(strip_tags($w->bio), 220) }}</p>@endif
                @endforeach
            </section>
        @endif
        <section data-kind="back" data-head="none">
            <div class="mag-back-in">
                <p style="font-family:'Galada',cursive;font-size:3em;margin:0;color:#FFFBF4">হাতপাখা</p>
                <p style="margin:.4em 0 1.6em;color:#EFC08D">কবিতা, গল্প, প্রবন্ধ আর ভ্রমণকথার অনলাইন পত্রিকা</p>
                <p><b>আপনার লেখাও পাঠান</b><br>প্রকাশ সবসময় বিনামূল্যে<br><a href="{{ \App\Support\Url::to('/submit') }}">hatpakha.com/submit</a></p>
                <p style="margin-top:1.4em">প্রতি সপ্তাহের চিঠি আর নতুন লেখার খবর<br><a href="{{ \App\Support\Url::to('/app') }}">hatpakha.com/app</a></p>
                <p style="margin-top:2em;font-size:.8em;opacity:.8">ISSN 2321-3035 · ২০১২ থেকে</p>
            </div>
        </section>
    </div>
</div>
<script src="{{ asset('js/hp-magazine.js') }}?v=5" defer></script>
</body>
</html>
