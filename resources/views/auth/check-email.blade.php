<x-layouts.app :seo="$seo">
    <x-form-card :title="__('ইনবক্স দেখুন')" :subtitle="__('আর একটু বাকি।')">
        <div class="rounded-2xl border-2 border-navy bg-sky-tint p-5">
            <p class="font-serif text-lg">@if ($email)<strong>{{ $email }}</strong>: {{ __('এই ঠিকানায় একটি লিংক পাঠানো হয়েছে।') }}@else {{ __('আপনার ই-মেলে একটি লিংক পাঠানো হয়েছে।') }}@endif {{ __('লিংকে ক্লিক করলেই অ্যাকাউন্ট চালু হবে, আর আপনি সাইন ইন হয়ে যাবেন।') }}</p>
        </div>
        <ul class="mt-5 list-disc space-y-1 pl-5 text-ink2">
            <li>{{ __('কয়েক মিনিটে না এলে স্প্যাম বা "Promotions" ফোল্ডার দেখুন।') }}</li>
            <li>{{ __('লিংক এক ঘণ্টা কাজ করে। সময় পেরিয়ে গেলে সাইন ইন করে নতুন লিংক চেয়ে নিন।') }}</li>
            <li>{{ __('এই ই-মেলে আগেই অ্যাকাউন্ট থাকলে সাইন ইন বা পাসওয়ার্ড বদলানোর নিয়ম ই-মেলে পাবেন।') }}</li>
        </ul>
        <a href="{{ \App\Support\Url::to('/login') }}" class="mt-6 flex h-12 w-full items-center justify-center rounded-full border-2 border-navy font-bold">{{ __('সাইন ইন পাতায় যান') }}</a>
    </x-form-card>
</x-layouts.app>
