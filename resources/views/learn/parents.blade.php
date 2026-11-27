{{-- D321: for parents: how to help (even with little Bengali), progress on this phone, moving progress, worksheets --}}
@php
    $U = fn ($p) => \App\Support\Url::to($p);
    $C = \App\Support\LearnCourse::class;
    $lessons = []; $items = [];
    foreach ($C::all() as [$slug, $n, $id]) {
        $l = $C::lesson($slug, $n);
        $lessons[] = ['id' => $id, 'title' => $l['title'], 'unit' => $units[$slug]['title'], 'url' => $C::url($slug, $n), 'new' => array_values(array_filter((array) $l['new'], fn ($x) => mb_strlen($x) === 1))];
        foreach ((array) $l['new'] as $x) { if (mb_strlen($x) === 1 && ! in_array($x, $items, true)) { $items[] = $x; } }
    }
@endphp
<x-layouts.app :seo="$seo">
    <article class="mx-auto max-w-[860px] px-5 py-8" data-learn-parents>
        @include('janun.partials.head', ['crumbs' => [['হাতপাখা', $U('/')], ['জেনে রাখুন', $U('/janun')], ['বাংলা শিখি', $C::url()], ['বাবা-মায়ের জন্য', '']], 'kicker' => 'বাংলা শিখি', 'title' => 'বাবা-মায়ের জন্য'])
        <p lang="en" class="mt-1 text-base text-ink2">Helping your child learn to read Bengali, even if you read little Bengali yourself</p>
        <section class="mt-6 print:hidden">
            <h2 class="font-display text-2xl font-extrabold">রোজ দশ মিনিট: কীভাবে</h2>
            <ol class="mt-3 list-decimal space-y-2 pl-6 font-serif text-lg">
                <li>একসঙ্গে বসুন, একটা পাঠ খুলুন। একদিনে একটাই।</li>
                <li>নতুন অক্ষর এলে আপনি জোরে বলুন, শিশু আপনার পরে বলুক। উপরের "বাবা-মা" বোতামে প্রতিটি অক্ষরের উচ্চারণ ইংরেজি হরফে লেখা আছে।</li>
                <li>ভুল হলে বকবেন না। পাঠ নিজেই ভুল প্রশ্ন শেষে আরেকবার দেয়।</li>
                <li>"জোরে পড়ো" পাতায় শিশু পড়তে পারলে আপনি ✓ টিপুন।</li>
                <li>সপ্তাহে একদিন ওয়ার্কশিট ছেপে হাতে লিখুক। পেনসিলে লেখা মনে থাকে বেশি।</li>
            </ol>
            <div lang="en" class="mt-4 rounded-2xl bg-sky-tint p-4 text-base">
                <p class="font-bold">In English</p>
                <p class="mt-1">Sit together for ten minutes a day, one lesson at a time. Say each new letter aloud and let your child repeat it (the "বাবা-মা" button in every lesson shows how each letter is said, in English letters). Don't correct harshly: the lesson brings wrong answers back once at the end. On the "read aloud" screen, tap ✓ when your child manages it. Once a week, print the worksheet: writing by hand helps letters stick.</p>
            </div>
        </section>
        <x-ad-slot spot="list" :height="250" />
        <section class="mt-6">
            <h2 class="font-display text-2xl font-extrabold">এই ফোনে অগ্রগতি</h2>
            <p class="text-base text-ink2 print:hidden">শুধু এই ফোন বা কম্পিউটারে রাখা আছে, কোথাও পাঠানো হয় না।</p>
            <div data-progress-out><p class="mt-3 text-lg">অগ্রগতি দেখতে জাভাস্ক্রিপ্ট চালু রাখুন।</p></div>
            <button type="button" data-print-card class="mt-3 min-h-11 rounded-full border-2 border-navy px-4 font-bold print:hidden">অগ্রগতির কার্ড ছাপুন</button>
        </section>
        <section class="mt-8 rounded-2xl border-2 border-navy p-5 print:hidden">
            <h2 class="font-display text-2xl font-extrabold">অন্য ফোনে নিয়ে যান</h2>
            <p class="mt-1 font-serif text-lg">এই ফোনে "কোড বানান" টিপুন, কোডটা নিজেকে হোয়াটসঅ্যাপ বা ইমেলে পাঠান, অন্য ফোনে এই পাতায় বসিয়ে "কোড বসান" টিপুন।</p>
            <label for="learn-code" class="mt-3 block font-bold">কোড</label>
            <textarea id="learn-code" data-code rows="3" class="mt-1 block w-full rounded-xl border-2 border-navy bg-white p-3 font-mono text-sm" spellcheck="false"></textarea>
            <div class="mt-2 flex flex-wrap gap-2">
                <button type="button" data-export class="min-h-11 rounded-full bg-navy px-4 font-bold text-white">কোড বানান</button>
                <button type="button" data-import class="min-h-11 rounded-full border-2 border-navy px-4 font-bold">কোড বসান</button>
            </div>
            <p data-code-msg role="status" class="mt-2 font-bold"></p>
        </section>
        <section class="mt-8 print:hidden">
            <h2 class="font-display text-2xl font-extrabold">যেখানে সবাই আটকায়</h2>
            <dl class="mt-3 space-y-3 font-serif text-lg">
                <div><dt class="font-bold">ই-কার আগে লেখা, পরে পড়া</dt><dd>দি = দ + ই, কিন্তু চিহ্নটা বসে বাঁদিকে। আঙুল দিয়ে আগে অক্ষর, পরে চিহ্ন ছুঁয়ে পড়ান।</dd></div>
                <div><dt class="font-bold">লুকোনো অ</dt><dd>কলম = কো-লোম, শেষের ম-এ অ নেই। প্রথমে অদ্ভুত লাগে, ধাপ ৫ এটাই শেখায়।</dd></div>
                <div><dt class="font-bold">ব আর র, ড আর ড়</dt><dd>দেখতে কাছাকাছি। পাঠের "কোনটা আলাদা?" খেলাটা এর জন্যই।</dd></div>
                <div><dt class="font-bold">যুক্তাক্ষর</dt><dd>প্রথমে যেগুলোয় দুটো অক্ষর দেখা যায় (ন্ত, স্ত), পরে নতুন চেহারার (ক্ষ, জ্ঞ)। পুরো তালিকা: <a href="{{ $U('/janun/learn-bengali/juktakkhor') }}" class="underline">যুক্তাক্ষর</a>।</dd></div>
            </dl>
        </section>
        <div class="mt-8 flex flex-wrap gap-2 print:hidden">
            <a href="{{ $C::url('start') }}" class="flex min-h-11 items-center rounded-full bg-red px-4 font-bold text-white">পাঠে ফিরুন</a>
            <a href="{{ $C::url('worksheets') }}" class="flex min-h-11 items-center rounded-full border-2 border-navy px-4 font-bold">সব ওয়ার্কশিট</a>
        </div>
        <div class="print:hidden"><x-newsletter-signup variant="card" /></div>
    </article>
    <script type="application/json" id="learn-data" nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">@json(['lessons' => $lessons, 'items' => $items, 'start' => $C::url('start')], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG)</script>
    @vite(['resources/js/learn.js'])
</x-layouts.app>
