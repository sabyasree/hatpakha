<x-layouts.app :seo="$seo">
    <x-form-card title="লগ-ইন" subtitle="পুরনো হাতপাখার অ্যাকাউন্টের ইমেল আর পাসওয়ার্ডও এখানে চলবে।">
        <form method="POST" action="{{ \App\Support\Url::to('/login') }}">
            @csrf
            <x-field name="email" type="email" label="ইমেল" autocomplete="email" required autofocus />
            <x-field name="password" type="password" label="পাসওয়ার্ড" autocomplete="current-password" required />
            <label class="mb-5 flex min-h-11 items-center gap-3"><input type="checkbox" name="remember" value="1" class="h-5 w-5"> <span>মনে রাখুন</span></label>
            <button class="h-12 w-full rounded-full bg-red font-bold text-white">লগ-ইন</button>
        </form>
        <div class="mt-6 flex flex-col gap-3 text-center">
            <a href="{{ \App\Support\Url::to('/forgot-password') }}" class="font-semibold text-sky-deep underline">পাসওয়ার্ড ভুলে গেছেন?</a>
            <span>অ্যাকাউন্ট নেই? <a href="{{ \App\Support\Url::to('/register') }}" class="font-bold text-red underline">অ্যাকাউন্ট খুলুন</a></span>
        </div>
    </x-form-card>
</x-layouts.app>
