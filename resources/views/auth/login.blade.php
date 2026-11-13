<x-layouts.app :seo="$seo">
    <x-form-card :title="__('সাইন ইন')" :subtitle="__('পুরনো হাতপাখা অ্যাকাউন্টের ই-মেল আর পাসওয়ার্ড দিয়েও সাইন ইন করা যাবে।')">
        <p class="-mt-2 mb-5 text-center">{{ __('নতুন?') }} <a href="{{ \App\Support\Url::to('/register') }}" class="font-bold text-red underline">{{ __('ফ্রিতে সাইন আপ করুন') }}</a></p>
        <form method="POST" action="{{ \App\Support\Url::to('/login') }}">
            @csrf
            <x-field name="email" type="email" :label="__('ই-মেল')" autocomplete="email" required autofocus />
            <x-field name="password" type="password" :label="__('পাসওয়ার্ড')" autocomplete="current-password" required />
            <label class="mb-5 flex min-h-11 items-center gap-3"><input type="checkbox" name="remember" value="1" class="h-6 w-6"> <span>{{ __('আমাকে মনে রাখুন') }}</span></label>
            <x-turnstile action="login" />
            <button class="h-12 w-full rounded-full bg-red font-bold text-white">{{ __('সাইন ইন') }}</button>
        </form>
        <div class="mt-6 flex flex-col gap-3 text-center">
            <a href="{{ \App\Support\Url::to('/forgot-password') }}" class="inline-flex min-h-11 items-center font-semibold text-sky-night underline">{{ __('পাসওয়ার্ড ভুলে গেছেন?') }}</a>
        </div>
    </x-form-card>
</x-layouts.app>
