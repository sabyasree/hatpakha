{{-- D321: one unit, for parents and teachers: what it teaches, every lesson with its words, worksheets, a story to read next --}}
@php
    $U = fn ($p) => \App\Support\Url::to($p);
    $N = fn ($n) => \App\Support\Bn::num((string) $n);
    $C = \App\Support\LearnCourse::class;
    $keys = array_keys($units); $i = array_search($slug, $keys, true);
    $prev = $keys[$i - 1] ?? null; $nextUnit = $keys[$i + 1] ?? null;
@endphp
<x-layouts.app :seo="$seo">
    <article class="mx-auto max-w-[860px] px-5 py-8">
        @include('janun.partials.head', ['crumbs' => [['হাতপাখা', $U('/')], ['জেনে রাখুন', $U('/janun')], ['বাংলা শিখি', $C::url()], [$u['title'], '']],
            'kicker' => 'বাংলা শিখি · ধাপ '.$N($u['n']).'/'.$N(count($units)), 'title' => $u['title']])
        <p lang="en" class="mt-1 text-base text-ink2">{{ $u['en'] }}</p>
        <p class="mt-4 font-serif text-xl leading-relaxed">{{ $u['intro'] }}</p>
        <div class="mt-4 rounded-2xl bg-sky-tint p-4"><p class="font-bold">বাবা-মায়ের জন্য</p><p class="mt-1 font-serif text-lg">{{ $u['tip']['bn'] }}</p><p lang="en" class="mt-1 text-base">{{ $u['tip']['en'] }}</p></div>
        @if ($chart)<p class="mt-3"><a href="{{ $U('/janun/learn-bengali/'.$chart) }}" class="inline-flex min-h-11 items-center font-bold underline">পুরো চার্ট: {{ \App\Http\Controllers\LearnController::lessons()[$chart]['title'] }} →</a></p>@endif
        <a href="{{ $C::url($slug, 1) }}" class="mt-5 flex min-h-16 items-center justify-center rounded-2xl bg-red px-6 font-display text-2xl font-extrabold text-white shadow-[4px_4px_0_#13212C]">এই ধাপের প্রথম পাঠ খেলুন →</a>
        <x-ad-slot spot="list" :height="250" />
        <ol class="mt-6 space-y-4">
            @foreach ($u['lessons'] as $k => $l)
                <li class="rounded-2xl border-2 border-navy p-4">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <h2 class="font-display text-xl font-extrabold">পাঠ {{ $N($k + 1) }}: {{ $l['title'] }}</h2>
                        @if (! empty($l['new']))<p class="font-display text-2xl font-extrabold text-red">{{ collect($l['new'])->map(fn ($x) => in_array(\App\Http\Controllers\LearnController::kind($x), ['sign', 'phola']) ? ($x === 'র্' ? 'র্ক' : 'ক'.$x) : $x)->implode(' ') }}</p>@endif
                    </div>
                    @if (! empty($l['words']))<p class="mt-2 text-lg">@foreach ($l['words'] as $w)<span class="mr-3 inline-block"><span aria-hidden="true">{{ $w[3] }}</span> <b class="font-display text-xl">{{ $w[0] }}</b> <span lang="en" class="text-ink2">{{ $w[1] }}, {{ $w[2] }}</span></span>@endforeach</p>@endif
                    @if (! empty($l['sentences']))<ul class="mt-2 text-lg">@foreach ($l['sentences'] as $s)<li><b class="font-display text-xl">{{ $s[0] }}</b> <span lang="en" class="text-ink2">{{ $s[2] }}</span></li>@endforeach</ul>@endif
                    @if (isset($l['story']))<p class="mt-2 font-serif text-lg text-ink2">{{ \Illuminate\Support\Str::limit(str_replace("\n", ' ', $l['story']['text']), 120) }}</p>@endif
                    <div class="mt-3 flex flex-wrap gap-2">
                        <a href="{{ $C::url($slug, $k + 1) }}" class="flex min-h-11 items-center rounded-full bg-navy px-4 font-bold text-white">খেলুন</a>
                        <a href="{{ $C::url('pdf/'.$slug.'-'.($k + 1).'-a4') }}" class="flex min-h-11 items-center rounded-full border-2 border-navy px-4 font-bold">ওয়ার্কশিট A4</a>
                        <a href="{{ $C::url('pdf/'.$slug.'-'.($k + 1).'-letter') }}" class="flex min-h-11 items-center rounded-full border-2 border-navy px-4 font-bold" lang="en">US Letter</a>
                    </div>
                </li>
                @if ($k === 2)<x-ad-slot spot="list" :height="250" />@endif
            @endforeach
        </ol>
        <section class="mt-8 rounded-2xl bg-peach-light p-5">
            @if ($img = \App\Http\Controllers\LearnController::preview($slug.'-1'))<img src="{{ $img }}" alt="{{ $u['title'] }}: ওয়ার্কশিট / {{ $u['en'] }} worksheet" width="794" height="1000" loading="lazy" class="float-right ml-3 h-auto w-28 rounded-lg border border-rule sm:w-40">@endif
            <h2 class="font-display text-2xl font-extrabold">বাংলা স্কুল আর শিক্ষকদের জন্য</h2>
            <p class="mt-2 font-serif text-lg">এই ধাপের সব ওয়ার্কশিট একসঙ্গে, প্রথম পাতায় কীভাবে ব্যবহার করবেন, শেষ পাতায় উত্তর। বিনা পয়সায় ছাপুন, ক্লাসে বিলি করুন।</p>
            <div class="mt-3 flex flex-wrap gap-2">
                <a href="{{ $C::url('pdf/'.$slug.'-teacher-a4') }}" class="flex min-h-11 items-center rounded-full bg-navy px-4 font-bold text-white">শিক্ষকের সেট (A4)</a>
                <a href="{{ $C::url('pdf/'.$slug.'-teacher-letter') }}" class="flex min-h-11 items-center rounded-full border-2 border-navy px-4 font-bold">US Letter</a>
            </div>
        </section>
        @if ($read->isNotEmpty())
            <h2 class="mt-8 font-display text-2xl font-extrabold">এখন নিজে পড়ো</h2>
            <p class="text-base text-ink2">এই ধাপ শেষ হলে পড়া যায় এমন ছোটদের লেখা</p>
            <ul class="mt-3 grid gap-2 sm:grid-cols-2">@foreach ($read as $p)<li><a href="{{ $p->url() }}" class="block min-h-11 rounded-xl border-2 border-navy p-3 font-bold hover:bg-peach-light">{{ $p->title }}</a></li>@endforeach</ul>
        @endif
        <nav aria-label="ধাপ" class="mt-8 flex flex-wrap justify-between gap-2">
            @if ($prev)<a href="{{ $C::url($prev) }}" class="flex min-h-11 items-center rounded-full border-2 border-navy px-4 font-bold">← {{ $units[$prev]['title'] }}</a>@else<a href="{{ $U('/bornomala') }}" class="flex min-h-11 items-center rounded-full border-2 border-navy px-4 font-bold">← বর্ণমালা</a>@endif
            @if ($nextUnit)<a href="{{ $C::url($nextUnit) }}" class="flex min-h-11 items-center rounded-full border-2 border-navy px-4 font-bold">{{ $units[$nextUnit]['title'] }} →</a>@endif
        </nav>
    </article>
</x-layouts.app>
