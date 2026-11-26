{{-- D301: one "জেনে রাখুন" page: list (প্রবাদ, শব্দ…), guide (পুজো, বানানের নিয়ম) or study (ভাবসম্প্রসারণ, রচনা).
     The look of a reference page, not a literary piece. Ads only between groups and sections, never inside a mantra,
     a step list or an answer (UX review, 2026-11-25). --}}
@php
    $U = fn ($p) => \App\Support\Url::to($p);
    $crumbs = [['হাতপাখা', $U('/')], ['জেনে রাখুন', $U('/janun')], [$p->sectionName(), $U('/janun/'.$p->section)], [$p->title, '']];
    $trust = ($p->kind === 'list' ? \App\Support\Bn::num((string) count((array) ($b['entries'] ?? []))).'টি · ' : '').'শেষ যাচাই: '.\App\Support\Bn::date($p->modifiedAt());
    $sections = collect((array) ($b['sections'] ?? []))->filter(fn ($s) => ! empty($s['paragraphs']) || ! empty($s['steps']) || ! empty($s['mantras']))->values();
@endphp
<x-layouts.app :seo="$seo">
    <article class="mx-auto max-w-[760px] px-5 py-8">
        @include('janun.partials.head', ['crumbs' => $crumbs, 'kicker' => 'জেনে রাখুন · '.$p->sectionName(), 'title' => $p->title, 'trust' => $trust, 'trustBy' => $deskUrl ?? null])

        @if ($p->kind === 'study' && ! empty($b['summary']))
            <div class="mt-5 rounded-2xl border-l-4 border-sky-deep bg-sky-tint p-4 font-serif text-lg leading-relaxed"><b class="font-display">এক নজরে:</b> {{ $b['summary'] }}</div>
        @elseif (! empty($b['intro']))
            <p class="mt-5 font-serif text-lg leading-relaxed">{{ $b['intro'] }}</p>
        @endif

        @if ($p->kind === 'list')
            @php($groups = collect((array) ($b['entries'] ?? []))->groupBy(fn ($e) => mb_substr(trim((string) $e['term']), 0, 1)))
            <div class="sticky top-0 z-10 -mx-5 mt-6 bg-paper px-5 pb-2 pt-2">
            <label for="janun-q" class="block font-bold">খুঁজুন</label>
            <input id="janun-q" type="search" data-janun-q autocomplete="off" class="mt-1 block min-h-12 w-full rounded-xl border-2 border-navy bg-white px-4 text-lg" placeholder="যেমন: লোভ">
            <p data-janun-count role="status" aria-live="polite" class="mt-1 min-h-5 text-sm text-ink2"></p>
            <nav aria-label="অক্ষর ধরে" class="mt-2 flex flex-nowrap gap-2 overflow-x-auto pb-1">
                @foreach ($groups->keys() as $l)<a href="#l-{{ $loop->index }}" data-janun-letter class="grid h-11 w-11 flex-none place-items-center rounded-xl border-2 border-navy bg-white font-bold hover:bg-peach-light">{{ $l }}</a>@endforeach
            </nav>
            </div>
            @php($seen = 0)
            @foreach ($groups as $letter => $entries)
                <h2 id="l-{{ $loop->index }}" data-janun-group class="mt-8 scroll-mt-48 inline-block border-b-2 border-red font-display text-2xl font-extrabold text-red">{{ $letter }}</h2>
                @foreach ($entries as $e)
                    <div data-janun-item class="border-b border-rule py-3">
                        <h3 class="font-display text-lg font-extrabold">{{ $e['term'] }}</h3>
                        <p class="mt-1 font-serif text-base leading-relaxed"><b>অর্থ:</b> {{ $e['meaning'] }}</p>
                        @if (filled($e['example'] ?? ''))<p class="mt-1 font-serif text-base italic leading-relaxed text-ink2">উদাহরণ: {{ $e['example'] }}</p>@endif
                    </div>
                @endforeach
                @php($before = $seen)
                @php($seen += $entries->count())
                @if (($before < 15 && $seen >= 15) || ($before >= 15 && intdiv($seen - 15, 25) > intdiv($before - 15, 25)))<div data-janun-ad><x-ad-slot spot="list" :height="250" /></div>@endif
            @endforeach
            <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
                (() => {
                    const q = document.querySelector('[data-janun-q]'), out = document.querySelector('[data-janun-count]');
                    const items = [...document.querySelectorAll('[data-janun-item]')], groups = [...document.querySelectorAll('[data-janun-group]')];
                    const ads = [...document.querySelectorAll('[data-janun-ad]')], letters = [...document.querySelectorAll('[data-janun-letter]')];
                    const bn = (n) => String(n).replace(/\d/g, (d) => '০১২৩৪৫৬৭৮৯'[d]);
                    // য়/ড়/ঢ় come as one character from some keyboards and as two from others: compare in one form (UX review)
                    const norm = (s) => s.normalize('NFC').replace(/[\u200c\u200d]/g, '');
                    items.forEach((el) => { el.dataset.t = norm(el.textContent); });
                    q?.addEventListener('input', () => {
                        const t = norm(q.value.trim());
                        let n = 0;
                        items.forEach((el) => { const hit = !t || el.dataset.t.includes(t); el.hidden = !hit; n += hit ? 1 : 0; });
                        groups.forEach((g) => { let s = g.nextElementSibling, any = false; while (s && !s.matches('[data-janun-group]')) { if (s.matches('[data-janun-item]') && !s.hidden) any = true; s = s.nextElementSibling; } g.hidden = !any; });
                        groups.forEach((g, i) => { if (letters[i]) letters[i].hidden = g.hidden; });
                        ads.forEach((a) => { a.hidden = !!t; });   // no ad beside a search answer
                        out.textContent = !t ? '' : (n ? bn(n) + 'টি মিলেছে' : 'কিছু মেলেনি। অন্য কোনো শব্দে খুঁজে দেখুন।');
                    });
                })();
            </script>
        @else
            @if ($p->kind === 'guide' && ! empty($b['facts']))
                <dl class="mt-5 grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 rounded-2xl border-l-4 border-sky-deep bg-sky-tint p-4">
                    @foreach ($b['facts'] as $f)<dt class="text-ink2">{{ $f['label'] }}</dt><dd class="font-bold">{{ $f['value'] }}</dd>@endforeach
                </dl>
            @endif
            @if ($sections->count() > 2)
                <details class="mt-5 rounded-2xl border-2 border-navy p-4" open>
                    <summary class="cursor-pointer font-bold">এই লেখায়</summary>
                    <ol class="mt-2 list-decimal space-y-1 pl-6">@foreach ($sections as $s)<li><a href="#s-{{ $loop->index }}" class="underline">{{ $s['heading'] }}</a></li>@endforeach</ol>
                </details>
            @endif
            @foreach ($sections as $s)
                <h2 id="s-{{ $loop->index }}" class="mt-8 scroll-mt-24 font-display text-2xl font-extrabold">{{ $s['heading'] }}</h2>
                @foreach ((array) ($s['paragraphs'] ?? []) as $para)<p class="mt-3 font-serif text-lg leading-relaxed">{{ $para }}</p>@endforeach
                @if (! empty($s['steps']))
                    <ol class="mt-4 space-y-3">
                        @foreach ($s['steps'] as $step)<li class="flex gap-3"><span class="grid h-8 w-8 flex-none place-items-center rounded-full bg-red font-bold text-white">{{ \App\Support\Bn::num((string) $loop->iteration) }}</span><span class="font-serif text-lg leading-relaxed">{{ $step }}</span></li>@endforeach
                    </ol>
                @endif
                @foreach ((array) ($s['mantras'] ?? []) as $m)
                    <div class="mt-4 rounded-2xl bg-peach-light p-4">
                        <div class="text-sm font-bold text-ink2">{{ $m['name'] }}</div>
                        <p lang="sa" class="mt-1 whitespace-pre-line font-serif text-xl font-bold leading-relaxed">{{ $m['text'] }}</p>
                        @if (filled($m['roman'] ?? ''))<p lang="sa-Latn" class="mt-1 text-base italic text-ink2">{{ $m['roman'] }}</p>@endif
                        <p class="mt-2 font-serif text-base leading-relaxed"><b>অর্থ:</b> {{ $m['meaning'] }}</p>
                        @if (filled($m['english'] ?? ''))<p lang="en" class="mt-1 text-base leading-relaxed text-ink2">{{ $m['english'] }}</p>@endif
                        @if (! empty($m['checked_on']))
                            <p class="mt-1 text-sm text-ink2">মিলিয়ে দেখা হয়েছে:
                                @foreach ((array) $m['checked_on'] as $i => $site)@if (! $loop->first), @endif @if (filled($m['checked_urls'][$i] ?? null))<a href="{{ $m['checked_urls'][$i] }}" rel="nofollow noopener" target="_blank" class="underline">{{ $site }}</a>@else{{ $site }}@endif @endforeach
                            </p>
                        @endif
                    </div>
                @endforeach
                @if (($loop->iteration === 1 || ($loop->iteration === 3 && $loop->remaining > 0)) && empty($s['steps']) && empty($s['mantras']))<x-ad-slot spot="article_mid" :height="250" />@endif
            @endforeach
        @endif

        @if (! empty($b['faq']))
            <h2 class="mt-10 font-display text-2xl font-extrabold">প্রশ্নোত্তর</h2>
            @foreach ($b['faq'] as $f)
                <details class="mt-2 rounded-xl border-2 border-rule p-3"><summary class="cursor-pointer font-bold">{{ $f['q'] }}</summary><p class="mt-2 font-serif leading-relaxed">{{ $f['a'] }}</p></details>
            @endforeach
        @endif
        @if (! empty($b['sources']))
            <h2 class="mt-8 text-sm font-bold text-ink2">সূত্র</h2>
            <ul class="mt-1 list-disc pl-5 text-sm text-ink2">@foreach ($b['sources'] as $src)<li>{{ $src }}</li>@endforeach</ul>
        @endif
        <x-ad-slot spot="list" :height="250" />
        @if ($siblings->isNotEmpty())
            <h2 class="mt-8 font-display text-xl font-extrabold">আরও পড়ুন</h2>
            <ul class="mt-3 grid gap-2">@foreach ($siblings as $sib)<li><a href="{{ $sib->url() }}" class="flex min-h-11 items-center rounded-xl border-2 border-navy px-4 font-bold hover:bg-peach-light">{{ $sib->title }}</a></li>@endforeach</ul>
        @endif
        <a href="{{ $U('/janun/'.$p->section) }}" class="mt-4 inline-flex min-h-11 items-center font-bold text-sky-night underline">{{ $p->sectionName() }}: সব পাতা →</a>
    </article>
</x-layouts.app>
