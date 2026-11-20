<x-layouts.app :seo="$seo">
    <x-form-card :title="__('ইনবক্স দেখুন')" :subtitle="__('আর একটু বাকি।')">
        {{-- D249 (a writer asked: "am I a member now? can I send writing? I gave my e-mail; is anything else needed?") --}}
        <div class="mb-4 rounded-2xl border-2 border-navy bg-white p-5 text-lg">
            <p class="font-bold">{{ __('আপনার অ্যাকাউন্ট খোলা হয়েছে।') }}</p>
            <p class="mt-1">{{ __('শুধু একটা কাজ বাকি: নিজের ই-মেল খুলে হাতপাখার চিঠির বোতামটা একবার টিপুন। তারপরই লেখা পাঠাতে পারবেন। আর কিছু লাগবে না।') }}</p>
        </div>
        @if ($email)<p class="mb-4 text-lg">{{ __('চিঠি গেছে এই ঠিকানায়:') }} <strong>{{ $email }}</strong></p>@endif
        <ul class="mb-2 list-disc space-y-2 pl-5 text-lg">
            <li>{{ __('ফোনে ই-মেল খুলুন (Gmail হলে লাল খামের ছবি দেওয়া অ্যাপ)। “হাতপাখা” থেকে আসা চিঠিটা খুলে বোতামটা টিপুন।') }}</li>
            <li>{{ __('চিঠি না দেখলে কয়েক মিনিট অপেক্ষা করুন, তারপর “স্প্যাম” খুলে দেখুন।') }}</li>
            <li>{{ __('অনেক দেরি হয়ে গেলে বোতাম আর কাজ না-ও করতে পারে। তখন নিচের বোতাম টিপে ই-মেল আর পাসওয়ার্ড দিয়ে ঢুকুন; নতুন চিঠি চাওয়ার বোতাম পাবেন।') }}</li>
        </ul>
        <a href="{{ \App\Support\Url::to('/login') }}" class="mt-6 flex h-12 w-full items-center justify-center rounded-full border-2 border-navy font-bold">{{ __('সাইন ইন পাতায় যান') }}</a>
    </x-form-card>
</x-layouts.app>
