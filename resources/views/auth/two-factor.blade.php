<x-layouts.app :seo="$seo">
    <x-form-card title="দুই-ধাপের যাচাই" subtitle="আপনার অ্যাকাউন্টে বাড়তি সুরক্ষা চালু আছে। ফোনের অথেনটিকেটর অ্যাপে দেখানো ৬ অঙ্কের কোডটি দিন।">
        <form method="POST" action="{{ \App\Support\Url::to('/login/2fa') }}">
            @csrf
            <x-field name="code" label="কোড" inputmode="numeric" autocomplete="one-time-code" maxlength="40" required autofocus
                hint="ফোন হাতের কাছে না থাকলে, চালু করার সময় যে উদ্ধার-কোডগুলো রেখেছিলেন তার একটি দিন।" />
            <button class="h-12 w-full rounded-full bg-red font-bold text-white">যাচাই করুন</button>
        </form>
        <p class="mt-6 text-center text-sm text-ink2">ফোন আর উদ্ধার-কোড দুটোই হারিয়ে গেলে <a href="{{ \App\Support\Url::to('/contact-us') }}" class="underline">যোগাযোগ</a> করুন।</p>
    </x-form-card>
</x-layouts.app>
