{{-- D175: পাঠকের কলম. This week's theme and a one-minute form (no account needed); the picks once published.
     D266 (owner: "make this section interesting"; two UX reviews): an invitation card with a fixed deadline and the form
     always open (partials/kolom-invite), picks as quote cards with the best one apart, long letters folded, picks first
     while they are fresh or while the week is being judged. Only real data. Nothing people read is tilted. --}}
@php
    use App\Models\ReaderPrompt;
    use App\Support\Bn;
    use App\Support\Url;
    $shown = $prompt ?? $latest;   // the published week whose picks are on this page
    $picks = $shown?->picked ?? collect();
    $open = $current && $current->isOpen();
    $readingFirst = ! $prompt && $picks->isNotEmpty() && ! $judging && (! $open || $shown->published_at?->gt(now()->subHours(30)));
    $wa = fn (string $text) => 'https://wa.me/?text='.rawurlencode($text);
    $invite = ['open' => $open, 'readingFirst' => $readingFirst, 'hasPicks' => $picks->isNotEmpty(), 'wa' => $wa];
    $fold = ! $prompt && $picks->count() > 3;   // A6: the main page folds the ten into three + the rest; a week's own page shows all
@endphp
<x-layouts.app :seo="$seo">
    <div class="mx-auto max-w-[760px] px-4 pb-16 pt-5 sm:px-5">
        <header>
            <h1 class="font-hand text-4xl leading-tight text-red">পাঠকের কলম</h1>
            <p class="mt-1 text-lg text-ink2">প্রতি সপ্তাহে একটি বিষয়। এক মিনিটে লিখুন, সাইন ইন লাগে না।</p>
            @if ($readingFirst && $open)
                {{-- A6 (research 2026-11): the form can be far down under last week's picks; one tap takes the reader there --}}
                <a href="#form" data-kolom-jump class="mt-3 flex min-h-12 items-center justify-center rounded-full bg-red px-5 py-2 text-center text-lg font-bold leading-snug text-white shadow-[3px_3px_0_#13212C]">এ সপ্তাহের বিষয়: “{{ $current->title }}” · লিখুন ↓</a>
            @endif
        </header>

        @unless ($readingFirst)
            @include('partials.kolom-invite', $invite)
        @endunless

        @if ($picks->isNotEmpty())
            <section id="picks" class="mt-10 scroll-mt-24" aria-labelledby="picks-title">
                <p class="text-base font-bold text-red-dark">{{ ($prompt ? '' : ($readingFirst ? 'এ সপ্তাহের সেরা দশ · ' : 'গত সপ্তাহের সেরা দশ · ')).ReaderPrompt::KINDS[$shown->kind] }}{{ $prompt ? ' · '.Bn::date($shown->published_at) : '' }}</p>
                <h2 id="picks-title" class="font-serif text-3xl font-extrabold leading-tight">{{ $shown->title }}</h2>
                <p class="mt-1 text-lg text-ink2">{{ $shown->ask }}</p>
                <ol class="mt-6 grid gap-5">
                    @foreach ($picks as $i => $e)
                        {{-- A6: on the main page the first three are open, the rest folded under one button --}}
                        @if ($fold && $i === 3)
                            </ol>
                            <details class="group/more mt-5" data-kolom-rest>
                                <summary class="flex min-h-12 cursor-pointer list-none items-center justify-center rounded-full border-2 border-navy bg-white px-5 text-lg font-bold [&::-webkit-details-marker]:hidden">
                                    <span class="group-open/more:hidden">আরও {{ Bn::num((string) ($picks->count() - 3)) }}টি পড়ুন ↓</span><span class="hidden group-open/more:inline">ছোট করুন ↑</span>
                                </summary>
                                <ol class="mt-5 grid gap-5" start="4">
                        @endif
                        @php
                            $best = $i === 0;
                            [$head, $rest] = $best ? [trim($e->body), null] : $e->parts();   // the best piece is shown whole (UX review)
                            $letter = $shown->kind === 'letter';
                            $dark = $best && ! $letter;   // a long letter in white on navy is a tall dark block: the best letter stays on paper
                            $bg = $best ? ($letter ? 'bg-peach-light' : 'bg-navy text-white') : ['bg-white', 'bg-peach-light', 'bg-sky-tint'][$i % 3];
                            $mark = $dark ? 'text-white' : 'text-red';   // only white text on navy (DESIGN.md)
                            $share = '“'.\Illuminate\Support\Str::limit(preg_replace('/\s+/u', ' ', $e->body), 300).'” — '.$e->shownName()."\n".'হাতপাখার পাঠকের কলম: '.$shown->url().'#e'.$e->id;
                        @endphp
                        <li id="e{{ $e->id }}" class="relative scroll-mt-24 rounded-3xl border-2 border-navy {{ $bg }} {{ $best ? 'mt-3 p-6 shadow-[4px_4px_0_#CB1B32]' : 'p-5' }}">
                            @if ($best)
                                <span class="absolute -top-4 left-5 -rotate-3 rounded-xl bg-red px-3 pb-0.5 font-hand text-2xl text-white" aria-hidden="true">সেরা</span>
                                <span class="sr-only">এ সপ্তাহের সেরা লেখা।</span>
                            @endif
                            <p class="whitespace-pre-line font-serif {{ $best ? 'text-2xl' : 'text-xl' }} leading-[1.8]"><span class="{{ $mark }}" aria-hidden="true">“</span>{{ $head }}@if (! $rest)<span class="{{ $mark }}" aria-hidden="true">”</span>@endif</p>
                            @if ($rest)
                                <details class="group" data-kolom-more>
                                    <summary class="mt-2 inline-flex min-h-11 cursor-pointer list-none items-center rounded-full border-2 {{ $dark ? 'border-white' : 'border-navy bg-white' }} px-4 font-bold [&::-webkit-details-marker]:hidden">
                                        <span class="group-open:hidden">{{ $letter ? 'পুরো চিঠিটা পড়ুন' : 'পুরোটা পড়ুন' }} ↓</span><span class="hidden group-open:inline">ছোট করুন ↑</span>
                                    </summary>
                                    <p class="mt-2 whitespace-pre-line font-serif {{ $best ? 'text-2xl' : 'text-xl' }} leading-[1.8]">{{ $rest }}<span class="{{ $mark }}" aria-hidden="true">”</span></p>
                                </details>
                            @endif
                            <div class="mt-4 flex flex-wrap items-center justify-between gap-x-3 gap-y-2">
                                <span class="text-lg font-bold">— {{ $e->shownName() }}</span>
                                <a href="{{ $wa($share) }}" target="_blank" rel="noopener"
                                    class="flex min-h-11 items-center rounded-full border-2 px-4 font-bold {{ $dark ? 'border-white bg-navy text-white' : 'border-navy bg-white text-navy' }}">WhatsApp-এ পাঠান</a>
                            </div>
                        </li>
                    @endforeach
                </ol>
                @if ($fold && $picks->count() > 3)</details>@endif
                @if ($prompt && $open)
                    <a href="{{ Url::to('/kolom') }}#form" class="mt-8 flex min-h-12 items-center justify-center rounded-full bg-red px-6 text-center text-lg font-bold text-white shadow-[3px_3px_0_#13212C]">এ সপ্তাহের বিষয় “{{ $current->title }}”: আপনিও লিখুন</a>
                @endif
            </section>
        @endif

        @if ($readingFirst)
            @include('partials.kolom-invite', $invite)
        @endif

        {{-- Next Monday's theme, from the fixed list --}}
        @if (! $prompt && $open)
            <section class="mt-10 rounded-2xl border-2 border-navy bg-peach p-5">
                <p class="text-base font-bold">সামনের সোমবার</p>
                <p class="font-serif text-2xl font-extrabold">“{{ $next[1] }}”</p>
                <p class="text-base">{{ ReaderPrompt::KINDS[$next[0]] }}: {{ $next[2] }}</p>
            </section>
        @endif

        @if ($past->count() > 1)
            <section class="mt-10" aria-labelledby="past-title">
                <h2 id="past-title" class="font-serif text-2xl font-extrabold">আগের সপ্তাহগুলো</h2>
                <ul class="-mx-4 mt-3 flex snap-x gap-3 overflow-x-auto px-4 pb-3 sm:mx-0 sm:grid sm:grid-cols-2 sm:overflow-visible sm:px-0">
                    @foreach ($past->skip(1)->values() as $i => $p)
                        <li class="w-[78%] flex-none snap-start sm:w-auto">
                            <a href="{{ $p->url() }}" class="block h-full rounded-2xl border-2 border-navy {{ ['bg-white', 'bg-sky-tint', 'bg-peach-light'][$i % 3] }} p-4 hover:shadow-[3px_3px_0_#13212C]">
                                <span class="block text-base font-bold text-red-dark">{{ ReaderPrompt::KINDS[$p->kind] }} · {{ Bn::date($p->published_at) }}</span>
                                <span class="mt-1 block font-serif text-xl font-extrabold">{{ $p->title }}</span>
                                @if ($top = $p->picked->first())<span class="mt-1 block font-serif text-lg text-ink2">“{{ \Illuminate\Support\Str::limit(preg_replace('/\s+/u', ' ', $top->body), 60) }}”</span>@endif
                                <span class="mt-2 block font-bold">সেরা লেখাগুলো পড়ুন →</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
</x-layouts.app>
