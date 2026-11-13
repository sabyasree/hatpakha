<x-layouts.app :seo="$seo">
    <x-form-card :title="__('টু-স্টেপ ভেরিফিকেশন')" :subtitle="__('আপনার অ্যাকাউন্টে টু-স্টেপ ভেরিফিকেশন চালু আছে। ফোনের অথেনটিকেটর অ্যাপে যে ৬ সংখ্যার কোড দেখাচ্ছে, সেটা দিন।')">
        <form method="POST" action="{{ \App\Support\Url::to('/login/2fa') }}">
            @csrf
            <x-field name="code" :label="__('কোড')" inputmode="numeric" autocomplete="one-time-code" maxlength="40" required autofocus
                :hint="__('ফোন কাছে না থাকলে, চালু করার সময় যে ব্যাকআপ কোডগুলো সেভ করেছিলেন, তার একটি দিন।')" />
            <button class="h-12 w-full rounded-full bg-red font-bold text-white">{{ __('ভেরিফাই করুন') }}</button>
        </form>
        <p class="mt-6 text-center text-sm text-ink2">{{ __('ফোন আর ব্যাকআপ কোড দুটোই হারিয়ে গেলে আমাদের জানান:') }} <a href="{{ \App\Support\Url::to('/contact-us') }}" class="underline">{{ __('যোগাযোগ') }}</a></p>
    </x-form-card>
</x-layouts.app>
