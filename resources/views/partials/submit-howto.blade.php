{{-- D234 (owner: "add this video and pdf to the rules page, at the top, so users find it easily"): for a first-time
     writer, before the rules: the three steps in words, a one-minute silent film of the real screens, the same as a
     PDF with pictures, and the way in. The words carry everything; the film and the PDF only show it. --}}
<section aria-labelledby="howto-h" class="mt-6 rounded-2xl border-2 border-navy bg-peach-light p-5 shadow-[4px_4px_0_#13212C]">
    <p class="font-hand text-2xl text-red">প্রথমবার পাঠাচ্ছেন?</p>
    <h2 id="howto-h" class="font-display text-3xl font-extrabold leading-tight">এক মিনিটে দেখে নিন কীভাবে পাঠাবেন</h2>
    <div class="mt-4 grid gap-5 sm:grid-cols-[220px_1fr] sm:items-start">
        <figure class="order-2 mx-auto w-full max-w-[220px] sm:order-1">
            <figcaption class="mb-1 text-center text-sm font-semibold text-ink2">এক মিনিটের ভিডিও। শব্দ নেই; প্রতিটি ধাপ লেখায় দেখানো আছে।</figcaption>
            <video controls muted playsinline preload="none" width="720" height="1280" poster="{{ asset('guide/lekha-pathano.jpg') }}"
                class="block aspect-[9/16] h-auto w-full rounded-xl border-2 border-navy bg-navy" aria-describedby="howto-steps">
                <source src="{{ asset('guide/lekha-pathano.mp4') }}" type="video/mp4">
            </video>
        </figure>
        <div class="order-1 sm:order-2">
            <ol id="howto-steps" class="grid gap-3">
                @php $google = \App\Auth\GoogleSignIn::ready(); @endphp
                @foreach ([
                    ['অ্যাকাউন্ট খুলুন', $google ? 'সবচেয়ে সহজ: “Google দিয়ে অ্যাকাউন্ট খুলুন” টিপুন, পাসওয়ার্ড লাগে না। নইলে নাম, ই-মেল আর একটা পাসওয়ার্ড দিন। একবারই খুলতে হয়।' : 'নাম, ই-মেল আর একটা পাসওয়ার্ড। একবারই খুলতে হয়।'],
                    ['নিজের ই-মেল খুলে চিঠির বোতামটা টিপুন', ($google ? 'Google দিয়ে খুললে এই ধাপ লাগে না। ' : '').'হাতপাখা থেকে একটা চিঠি যাবে। না পেলে স্প্যাম ফোল্ডার দেখুন।'],
                    ['ফর্মে লেখা বসিয়ে “জমা দিন”', 'লেখাটা টাইপ করুন, কপি করে বসান, বা ওয়ার্ড ফাইল বেছে নিন।'],
                ] as $i => [$title, $line])
                    <li class="flex gap-3">
                        <span aria-hidden="true" class="flex h-9 w-9 flex-none items-center justify-center rounded-full bg-red font-bold text-white">{{ \App\Support\Bn::num((string) ($i + 1)) }}</span>
                        <span><strong class="block text-lg leading-snug">{{ $title }}</strong><span class="text-ink2">{{ $line }}</span></span>
                    </li>
                @endforeach
            </ol>
            <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                <a href="{{ \App\Support\Url::to('/submit') }}" class="flex min-h-12 items-center justify-center rounded-full bg-red px-7 text-lg font-bold text-white shadow-[3px_3px_0_#13212C]">লেখা পাঠান</a>
                <a href="{{ asset('guide/lekha-pathanor-niyom.pdf') }}" class="flex min-h-12 items-center justify-center rounded-full border-2 border-navy bg-white px-6 font-bold" type="application/pdf">ছবিসহ ধাপে ধাপে নির্দেশিকা (PDF, ৪ পাতা)</a>
            </div>
        </div>
    </div>
</section>
