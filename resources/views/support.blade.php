{{-- D237: voluntary support. Plain words, no pressure, nothing unlocked by paying. --}}
<x-layouts.app :seo="$seo">
    <section class="mx-auto max-w-[680px] px-5 pb-16 pt-10">
        <div class="font-hand text-2xl text-red">এক কাপ চা</div>
        <h1 class="font-display text-5xl font-extrabold leading-tight" style="font-stretch: 80%">হাতপাখার পাশে থাকুন</h1>
        <div class="mt-4 space-y-3 font-serif text-lg">
            <p>হাতপাখা পড়তে বা লেখা পাঠাতে কোনো খরচ নেই, আর তা-ই থাকবে। পত্রিকাটি চলে নিজেদের সময় আর খরচে।</p>
            <p>পড়ে ভালো লাগলে নিজের ইচ্ছেমতো সাহায্য করতে পারেন। কত দেবেন, দেবেন কি না, পুরোটাই আপনার ইচ্ছে। না দিলেও আপনি আমাদের ততটাই আপন পাঠক।</p>
        </div>
        <div class="mt-6 rounded-2xl border-2 border-navy bg-white p-5 shadow-[4px_4px_0_#13212C]">
            <h2 class="font-display text-2xl font-extrabold">UPI দিয়ে পাঠান</h2>
            <p class="mt-1 text-ink2">GPay, PhonePe, Paytm বা যে কোনো UPI অ্যাপে নিচের ঠিকানায় পাঠানো যায়। (UPI ভারতের ব্যাংক অ্যাকাউন্টে চলে।)</p>
            <p class="mt-4 break-all rounded-xl bg-peach-light p-4 text-center font-mono text-xl font-bold" data-upi>{{ $upi }}</p>
            <div class="mt-4 flex flex-col gap-3 sm:flex-row">
                <a href="{{ $pay }}" class="flex min-h-12 flex-1 items-center justify-center rounded-full bg-red px-6 text-lg font-bold text-white shadow-[3px_3px_0_#13212C]">ফোনের UPI অ্যাপ খুলুন</a>
                <button type="button" data-copy-upi class="flex min-h-12 flex-1 items-center justify-center rounded-full border-2 border-navy bg-white px-6 font-bold">ঠিকানা কপি করুন</button>
            </div>
            <p class="mt-3 text-sm text-ink2">“অ্যাপ খুলুন” বোতামটা ফোনে কাজ করে। কম্পিউটারে থাকলে ঠিকানাটা কপি করে ফোনের অ্যাপে বসান।</p>
        </div>
        <ul class="mt-6 list-disc space-y-1 ps-6 text-ink2">
            <li>এটা স্বেচ্ছায় দেওয়া সাহায্য; এর বদলে কোনো বাড়তি সুবিধা বা লেখা ছাপার নিশ্চয়তা নেই।</li>
            <li>টাকা না দিয়েও পাশে থাকা যায়: একটা লেখা বন্ধুকে পাঠান, বা নিজে একটা লেখা লিখুন।</li>
        </ul>
        <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
            document.querySelector('[data-copy-upi]')?.addEventListener('click', async (e) => {
                try { await navigator.clipboard.writeText(document.querySelector('[data-upi]').textContent.trim()); e.currentTarget.textContent = 'কপি হয়েছে ✓'; } catch (err) { e.currentTarget.textContent = 'ঠিকানাটা চেপে ধরে কপি করুন'; }
            });
        </script>
    </section>
</x-layouts.app>
