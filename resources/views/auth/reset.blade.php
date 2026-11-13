<x-layouts.app :seo="$seo">
    <x-form-card :title="__('নতুন পাসওয়ার্ড')">
        <form method="POST" action="{{ \App\Support\Url::to('/reset-password') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <x-field name="email" type="email" :label="__('ই-মেল')" :value="$email" autocomplete="email" required />
            <x-field name="password" type="password" :label="__('নতুন পাসওয়ার্ড')" autocomplete="new-password" :hint="__('অন্তত ১০টি অক্ষর, তার মধ্যে অক্ষর আর সংখ্যা দুটোই থাকবে।')" required />
            <x-field name="password_confirmation" type="password" :label="__('পাসওয়ার্ড আবার লিখুন')" autocomplete="new-password" required />
            <button class="h-12 w-full rounded-full bg-red font-bold text-white">{{ __('পাসওয়ার্ড সেভ করুন') }}</button>
        </form>
    </x-form-card>
</x-layouts.app>
