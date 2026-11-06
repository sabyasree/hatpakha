<x-layouts.app :seo="$seo">
    <x-form-card title="ই-মেল দেখুন" subtitle="আর এক ধাপ বাকি।">
        <div class="rounded-2xl border-2 border-navy bg-sky-tint p-5">
            <p class="font-serif text-lg">@if ($email)<strong>{{ $email }}</strong> ঠিকানায়@else আপনার ই-মেলে@endif একটি লিংক পাঠানো হয়েছে। লিংকে চাপলেই অ্যাকাউন্ট চালু হবে, আর আপনি লগ-ইন অবস্থায় থাকবেন।</p>
        </div>
        <ul class="mt-5 list-disc space-y-1 pl-5 text-ink2">
            <li>কয়েক মিনিটে না এলে স্প্যাম বা "Promotions" ফোল্ডার দেখুন।</li>
            <li>লিংক এক ঘণ্টা কাজ করে। পেরিয়ে গেলে লগ-ইন করে নতুন লিংক চেয়ে নিন।</li>
            <li>এই ঠিকানায় আগেই অ্যাকাউন্ট থাকলে লগ-ইন বা পাসওয়ার্ড বদলের নির্দেশ পাবেন।</li>
        </ul>
        <a href="{{ \App\Support\Url::to('/login') }}" class="mt-6 flex h-12 w-full items-center justify-center rounded-full border-2 border-navy font-bold">লগ-ইন পাতায় যান</a>
    </x-form-card>
</x-layouts.app>
