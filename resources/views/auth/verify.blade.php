<x-layouts.app :seo="$seo">
    <x-form-card title="ই-মেল যাচাই করুন" subtitle="অ্যাকাউন্ট চালু করতে আপনার ই-মেলটি একবার যাচাই করা দরকার। তার আগে লেখা পাঠানো, মন্তব্য বা অ্যাকাউন্টের অন্য কিছু করা যাবে না।">
        <div class="rounded-2xl border-2 border-navy bg-sky-tint p-5 font-serif text-lg"><strong>{{ auth()->user()->email }}</strong> ঠিকানায় পাঠানো লিংকে চাপুন। ইনবক্সে না পেলে স্প্যাম ফোল্ডার দেখুন।</div>
        <form method="POST" action="{{ \App\Support\Url::to('/email/verification-notification') }}" class="mt-5">
            @csrf
            <button class="h-12 w-full rounded-full bg-red font-bold text-white">লিংক আবার পাঠান</button>
        </form>
        <form method="POST" action="{{ \App\Support\Url::to('/logout') }}" class="mt-3 text-center">@csrf<button class="text-sm text-ink2 underline">ভুল ই-মেল? লগ-আউট করে নতুন অ্যাকাউন্ট খুলুন</button></form>
    </x-form-card>
</x-layouts.app>
