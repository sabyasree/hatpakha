{{-- D128: how to keep Hatpakha on a phone or computer (always reachable, from the footer and the menu), and phone notifications. --}}
<x-layouts.app :seo="$seo">
    <div class="h-1 bg-gradient-to-r from-pink via-red to-peach" aria-hidden="true"></div>
    <div class="mx-auto max-w-[760px] px-5 pb-14 pt-8">
        <div class="flex items-center gap-4">
            <img src="{{ asset('images/app/icon-192.png') }}" alt="" width="72" height="72" class="h-[72px] w-[72px] rounded-2xl border-2 border-navy">
            <h1 class="font-display text-4xl font-extrabold leading-tight md:text-5xl" style="font-stretch: 85%">হাতপাখা ফোনে</h1>
        </div>
        <p class="mt-4 text-lg">হাতপাখা ফোনের হোম স্ক্রিনে রাখুন। অ্যাপের মতো এক ছোঁয়ায় খুলবে, পুরো পর্দা জুড়ে; আগে পড়া লেখা ইন্টারনেট ছাড়াও পড়া যাবে; চাইলে নতুন লেখার খবর আসবে ফোনে। কোনো দোকান থেকে নামাতে হয় না, জায়গাও প্রায় লাগে না। বিনামূল্যে।</p>

        <p data-app-installed hidden class="mt-6 rounded-2xl bg-sky-tint p-4 font-bold text-sky-night" role="status">✓ আপনি হাতপাখা অ্যাপের মতোই খুলেছেন। নিচে ফোনে খবর চালু করতে পারেন।</p>
        <button type="button" data-install-now hidden class="mt-6 inline-flex h-14 items-center rounded-full bg-red px-8 text-lg font-bold text-white">এখনই যোগ করুন</button>

        <section id="android" class="mt-10 scroll-mt-24 rounded-3xl border-2 border-navy bg-white p-5 sm:p-6">
            <h2 class="text-2xl font-extrabold">Android ফোনে</h2>
            <ol class="mt-3 list-decimal space-y-2 pl-6">
                <li>hatpakha.com খুলুন <strong>Chrome</strong>-এ।</li>
                <li>ওপরে ডান দিকের <strong>⋮</strong> (তিন বিন্দু) ছুঁয়ে দিন।</li>
                <li><strong>Add to Home screen</strong> বা <strong>Install app</strong> (হোম স্ক্রিনে যোগ করুন) বাছুন।</li>
                <li><strong>Install</strong> / <strong>Add</strong> চাপুন। হোম স্ক্রিনে হাতপাখার আইকন চলে আসবে।</li>
            </ol>
            <p class="mt-3 text-sm text-ink2">Samsung Internet-এ: নিচের ☰ মেনু → <strong>Add page to</strong> → <strong>Home screen</strong>।</p>
        </section>

        <section id="iphone" class="mt-6 scroll-mt-24 rounded-3xl border-2 border-navy bg-white p-5 sm:p-6">
            <h2 class="text-2xl font-extrabold">iPhone বা iPad-এ</h2>
            <ol class="mt-3 list-decimal space-y-2 pl-6">
                <li>hatpakha.com খুলুন <strong>Safari</strong>-তে।</li>
                <li><strong>শেয়ার</strong> বোতাম ছুঁয়ে দিন: চৌকো থেকে ওপরে তীর (□↑)। নতুন iPhone-এ আগে নিচের <strong>•••</strong> ছুঁতে হতে পারে।</li>
                <li>তালিকা একটু নামিয়ে <strong>Add to Home Screen</strong> বাছুন।</li>
                <li><strong>Open as Web App</strong> দেখালে সেটি চালু রাখুন, তারপর ওপরে ডান দিকে <strong>Add</strong>।</li>
                <li>এবার <strong>হোম স্ক্রিনের হাতপাখা আইকন</strong> থেকে খুলুন। Safari থেকে খুললে আগের মতো ওয়েবসাইটই দেখাবে; আইকন থেকে খুললে অ্যাপের মতো, ব্রাউজারের বার ছাড়া।</li>
            </ol>
        </section>

        <section id="computer" class="mt-6 scroll-mt-24 rounded-3xl border-2 border-navy bg-white p-5 sm:p-6">
            <h2 class="text-2xl font-extrabold">কম্পিউটারে</h2>
            <ul class="mt-3 list-disc space-y-2 pl-6">
                <li><strong>Chrome বা Edge:</strong> ঠিকানার ঘরের ডান দিকে ইনস্টলের ছোট চিহ্ন (⊕ বা 🖥↓) ক্লিক করুন, অথবা মেনু → <strong>Install হাতপাখা</strong> / <strong>Apps → Install this site as an app</strong>।</li>
                <li><strong>Mac-এ Safari:</strong> মেনু <strong>File → Add to Dock</strong>।</li>
            </ul>
        </section>

        @if (\App\Support\WebPush::publicKey())
        <section id="notify" class="mt-10 scroll-mt-24 rounded-3xl bg-sky-tint p-5 sm:p-6">
            <h2 class="text-2xl font-extrabold">ফোনে নতুন লেখার খবর</h2>
            <p class="mt-2">অ্যাকাউন্ট লাগবে না। দিনে একবার, সন্ধ্যায়, সেদিন যা নতুন লেখা বেরোল তার একটি ছোট খবর; নতুন কিছু না থাকলে কিছুই আসবে না। ছুঁয়ে দিলেই লেখা খুলবে।</p>
            <div class="mt-4 flex flex-wrap gap-3">
                <x-push-button topic="daily" off="🔔 রোজকার নতুন লেখার খবর চালু করুন" on="✓ রোজকার খবর চালু আছে (বন্ধ করতে ছুঁয়ে দিন)" />
            </div>
            @php($pushSections = \App\Models\Section::where('is_active', true)->where('slug', '!=', \App\Models\Section::KIDS)->orderBy('sort')->get(['id', 'name_bn']))
            <p class="mt-4 font-bold">শুধু কিছু বিভাগের নতুন লেখা চান? যেটি চান ছুঁয়ে দিন (প্রতিটি নতুন লেখা প্রকাশ হলেই খবর):</p>
            <div class="mt-2 flex flex-wrap gap-2">
                @foreach ($pushSections as $ps)
                    <x-push-button :topic="'section:'.$ps->id" :off="$ps->name_bn" :on="'✓ '.$ps->name_bn" :small="true" />
                @endforeach
            </div>
            <p class="mt-3 text-sm" data-push-unsupported hidden>এই ব্রাউজারে ফোনে খবর পাঠানো যায় না। Android-এ Chrome ব্যবহার করুন; iPhone-এ আগে হাতপাখা হোম স্ক্রিনে যোগ করে (ওপরে দেখুন) আইকন থেকে খুলুন, তারপর এখানে এসে চালু করুন।</p>
            <p class="mt-3 text-sm text-ink2">কোনো প্রিয় লেখক বা ধারাবাহিক থাকলে, তাঁর বা সেটির পাতায় <strong>🔔 ফোনে জানান</strong> ছুঁয়ে দিন: শুধু তাঁদের নতুন লেখা এলেই খবর আসবে। যে-কোনো সময় একই বোতামে বন্ধ করা যায়।</p>
        </section>
        @endif

        <section class="mt-10">
            <h2 class="text-2xl font-extrabold">জেনে রাখুন</h2>
            <ul class="mt-3 list-disc space-y-2 pl-6 text-ink2">
                <li>এটা হাতপাখার ওয়েবসাইটই, ফোনে অ্যাপের মতো সাজানো। তাই সবসময় নতুন, কিছু আপডেট করতে হয় না।</li>
                <li>ইন্টারনেট না থাকলে আগে খোলা শেষ ৪০টি পাতা পড়া যাবে।</li>
                <li>সরাতে চাইলে: ফোনে আইকনটি চেপে ধরে <strong>Remove</strong> / <strong>Uninstall</strong>।</li>
            </ul>
        </section>
    </div>
</x-layouts.app>
