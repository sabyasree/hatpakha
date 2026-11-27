{{-- D322 (owner: "if someone doesn't know Bengali much, they should also learn… parents… or any foreigner"): the course
     explained in English, how Bengali writing works, and every sound compared with English. For grown-ups; ads allowed. --}}
@php
    $U = fn ($p) => \App\Support\Url::to($p);
    $C = \App\Support\LearnCourse::class;
    $row = fn ($l, $s) => ['l' => $l, 'r' => $s[0], 'h' => $s[1]];
@endphp
<x-layouts.app :seo="$seo">
    <article class="mx-auto max-w-[860px] px-5 py-8" lang="en">
        <nav aria-label="Breadcrumb" class="text-sm text-ink2"><a href="{{ $U('/janun') }}" class="inline-flex min-h-11 items-center underline">জেনে রাখুন</a> › <a href="{{ $C::url() }}" class="inline-flex min-h-11 items-center underline">বাংলা শিখি</a> › <span aria-current="page">In English</span></nav>
        <span class="mt-3 inline-block rounded-full bg-sky-tint px-3 py-1 text-sm font-bold text-sky-night">Free · no account · no sign-up</span>
        <h1 class="mt-2 font-display text-3xl font-extrabold leading-tight sm:text-5xl">Learn to read Bengali, even if you don't read it yourself</h1>
        <p class="mt-4 font-serif text-xl leading-relaxed">For parents who grew up speaking Bengali but never learnt to read it, for families where one parent doesn't speak it, and for anyone curious about the script. The course gives every instruction in English, every word with an English meaning and a reading in English letters, and starts with only a few letters, so you can learn alongside your child.</p>
        <div class="mt-6 grid gap-3 sm:grid-cols-2">
            <a href="{{ $C::url('start') }}?ui=en" class="flex min-h-20 flex-col justify-center rounded-2xl bg-red px-5 py-4 text-white shadow-[4px_4px_0_#13212C]"><span class="font-display text-2xl font-extrabold">Start the course in English →</span><span class="text-base">{{ $C::count() }} short lessons, about 10 minutes a day</span></a>
            <a href="#sounds" class="flex min-h-20 flex-col justify-center rounded-2xl border-2 border-navy bg-paper px-5 py-4"><span class="font-display text-2xl font-extrabold">How each letter sounds ↓</span><span class="text-base">compared with English</span></a>
        </div>

        <h2 class="mt-10 font-display text-3xl font-extrabold">How Bengali writing works, in five ideas</h2>
        <ol class="mt-4 space-y-4 font-serif text-lg leading-relaxed">
            <li><b>1. Every consonant has a built-in vowel, "o".</b> <span lang="bn" class="font-display text-2xl font-extrabold">ক</span> is read "ko", not "k". So <span lang="bn" class="font-display text-2xl font-extrabold">কলম</span> (pen) is "ko-lom". At the end of a word the "o" is usually dropped: <span lang="bn" class="font-display text-2xl font-extrabold">কাক</span> is "kak".</li>
            <li><b>2. Other vowels are small signs (kar) around the consonant.</b> <span lang="bn" class="font-display text-2xl font-extrabold">ক + া = কা</span> "ka". Some signs sit after the letter, some under it, some <em>before</em> it although they are said after: <span lang="bn" class="font-display text-2xl font-extrabold">কি</span> is "ki", with the sign written on the left.</li>
            <li><b>3. A vowel at the start of a word is written as a full letter.</b> <span lang="bn" class="font-display text-2xl font-extrabold">আম</span> (mango) starts with the letter আ, which is the same sound as the sign া.</li>
            <li><b>4. Consonants can join into one shape (juktakkhor).</b> Often you can still see both letters: <span lang="bn" class="font-display text-2xl font-extrabold">ন্ত = ন + ত</span> "nt". A few look quite new: <span lang="bn" class="font-display text-2xl font-extrabold">ক্ষ</span> is ক + ষ, said "kkh".</li>
            <li><b>5. There are no capital letters.</b> Words hang from a line along the top (matra). A sentence ends with a vertical bar <span lang="bn" class="font-display text-2xl font-extrabold">।</span> instead of a full stop. It is read left to right, like English.</li>
        </ol>
        <x-ad-slot spot="list" :height="250" />

        <h2 id="sounds" class="mt-10 scroll-mt-20 font-display text-3xl font-extrabold">How each letter sounds</h2>
        <p class="mt-2 font-serif text-lg">English comparisons are only approximate. Two things English doesn't have: <b>soft and hard t/d</b> (soft: tongue on the teeth, ত দ; hard: tongue curled back, ট ড), and <b>breathy pairs</b> (ক k and খ kh: the second has a puff of air). Recordings in a human voice are being added to the lessons.</p>
        @foreach (['vowels' => 'Vowels (shown as full letters; inside words they become signs)', 'consonants' => 'Consonants (each read with "o" unless a sign follows)', 'signs' => 'Small signs'] as $group => $title)
            <h3 class="mt-6 font-display text-2xl font-extrabold">{{ $title }}</h3>
            <div class="mt-3 overflow-x-auto">
                <table class="w-full text-left">
                    <thead><tr class="border-b-2 border-navy text-sm"><th class="py-2 pr-3">Letter</th><th class="py-2 pr-3">In English letters</th><th class="py-2">Sounds like</th></tr></thead>
                    <tbody>
                        @foreach ($sounds[$group] as $l => $s)
                            <tr class="border-b border-rule"><th scope="row" class="py-2 pr-3 align-top font-display text-4xl font-extrabold" lang="bn">{{ $l }}</th><td class="py-2 pr-3 align-top text-lg font-bold">{{ $s[0] }}</td><td class="py-2 align-top text-lg">{{ $s[1] }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($group === 'consonants')<x-ad-slot spot="list" :height="250" />@endif
        @endforeach

        <h2 class="mt-10 font-display text-3xl font-extrabold">If you are a parent who doesn't read Bengali</h2>
        <ul class="mt-3 list-disc space-y-2 pl-6 font-serif text-lg">
            <li><b>Learn together, one step ahead.</b> Do tonight's lesson on your own first (five minutes), then with your child.</li>
            <li><b>Use the grown-ups' button</b> in every lesson: it shows how the new letter sounds, in English.</li>
            <li><b>Say it, don't spell it.</b> Children learn letters by their sounds ("mo", "ba"), not by names.</li>
            <li><b>Your Bengali-speaking relatives can help by phone:</b> ask a grandparent to read the lesson's words aloud on a video call.</li>
            <li><b>Words from both Bengals.</b> Some everyday words differ between families and between West Bengal and Bangladesh (জল / পানি for water). Both are right; use your family's.</li>
        </ul>

        <h2 class="mt-10 font-display text-3xl font-extrabold">The course, step by step</h2>
        <ol class="mt-3 space-y-2">
            @foreach ($units as $slug => $u)
                <li><a href="{{ $C::url($slug) }}" class="flex min-h-11 items-center gap-3 rounded-xl border-2 border-navy px-3 py-2 hover:bg-peach-light"><span class="text-2xl" aria-hidden="true">{{ $u['sticker'][0] }}</span><span><b>{{ $u['n'] }}. {{ $u['en'] }}</b> <span lang="bn" class="text-ink2">{{ $u['title'] }}</span></span></a></li>
            @endforeach
        </ol>
        <div class="mt-6 flex flex-wrap gap-2">
            <a href="{{ $C::url('start') }}?ui=en" class="flex min-h-12 items-center rounded-full bg-red px-5 font-bold text-white">Start in English →</a>
            <a href="{{ $C::url('worksheets') }}" class="flex min-h-12 items-center rounded-full border-2 border-navy px-5 font-bold">Free worksheets (PDF)</a>
            <a href="{{ $U('/bornomala') }}" class="flex min-h-12 items-center rounded-full border-2 border-navy px-5 font-bold">The alphabet with pictures</a>
        </div>
    </article>
</x-layouts.app>
