<x-layouts.app :seo="$seo">
    <x-form-card title="ইমেল যাচাই করুন" subtitle="লেখা জমা দেওয়ার আগে আপনার ইমেলটি যাচাই করা দরকার। ইনবক্সে (বা স্প্যাম ফোল্ডারে) পাঠানো লিংকটিতে ক্লিক করুন।">
        <form method="POST" action="{{ \App\Support\Url::to('/email/verification-notification') }}">
            @csrf
            <button class="h-12 w-full rounded-full border-2 border-navy font-bold">লিংক আবার পাঠান</button>
        </form>
    </x-form-card>
</x-layouts.app>
