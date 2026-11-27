{{-- D321: every worksheet as PDF (A4 and US Letter), the wall charts and each unit's teacher's set; indexed for "worksheet pdf" searches --}}
@php
    $U = fn ($p) => \App\Support\Url::to($p);
    $N = fn ($n) => \App\Support\Bn::num((string) $n);
    $C = \App\Support\LearnCourse::class;
@endphp
<x-layouts.app :seo="$seo">
    <article class="mx-auto max-w-[860px] px-5 py-8">
        @include('janun.partials.head', ['crumbs' => [['হাতপাখা', $U('/')], ['জেনে রাখুন', $U('/janun')], ['বাংলা শিখি', $C::url()], ['ওয়ার্কশিট', '']], 'kicker' => 'বাংলা শিখি · বিনা পয়সায়', 'title' => 'বাংলা ওয়ার্কশিট (PDF)'])
        <p lang="en" class="mt-1 text-base text-ink2">Free Bengali worksheets: alphabet, vowel signs (kar), phola and conjunct letters. A4 and US Letter.</p>
        <p class="mt-4 font-serif text-xl leading-relaxed">প্রতিটি পাঠের একটি ওয়ার্কশিট: আগে দেখে, তারপর ধূসর অক্ষরের ওপর, শেষে নিজে লেখা; ছবির সঙ্গে শব্দ মেলানো। বাংলা স্কুল আর পুজো কমিটির জন্য প্রতিটি ধাপের পুরো সেট, উত্তরসহ। বিনা পয়সায় ছাপুন, বিলি করুন।</p>
        <section id="schools" lang="en" class="mt-6 scroll-mt-20 rounded-2xl border-2 border-sky-deep bg-sky-tint p-4">
            <h2 class="font-display text-2xl font-extrabold">For Bengali schools and teachers</h2>
            <p class="mt-1 text-lg">Every unit has a teacher's set: a page on how to use it in class, every worksheet of the unit, and an answer page. Free to print and hand out. Lessons can also be played on a phone at home, with English instructions for families who don't read Bengali.</p>
            <ul class="mt-3 grid gap-2 sm:grid-cols-2">@foreach ($units as $slug => $u)<li><a href="{{ $C::url('pdf/'.$slug.'-teacher-a4') }}" class="flex min-h-11 items-center rounded-xl border-2 border-navy bg-paper px-3 font-bold">{{ $u['n'] }}. {{ $u['en'] }}</a></li>@endforeach</ul>
            <p class="mt-2 text-base">US Letter versions are in each unit below.</p>
        </section>
        <h2 lang="en" class="mt-6 font-display text-2xl font-extrabold">Bengali alphabet worksheets (PDF)</h2>
        <h2 class="mt-1 font-display text-2xl font-extrabold">দেওয়ালের চার্ট</h2>
        <div class="mt-3 grid gap-3 sm:grid-cols-2">
            @foreach (['alphabet' => 'বর্ণমালার চার্ট', 'kar' => 'কারের চার্ট', 'juktakkhor' => 'যুক্তাক্ষরের তালিকা'] as $c => $t)
                <div class="rounded-2xl border-2 border-navy p-4">@if ($img = \App\Http\Controllers\LearnController::preview('chart-'.$c))<img src="{{ $img }}" alt="{{ $t }}: বাংলা ওয়ার্কশিট / Bengali worksheet (PDF)" width="794" height="1000" loading="lazy" class="mb-2 h-auto w-full rounded-lg border border-rule">@endif<p class="font-display text-xl font-extrabold">{{ $t }}</p>
                    <div class="mt-2 flex gap-2"><a href="{{ $C::url('pdf/chart-'.$c.'-a4') }}" class="flex min-h-11 items-center rounded-full bg-navy px-4 font-bold text-white">A4</a><a href="{{ $C::url('pdf/chart-'.$c.'-letter') }}" class="flex min-h-11 items-center rounded-full border-2 border-navy px-4 font-bold" lang="en">US Letter</a></div></div>
            @endforeach
        </div>
        <x-ad-slot spot="list" :height="250" />
        @foreach ($units as $slug => $u)
            <section class="mt-6">
                <h2 class="font-display text-xl font-extrabold"><a href="{{ $C::url($slug) }}" class="hover:underline">{{ $N($u['n']) }}. {{ $u['title'] }}</a> <span lang="en" class="text-base font-normal text-ink2">{{ $u['en'] }}</span></h2>
                @if ($img = \App\Http\Controllers\LearnController::preview($slug.'-1'))<img src="{{ $img }}" alt="{{ $u['title'] }}: ওয়ার্কশিট / {{ $u['en'] }} worksheet (PDF)" width="794" height="1000" loading="lazy" class="mt-2 h-auto w-40 rounded-lg border border-rule">@endif
                <ul class="mt-2 grid gap-2 sm:grid-cols-2">
                    @foreach ($u['lessons'] as $k => $l)
                        <li class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-rule p-2"><span class="font-bold">{{ $N($k + 1) }}. {{ $l['title'] }}</span>
                            <span class="flex gap-1"><a href="{{ $C::url('pdf/'.$slug.'-'.($k + 1).'-a4') }}" class="flex min-h-11 items-center rounded-full border-2 border-navy px-3 text-sm font-bold" aria-label="{{ $l['title'] }}: A4 PDF">A4</a><a href="{{ $C::url('pdf/'.$slug.'-'.($k + 1).'-letter') }}" class="flex min-h-11 items-center rounded-full border-2 border-navy px-3 text-sm font-bold" aria-label="{{ $l['title'] }}: US Letter PDF">Letter</a></span></li>
                    @endforeach
                </ul>
                <p class="mt-2"><a href="{{ $C::url('pdf/'.$slug.'-teacher-a4') }}" class="inline-flex min-h-11 items-center font-bold underline">শিক্ষকের পুরো সেট, উত্তরসহ (A4)</a> · <a href="{{ $C::url('pdf/'.$slug.'-teacher-letter') }}" class="inline-flex min-h-11 items-center font-bold underline">US Letter</a></p>
            </section>
            @if ($u['n'] === 7)<x-ad-slot spot="list" :height="250" />@endif
        @endforeach
    </article>
</x-layouts.app>
