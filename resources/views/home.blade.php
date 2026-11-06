<x-layouts.app :seo="$seo">
    {{-- HERO: sunrise fan (design board ১) --}}
    <section class="relative overflow-hidden border-b-2 border-navy" style="background: linear-gradient(160deg,#7CCBEE 0%,#2DAAE2 45%,#1A86C2 100%)">
        <div class="halftone absolute inset-0" aria-hidden="true"></div>
        <div class="relative mx-auto min-h-[520px] max-w-[1320px] px-5 pt-10 md:min-h-[640px] md:pt-16">
            <div class="relative z-10 max-w-[640px]">
                <span class="inline-block -rotate-3 rounded-full border-2 border-navy bg-paper px-4 pt-1 font-hand text-2xl shadow-[3px_3px_0_#13212C]">২০১২ থেকে</span>
                <h1 class="mt-5 font-display text-7xl font-extrabold leading-[.85] tracking-tight md:text-[150px]" style="font-stretch: 75%">বাংলা সাহিত্যের<br><span class="text-paper [-webkit-text-stroke:3px_#13212C] [paint-order:stroke]">নতুন হাওয়া</span></h1>
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

    @if ($onThisDay)
    <section class="border-b-2 border-dashed border-navy">
        <div class="mx-auto flex max-w-[1320px] flex-wrap items-center gap-x-4 gap-y-2 px-5 py-4">
            <span class="font-hand text-2xl text-red">এই দিনে, {{ \App\Support\Bn::num(now()->year - $onThisDay->published_at->year) }} বছর আগে</span>
            <span class="text-lg">হাতপাখায় ছাপা হয়েছিল <a href="{{ $onThisDay->url() }}" class="border-b-2 border-red font-serif font-bold">“{{ $onThisDay->title }}”</a> — <x-byline :post="$onThisDay" /></span>
        </div>
    </section>
    @endif

    @if ($poemOfDay)
    {{-- Poem poster --}}
    <section class="relative overflow-hidden text-paper" style="background: linear-gradient(160deg,#13212C,#12304A)">
        <div class="absolute -right-10 -top-40 font-serif text-[520px] leading-none text-red" aria-hidden="true">“</div>
        <div class="relative mx-auto grid max-w-[1320px] gap-10 px-5 py-16 md:grid-cols-[1fr_2fr] md:py-24">
            <div class="flex flex-col gap-3">
                <span class="w-fit -rotate-3 rounded-full bg-sky px-4 pt-1 font-hand text-2xl text-navy">আজকের কবিতা</span>
                <a href="{{ $poemOfDay->url() }}" class="font-display text-5xl font-extrabold leading-none">{{ $poemOfDay->title }}</a>
                <span class="font-hand text-xl text-peach">{{ $pickNote ?: 'সম্পাদকের পছন্দ — না পড়লে মিস করবেন!' }}</span>
                <span class="text-peach-light"><x-byline :post="$poemOfDay" /> · {{ \App\Support\Bn::num($poemOfDay->reading_minutes) }} মিনিট</span>
                <a href="{{ $poemOfDay->url() }}" class="mt-2 flex h-12 w-fit items-center rounded-full bg-paper px-5 font-bold text-navy">পুরো কবিতা পড়ুন</a>
            </div>
            <div class="poem-bn text-2xl md:text-4xl [&_p]:mb-3">{!! \App\Support\Excerpt::lines($poemOfDay->body_html, 6) !!}</div>
        </div>
    </section>
    @endif

    {{-- Stories: taped pages --}}
    @if ($stories->isNotEmpty())
    <section class="border-y-2 border-navy bg-peach-light">
        <div class="mx-auto max-w-[1320px] px-5 py-16">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <h2 class="font-display text-6xl font-extrabold leading-none md:text-7xl" style="font-stretch: 78%">গল্পের পাতা</h2>
                <a href="{{ \App\Support\Url::to('/category/short-stories') }}" class="border-b-[3px] border-red text-lg font-bold">সব গল্প</a>
            </div>
            <div class="mt-9 grid gap-7 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($stories as $i => $story)
                    <a href="{{ $story->url() }}" class="lift relative flex flex-col gap-3 border-2 border-navy bg-white p-4 shadow-[6px_6px_0_#13212C] {{ $i % 2 ? 'tilt-b' : 'tilt-a' }}">
                        <span class="absolute -top-3 left-6 h-5 w-20 -rotate-6 bg-peach/90" aria-hidden="true"></span>
                        <x-title-card :post="$story" class="!rounded-none border-2 border-navy" />
                        @if ($story->hasCardImage())<span class="font-serif text-xl font-bold leading-snug">{{ $story->title }}</span>@endif
                        <span class="flex justify-between text-sm font-semibold"><span>{{ $story->authors->first()?->publicName() }}</span><span class="text-red">{{ \App\Support\Bn::num($story->reading_minutes) }} মিনিট</span></span>
                    </a>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    <x-ad-slot :slot="config('services.adsense.slot_home')" :height="100" />

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

    @if ($honoured->isNotEmpty())
        {{-- Writers the magazine has honoured (D66, D76): photo, honour, the editor's words, two pieces to read. --}}
        <section class="mx-auto max-w-[1320px] px-5 py-10" aria-labelledby="honoured-h">
            <div class="font-hand text-2xl text-red">হাতপাখার সম্মান</div>
            <h2 id="honoured-h" class="font-display text-4xl font-extrabold">সম্মানিত লেখক</h2>
            <div class="mt-6 grid gap-5 {{ [1 => '', 2 => 'lg:grid-cols-2', 3 => 'lg:grid-cols-3'][min(3, $honoured->count())] }}">
                @foreach ($honoured as $h)
                    @php($pieces = $h->posts()->published()->latest('published_at')->limit(2)->get(['posts.id', 'posts.slug', 'posts.title', 'posts.published_at']))
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

    {{-- Travel stamps --}}
    @if ($travel->isNotEmpty())
    <section class="mx-auto max-w-[1320px] px-5 pb-12">
        <h2 class="font-display text-6xl font-extrabold leading-none" style="font-stretch: 78%">ভ্রমণের ডাকটিকিট</h2>
        <div class="mt-6 flex snap-x gap-6 overflow-x-auto pb-4 pt-4">
            @foreach ($travel as $i => $t)
                <a href="{{ $t->url() }}" class="lift flex h-[300px] w-[240px] flex-none snap-start flex-col bg-white p-3 outline-4 outline-dotted outline-navy {{ $i % 2 ? 'tilt-b' : 'tilt-a' }}">
                    <span class="flex flex-1 flex-col justify-end border-2 border-navy p-4 {{ ['bg-navy text-paper', 'bg-sky', 'bg-red text-white', 'bg-peach'][$i % 4] }}"><span class="font-display text-2xl font-extrabold leading-tight">{{ $t->title }}</span></span>
                    <span class="flex justify-between pt-2 text-xs font-bold"><span>হাতপাখা ডাক</span><span>{{ $t->authors->first()?->publicName() }}</span></span>
                </a>
            @endforeach
        </div>
    </section>
    @endif
</x-layouts.app>
