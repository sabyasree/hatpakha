<x-layouts.app :seo="$seo">
    <x-form-card title="নতুন পাসওয়ার্ড">
        <form method="POST" action="{{ \App\Support\Url::to('/reset-password') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <x-field name="email" type="email" label="ইমেল" :value="$email" autocomplete="email" required />
            <x-field name="password" type="password" label="নতুন পাসওয়ার্ড" autocomplete="new-password" hint="অন্তত ১০ অক্ষর, অক্ষর আর সংখ্যা দুই-ই থাকবে।" required />
            <x-field name="password_confirmation" type="password" label="আবার লিখুন" autocomplete="new-password" required />
            <button class="h-12 w-full rounded-full bg-red font-bold text-white">পাসওয়ার্ড বদলান</button>
        </form>
    </x-form-card>
</x-layouts.app>
