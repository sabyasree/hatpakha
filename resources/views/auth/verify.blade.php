<x-layouts.app :seo="$seo">
    <x-form-card :title="__('ই-মেল ভেরিফাই করুন')" :subtitle="__('অ্যাকাউন্ট চালু করতে আপনার ই-মেল একবার ভেরিফাই করতে হবে। তার আগে লেখা পাঠানো, মন্তব্য বা অ্যাকাউন্টের অন্য কিছু করা যাবে না।')">
        <div class="rounded-2xl border-2 border-navy bg-sky-tint p-5 font-serif text-lg"><strong>{{ auth()->user()->email }}</strong>: {{ __('এই ঠিকানায় পাঠানো লিংকে ক্লিক করুন। ইনবক্সে না পেলে স্প্যাম ফোল্ডার দেখুন।') }}</div>
        <form method="POST" action="{{ \App\Support\Url::to('/email/verification-notification') }}" class="mt-5">
            @csrf
            <button class="h-12 w-full rounded-full bg-red font-bold text-white">{{ __('লিংক আবার পাঠান') }}</button>
        </form>
        <form method="POST" action="{{ \App\Support\Url::to('/logout') }}" class="mt-3 text-center">@csrf<button class="text-sm text-ink2 underline">{{ __('ভুল ই-মেল? লগ আউট করে আবার সাইন আপ করুন') }}</button></form>
    </x-form-card>
</x-layouts.app>
