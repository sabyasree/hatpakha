<x-layouts.app :seo="$seo">
    <x-form-card title="অ্যাকাউন্ট খুলুন" subtitle="লেখা পাঠাতে, আর নিজের লেখার অবস্থা দেখতে একটি অ্যাকাউন্ট লাগে। বিনামূল্যে।">
        <form method="POST" action="{{ \App\Support\Url::to('/register') }}">
            @csrf
            <input type="hidden" name="started" value="{{ $started }}">
            {{-- Honeypot: hidden from people, bots fill it --}}
            <div aria-hidden="true" class="absolute -left-[9999px] h-px w-px overflow-hidden"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
            <x-field name="name" label="আপনার নাম" autocomplete="name" hint="লেখা প্রকাশিত হলে এই নামটাই ছাপা হবে (পরে বদলানো যায়)।" required maxlength="80" />
            <x-field name="email" type="email" label="ইমেল" autocomplete="email" required />
            <x-field name="password" type="password" label="পাসওয়ার্ড" autocomplete="new-password" hint="অন্তত ১০ অক্ষর, অক্ষর আর সংখ্যা দুই-ই থাকবে। অন্য সাইটের পাসওয়ার্ড আবার ব্যবহার করবেন না।" required />
            <x-field name="password_confirmation" type="password" label="পাসওয়ার্ড আবার লিখুন" autocomplete="new-password" required />
            <label class="mb-3 flex items-start gap-3"><input type="checkbox" name="newsletter" value="1" checked class="mt-1 h-5 w-5">
                <span>সপ্তাহে একটি চিঠি পাঠান: নতুন লেখার খবর (যখন খুশি বন্ধ করা যায়)</span></label>
            <label class="mb-5 flex items-start gap-3"><input type="checkbox" name="terms" value="1" class="mt-1 h-5 w-5" required>
                <span>আমি <a href="{{ \App\Support\Url::to('/terms-conditions') }}" class="underline" target="_blank">শর্তাবলি</a> আর <a href="{{ \App\Support\Url::to('/privacy-policy') }}" class="underline" target="_blank">গোপনীয়তা নীতি</a> পড়েছি ও মেনে নিচ্ছি।</span></label>
            <x-turnstile action="register" />
            <button class="h-12 w-full rounded-full bg-red font-bold text-white">অ্যাকাউন্ট খুলুন</button>
            <p class="mt-3 text-sm text-ink2">অ্যাকাউন্ট খোলার পর আপনার ই-মেলে একটি লিংক যাবে। সেটায় চাপলেই অ্যাকাউন্ট চালু হবে।</p>
        </form>
        <p class="mt-6 text-center">আগেই অ্যাকাউন্ট আছে? <a href="{{ \App\Support\Url::to('/login') }}" class="font-bold text-red underline">লগ-ইন</a></p>
    </x-form-card>
</x-layouts.app>
