<x-layouts.app :seo="$seo">
    <x-form-card :title="__('পাসওয়ার্ড ভুলে গেছেন?')" :subtitle="__('অ্যাকাউন্টের ই-মেল লিখুন। নতুন পাসওয়ার্ড দেওয়ার একটি লিংক পাঠানো হবে।')">
        <form method="POST" action="{{ \App\Support\Url::to('/forgot-password') }}">
            @csrf
            <x-field name="email" type="email" :label="__('ই-মেল')" autocomplete="email" required autofocus />
            <x-turnstile action="forgot" />
            <button class="h-12 w-full rounded-full bg-red font-bold text-white">{{ __('লিংক পাঠান') }}</button>
        </form>
    </x-form-card>
</x-layouts.app>
