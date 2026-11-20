{{-- D234 (owner: "add this video and pdf to the rules page, at the top, so users find it easily"): for a first-time
     writer, before the rules: the three steps in words, a one-minute silent film of the real screens, the same as a
     PDF with pictures, and the way in. The words carry everything; the film and the PDF only show it. --}}
<section aria-labelledby="howto-h" class="mt-6 rounded-2xl border-2 border-navy bg-peach-light p-5 shadow-[4px_4px_0_#13212C]">
    <p class="font-hand text-2xl text-red">প্রথমবার পাঠাচ্ছেন?</p>
    <h2 id="howto-h" class="font-display text-3xl font-extrabold leading-tight">এক মিনিটে দেখে নিন কীভাবে পাঠাবেন</h2>
    <div class="mt-4 grid gap-5 sm:grid-cols-[220px_1fr] sm:items-start">
        <figure class="order-2 mx-auto w-full max-w-[220px] sm:order-1">
            <figcaption class="mb-1 text-center text-sm font-semibold text-ink2">এক মিনিটের ভিডিও। প্রতিটি ধাপ লেখায় দেখানো আছে।</figcaption>
            <video controls muted playsinline preload="none" width="720" height="1280" poster="{{ asset('guide/lekha-pathano.jpg') }}"
                class="block aspect-[9/16] h-auto w-full rounded-xl border-2 border-navy bg-navy" aria-describedby="howto-steps">
                <source src="{{ asset('guide/lekha-pathano.mp4') }}" type="video/mp4">
            </video>
        </figure>
        <div class="order-1 sm:order-2">
            {{-- D249 (owner: "many people are saying this is tough… make it very very simple for a non-computer person"):
                 three short steps, the Google way first; the e-mail way folded away under "Gmail নেই?". --}}
            @php $google = \App\Auth\GoogleSignIn::ready(); @endphp
            <ol id="howto-steps" class="grid gap-4">
                <li class="flex gap-3">
                    <span aria-hidden="true" class="flex h-10 w-10 flex-none items-center justify-center rounded-full bg-red text-lg font-bold text-white">১</span>
                    <span><strong class="block text-xl leading-snug">ঢুকুন</strong>
                        @if ($google)<span class="text-lg">নিচের লাল “এখনই লেখা পাঠান” বোতাম টিপুন। তারপর “Google দিয়ে ঢুকুন” টিপুন। একটা নতুন পাতা খুলবে (ইংরেজিতে হতে পারে): সেখানে আপনার ই-মেল ঠিকানা (শেষে @gmail.com) দেখাবে, তাতে টিপুন। “Continue” লেখা এলে সেটাও টিপুন। ব্যস।</span>
                        @else<span class="text-lg">নিচের লাল “এখনই লেখা পাঠান” বোতাম টিপুন। নাম, ই-মেল আর একটা পাসওয়ার্ড দিয়ে “সাইন আপ” টিপুন। তারপর নিজের ই-মেলে আসা চিঠির বোতামটা একবার টিপুন।</span>@endif
                    </span>
                </li>
                <li class="flex gap-3">
                    <span aria-hidden="true" class="flex h-10 w-10 flex-none items-center justify-center rounded-full bg-red text-lg font-bold text-white">২</span>
                    <span><strong class="block text-xl leading-snug">লেখা বসান</strong><span class="text-lg">কবিতা না গল্প, বেছে নিন। “না, এটি একটাই লেখা” বাছুন। নাম (শিরোনাম) লিখুন। লেখাটা টাইপ করুন বা কপি করে বসান। নিচে “লেখাটি আমার নিজের” ঘরে টিক দিন।</span></span>
                </li>
                <li class="flex gap-3">
                    <span aria-hidden="true" class="flex h-10 w-10 flex-none items-center justify-center rounded-full bg-red text-lg font-bold text-white">৩</span>
                    <span><strong class="block text-xl leading-snug">“জমা দিন” টিপুন</strong><span class="text-lg">লেখা আমাদের কাছে পৌঁছে যাবে। সম্পাদক পড়ে ই-মেলে জানাবেন।</span></span>
                </li>
            </ol>
            @if ($google)
                <details class="mt-4 rounded-xl border-2 border-navy bg-white px-4 py-2">
                    <summary class="flex min-h-11 cursor-pointer items-center justify-between font-bold">Gmail নেই? <span aria-hidden="true">▾</span></summary>
                    <p class="pb-2 text-lg">যে কোনো ই-মেল দিয়েও হয়। “সাইন আপ” পাতায় নাম, ই-মেল আর একটা পাসওয়ার্ড লিখে “সাইন আপ” টিপুন। তারপর নিজের ই-মেল খুলুন: হাতপাখা থেকে একটা চিঠি আসবে, তার বোতামটা একবার টিপুন। চিঠি না দেখলে “স্প্যাম” দেখুন।</p>
                </details>
            @endif
            <p class="mt-4 rounded-xl bg-white px-4 py-3 text-lg"><strong>ঢুকেছেন কি না, বুঝবেন কীভাবে?</strong> পাতার একদম উপরে ডান দিকে “সাইন ইন” লেখাটা আর না থাকলে বুঝবেন ঢুকে গেছেন।</p>
            <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                <a href="{{ \App\Support\Url::to('/submit') }}" class="flex min-h-12 items-center justify-center rounded-full bg-red px-7 text-lg font-bold text-white shadow-[3px_3px_0_#13212C]">এখনই লেখা পাঠান</a>
                <a href="{{ asset('guide/lekha-pathanor-niyom.pdf') }}" class="flex min-h-12 items-center justify-center rounded-full border-2 border-navy bg-white px-6 font-bold" type="application/pdf">ছবিসহ ধাপে ধাপে নির্দেশিকা (PDF, ৪ পাতা)</a>
            <p class="mt-4 text-lg">আটকে গেলে <a href="{{ \App\Support\Url::to('/contact-us') }}" class="font-bold text-sky-night underline">যোগাযোগ পাতা</a> থেকে লিখুন, আমরা সাহায্য করব।</p>
            </div>
        </div>
    </div>
</section>
