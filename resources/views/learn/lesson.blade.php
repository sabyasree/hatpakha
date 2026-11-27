{{-- D321: one lesson, played on the phone by resources/js/learn.js: meet, watch it join, trace, practise, read aloud, stars --}}
<x-layouts.learn :seo="$seo" :data="$data">
    <div data-learn-lesson class="mx-auto flex min-h-screen max-w-[640px] flex-col px-4 pb-[calc(1rem+env(safe-area-inset-bottom))] pt-[max(0.75rem,env(safe-area-inset-top))]">
        <header class="flex items-center gap-3">
            <a href="{{ \App\Support\LearnCourse::url('start') }}" class="flex h-12 w-12 flex-none items-center justify-center rounded-full border-2 border-navy bg-paper text-2xl font-bold" aria-label="পাঠের ম্যাপে ফিরি" data-en-label="Back to the map">✕</a>
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-bold text-ink2" data-en="{{ $u['n'] }}. {{ $u['en'] }} · Lesson {{ $data['n'] }}">{{ \App\Support\Bn::num((string) $u['n']) }}. {{ $u['title'] }} · পাঠ {{ \App\Support\Bn::num((string) $data['n']) }}</p>
                <div class="mt-1 h-3 overflow-hidden rounded-full bg-paper ring-2 ring-navy" role="progressbar" aria-label="এই পাঠে কতটা হল" data-en-label="How far through this lesson" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" data-progress>
                    <div class="h-full w-0 rounded-full bg-red transition-[width] duration-300" data-progress-bar></div>
                </div>
            </div>
            <button type="button" data-parent-open class="flex h-12 flex-none items-center rounded-full border-2 border-navy bg-paper px-3 text-sm font-bold"><span data-en="Grown-ups">বাবা-মা</span></button>
        </header>
        <main data-stage class="flex flex-1 flex-col items-center justify-center py-4 text-center">
            {{-- Without JavaScript: the lesson as a simple list --}}
            <noscript>
                <h1 class="font-display text-3xl font-extrabold">{{ $l['title'] }}</h1>
                <ul class="mt-4 space-y-3 text-left">
                    @foreach ((array) ($l['words'] ?? []) as $w)<li><span class="text-4xl">{{ $w[3] }}</span> <span class="font-display text-4xl font-extrabold">{{ $w[0] }}</span> <span lang="en" class="text-ink2">{{ $w[1] }} · {{ $w[2] }}</span></li>@endforeach
                    @foreach ((array) ($l['sentences'] ?? []) as $w)<li><span class="font-display text-3xl font-extrabold">{{ $w[0] }}</span> <span lang="en" class="block text-ink2">{{ $w[2] }}</span></li>@endforeach
                </ul>
                @if (isset($l['story']))<p class="mt-4 whitespace-pre-line text-left font-serif text-2xl leading-loose">{{ $l['story']['text'] }}</p>@endif
            </noscript>
        </main>
        <footer data-actions class="flex min-h-16 items-center justify-center gap-3"></footer>
    </div>
    <dialog data-parent class="m-auto w-[min(94vw,520px)] rounded-2xl border-2 border-navy bg-paper p-5 text-left text-navy backdrop:bg-navy/60" aria-labelledby="parent-h">
        <div class="flex items-start justify-between gap-3"><h2 id="parent-h" class="font-display text-xl font-extrabold" data-en="For grown-ups">বাবা-মায়ের জন্য</h2>
            <button type="button" data-parent-x class="flex h-12 w-12 flex-none items-center justify-center rounded-full border-2 border-navy text-xl font-bold" aria-label="বন্ধ" data-en-label="Close">✕</button></div>
        <fieldset class="mt-2 space-y-1">
            <legend class="font-bold">নির্দেশ / Instructions</legend>
            <label class="flex min-h-11 items-center gap-3"><input type="radio" name="ui" value="bn" data-ui class="h-6 w-6 accent-red"> <span>বাংলায়</span></label>
            <label class="flex min-h-11 items-center gap-3"><input type="radio" name="ui" value="en" data-ui class="h-6 w-6 accent-red"> <span lang="en">In English (for learners who don't read Bengali yet)</span></label>
        </fieldset>
        <p class="mt-2 font-serif text-lg">{{ $u['tip']['bn'] }}</p>
        <p lang="en" class="mt-2 text-base">{{ $u['tip']['en'] }}</p>
        <div data-parent-sounds class="mt-3 text-base"></div>
        <fieldset class="mt-4 space-y-2">
            <legend class="font-bold" data-en="Show">দেখাবে</legend>
            <label class="flex min-h-11 items-center gap-3"><input type="checkbox" data-setting="roman" class="h-6 w-6 accent-red"> <span><span data-en="How to say it, in English letters">রোমান হরফে উচ্চারণ</span> <span lang="en">(ma, baba)</span></span></label>
            <label class="flex min-h-11 items-center gap-3"><input type="checkbox" data-setting="en" class="h-6 w-6 accent-red"> <span><span data-en="English meaning">ইংরেজি মানে</span></span></label>
        </fieldset>
        <div class="mt-4 flex flex-wrap gap-2">
            <a href="{{ \App\Support\LearnCourse::url($data['unit'], $data['n'], '/print') }}" class="flex min-h-11 items-center rounded-full border-2 border-navy px-4 font-bold" data-en="Worksheet">ওয়ার্কশিট</a>
            <a href="{{ \App\Support\LearnCourse::url('english') }}" class="flex min-h-11 items-center rounded-full border-2 border-navy px-4 font-bold" lang="en">Sound guide</a>
            <a href="{{ \App\Support\LearnCourse::url('parents') }}" data-gate class="flex min-h-11 items-center rounded-full border-2 border-navy px-4 font-bold" data-en="Progress and tips">অগ্রগতি ও পরামর্শ</a>
            <button type="button" data-parent-close class="min-h-11 rounded-full bg-navy px-5 font-bold text-white" data-en="Close">বন্ধ</button>
        </div>
    </dialog>
</x-layouts.learn>
