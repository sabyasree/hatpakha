<x-layouts.app :seo="$seo">
    <x-form-card title="পাসওয়ার্ড ভুলে গেছেন" subtitle="অ্যাকাউন্টের ইমেল লিখুন। নতুন পাসওয়ার্ড বানানোর একটি লিংক পাঠানো হবে।">
        <form method="POST" action="{{ \App\Support\Url::to('/forgot-password') }}">
            @csrf
            <x-field name="email" type="email" label="ইমেল" autocomplete="email" required autofocus />
            <x-turnstile action="forgot" />
            <button class="h-12 w-full rounded-full bg-red font-bold text-white">লিংক পাঠান</button>
        </form>
    </x-form-card>
</x-layouts.app>
