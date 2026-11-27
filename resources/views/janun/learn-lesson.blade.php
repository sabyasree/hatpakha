{{-- D317: one বাংলা শিখি lesson; every word with a Roman reading and its English meaning --}}
@php
    $U = fn ($p) => \App\Support\Url::to($p);
    $L = \App\Http\Controllers\LearnController::class;
    $word = fn ($w) => '<span class="font-display text-2xl font-extrabold">'.e($w[0]).'</span> <span lang="en" class="text-base text-navy">'.e($w[1]).' · '.e($w[2]).'</span>';
@endphp
<x-layouts.app :seo="$seo">
    <article class="mx-auto max-w-[760px] px-5 py-8">
        @include('janun.partials.head', ['crumbs' => [['হাতপাখা', $U('/')], ['জেনে রাখুন', $U('/janun')], ['বাংলা শিখি', $U('/janun/learn-bengali')], [$l['title'], '']],
            'kicker' => 'বাংলা শিখি · চার্ট', 'title' => $l['title']])
        <p lang="en" class="mt-1 text-sm text-ink2">{{ $l['en'] }}</p>
        <p class="mt-4 font-serif text-lg leading-relaxed">{{ $l['intro'] }}</p>
        <div class="mt-4 flex flex-wrap gap-2">@foreach (array_keys(array_filter(\App\Http\Controllers\LearnController::CHART_OF, fn ($c) => $c === $key)) as $unitSlug)<a href="{{ \App\Support\LearnCourse::url($unitSlug) }}" class="inline-flex min-h-11 items-center rounded-full bg-navy px-4 font-bold text-white">ধাপে ধাপে শিখুন: {{ \App\Support\LearnCourse::unit($unitSlug)['title'] }}</a>@endforeach<a href="{{ \App\Support\LearnCourse::url('worksheets') }}" class="inline-flex min-h-11 items-center rounded-full border-2 border-navy px-4 font-bold hover:bg-peach-light">ওয়ার্কশিট (PDF)</a>@if ($key === 'kar')<a href="#barokhori" class="inline-flex min-h-11 items-center rounded-full bg-red px-4 font-bold text-white hover:bg-red-deep">বারোখড়ি খেলুন ↓</a>@endif</div>
        <x-ad-slot spot="list" :height="250" />   {{-- reference page for parents and older learners (owner, 2026-11-27: ads on parent pages only) --}}

        @if ($key === 'kar')
            <div class="mt-6 grid gap-3">
                @foreach ($l['rows'] as [$v, $sign, $name, $roman, $where, $words])
                    <section class="rounded-2xl border-2 border-navy p-4">
                        <h2 class="flex items-baseline gap-3"><span class="font-display text-4xl font-extrabold text-red">ক{{ $sign }}</span><span class="font-display text-2xl font-extrabold">{{ $name }}</span><span lang="en" class="text-sm text-ink2">{{ $roman }}</span></h2>
                        <p class="mt-1">{{ $v }}-এর চিহ্ন বসে {{ $where }}।</p>
                        <p class="mt-1 font-display text-2xl font-extrabold">ক + {{ $v }} = ক{{ $sign }}</p>
                        <ul class="mt-2 space-y-1">@foreach ($words as $w)<li>{!! $word($w) !!}</li>@endforeach</ul>
                    </section>
                @endforeach
            </div>
            <section id="barokhori" class="mt-8 scroll-mt-20 rounded-2xl bg-peach-light p-4" data-barokhori>
                <h2 class="font-display text-2xl font-extrabold">বারোখড়ি: একটা অক্ষরে সব কার</h2>
                <p class="mt-1 text-sm">অক্ষর বেছে নিন, তার সঙ্গে প্রতিটি কার কেমন দেখায় দেখুন।</p>
                <div class="mt-3 flex flex-wrap gap-2" role="group" aria-label="অক্ষর বেছে নিন">
                    @foreach ($l['barokhori'] as $c)<button type="button" data-letter="{{ $c }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}" class="h-11 w-11 rounded-lg border-2 border-navy font-display text-xl font-extrabold aria-pressed:bg-navy aria-pressed:text-white">{{ $c }}</button>@endforeach
                </div>
                <ol class="mt-4 grid grid-cols-4 gap-2 sm:grid-cols-6" data-combos>
                    @foreach (array_merge([''], array_column($l['rows'], 1)) as $sign)<li class="flex h-16 items-center justify-center rounded-xl bg-paper font-display text-3xl font-extrabold" data-sign="{{ $sign }}">ক{{ $sign }}</li>@endforeach
                </ol>
            </section>
            <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
            (() => {
                const box = document.querySelector('[data-barokhori]'); if (!box) return;
                box.querySelectorAll('[data-letter]').forEach((b) => b.addEventListener('click', () => {
                    box.querySelectorAll('[data-letter]').forEach((x) => x.setAttribute('aria-pressed', String(x === b)));
                    box.querySelectorAll('[data-sign]').forEach((li) => { li.textContent = b.dataset.letter + li.dataset.sign; });
                }));
            })();
            </script>
        @elseif ($key === 'phola')
            <div class="mt-6 grid gap-3">
                @foreach ($l['rows'] as [$name, $roman, $shape, $how, $words])
                    <section class="rounded-2xl border-2 border-navy p-4">
                        <h2 class="flex items-baseline gap-3"><span class="font-display text-4xl font-extrabold text-red">{{ $shape }}</span><span class="font-display text-2xl font-extrabold">{{ $name }}</span><span lang="en" class="text-sm text-ink2">{{ $roman }}</span></h2>
                        <p class="mt-1 text-sm">{{ $how }}</p>
                        <ul class="mt-2 space-y-1">@foreach ($words as $w)<li>{!! $word($w) !!}</li>@endforeach</ul>
                    </section>
                @endforeach
            </div>
        @elseif ($key === 'juktakkhor')
            <ul class="mt-6 grid gap-2 sm:grid-cols-2">
                @foreach ($l['rows'] as [$c, $parts, $w])
                    <li class="flex items-center gap-3 rounded-xl border border-rule p-3"><span class="w-16 flex-none text-center font-display text-4xl font-extrabold text-red">{{ $c }}</span>
                        <span><span class="block text-sm text-ink2">{{ $parts }}</span>{!! $word($w) !!}</span></li>
                @endforeach
            </ul>
        @else
            @foreach ($l['groups'] as [$heading, $words])
                <section class="mt-6">
                    <h2 class="font-display text-2xl font-extrabold">{{ $heading }}</h2>
                    <ul class="mt-2 grid gap-2 sm:grid-cols-2">
                        @foreach ($words as [$bn, $roman, $en, $note])
                            <li class="rounded-xl border border-rule p-3">{!! $word([$bn, $roman, $en]) !!}@if ($note)<span class="block text-sm text-ink2">{{ $note }}</span>@endif</li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
            <section class="mt-6">
                <h2 class="font-display text-2xl font-extrabold">ছোট বাক্য</h2>
                <ul class="mt-2 grid gap-2">@foreach ($l['sentences'] as $s)<li class="rounded-xl border border-rule p-3">{!! $word($s) !!}</li>@endforeach</ul>
            </section>
        @endif

        @if (! empty($l['notes']))
            <section class="mt-6 rounded-2xl bg-sky-tint p-4"><h2 class="font-display text-xl font-extrabold">মনে রাখার কথা</h2>
                <ul class="mt-2 list-disc space-y-1 pl-5 font-serif">@foreach ($l['notes'] as $n)<li>{{ $n }}</li>@endforeach</ul></section>
        @endif
        <x-ad-slot spot="list" :height="250" />
        <nav aria-label="পাঠ" class="mt-6 flex flex-wrap justify-between gap-2">
            @if ($prev)<a href="{{ $U('/janun/learn-bengali/'.$prev) }}" class="flex min-h-11 items-center rounded-full border-2 border-navy px-4 font-bold hover:bg-peach-light">← {{ $all[$prev]['title'] }}</a>@else<a href="{{ $U('/bornomala') }}" class="flex min-h-11 items-center rounded-full border-2 border-navy px-4 font-bold hover:bg-peach-light">← বর্ণমালা</a>@endif
            @if ($next)<a href="{{ $U('/janun/learn-bengali/'.$next) }}" class="flex min-h-11 items-center rounded-full bg-red px-4 font-bold text-white hover:bg-red-deep">{{ $all[$next]['title'] }} →</a>@else<a href="{{ $U('/janun/learn-bengali') }}" class="flex min-h-11 items-center rounded-full bg-red px-4 font-bold text-white hover:bg-red-deep">বাংলা শিখি →</a>@endif
        </nav>
    </article>
</x-layouts.app>
