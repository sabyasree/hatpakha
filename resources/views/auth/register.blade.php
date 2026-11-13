<x-layouts.app :seo="$seo">
    <x-form-card :title="__('সাইন আপ')" :subtitle="__('লেখা পাঠাতে আর নিজের লেখার খবর দেখতে একটি অ্যাকাউন্ট লাগে। পুরো ফ্রি।')">
        <form method="POST" action="{{ \App\Support\Url::to('/register') }}">
            @csrf
            <input type="hidden" name="started" value="{{ $started }}">
            {{-- Honeypot: hidden from people, bots fill it --}}
            <div aria-hidden="true" class="absolute -left-[9999px] h-px w-px overflow-hidden"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
            <x-field name="name" :label="__('আপনার নাম')" autocomplete="name" :hint="__('লেখা ছাপা হলে এই নামটাই দেখাবে (পরে বদলানো যায়)।')" required maxlength="80" />
            <x-field name="email" type="email" :label="__('ই-মেল')" autocomplete="email" required />
            <x-field name="password" type="password" :label="__('পাসওয়ার্ড')" autocomplete="new-password" :hint="__('অন্তত ১০টি অক্ষর, তার মধ্যে অক্ষর আর সংখ্যা দুটোই থাকবে। অন্য সাইটের পাসওয়ার্ড এখানে দেবেন না।')" required />
            <x-field name="password_confirmation" type="password" :label="__('পাসওয়ার্ড আবার লিখুন')" autocomplete="new-password" required />
            <label class="mb-3 flex items-start gap-3"><input type="checkbox" name="newsletter" value="1" checked class="mt-1 h-6 w-6">
                <span>{{ __('সাপ্তাহিক ই-মেল পাঠান: নতুন লেখার খবর (যখন খুশি বন্ধ করা যায়)') }}</span></label>
            <label class="mb-5 flex items-start gap-3"><input type="checkbox" name="terms" value="1" class="mt-1 h-6 w-6" required>
                <span>{{ __('আমি পড়েছি ও মেনে নিচ্ছি:') }} <a href="{{ \App\Support\Url::to('/terms-conditions') }}" class="underline" target="_blank">{{ __('শর্তাবলি') }}</a> {{ __('আর') }} <a href="{{ \App\Support\Url::to('/privacy-policy') }}" class="underline" target="_blank">{{ __('প্রাইভেসি পলিসি') }}</a></span></label>
            <x-turnstile action="register" />
            <button class="h-12 w-full rounded-full bg-red font-bold text-white">{{ __('সাইন আপ') }}</button>
            <p class="mt-3 text-sm text-ink2">{{ __('সাইন আপের পর আপনার ই-মেলে একটি লিংক যাবে। সেটায় ক্লিক করলেই অ্যাকাউন্ট চালু হবে।') }}</p>
        </form>
        <p class="mt-6 text-center">{{ __('আগেই অ্যাকাউন্ট আছে?') }} <a href="{{ \App\Support\Url::to('/login') }}" class="font-bold text-red underline">{{ __('সাইন ইন') }}</a></p>
    </x-form-card>
</x-layouts.app>
