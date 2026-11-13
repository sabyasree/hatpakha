{{-- D188: the writer's answer to the invitation for the ফিরে আসা সংখ্যা. Nothing here publishes anything. --}}
<x-layouts.app :seo="$seo">
    <section class="mx-auto max-w-[720px] px-5 py-12">
        <div class="font-hand text-2xl text-red">হাতপাখা</div>
        <h1 class="font-display text-4xl font-extrabold leading-tight">ফিরে আসা সংখ্যা</h1>
        <p class="mt-4 font-serif text-lg leading-relaxed text-ink2">নমস্কার, {{ $invite->author?->publicName() }}। চিঠির উত্তর দিতে এসেছেন, ভালো লাগল।</p>

        @if ($done)
            <div class="mt-6 rounded-2xl border-2 border-navy bg-sky-tint p-5" role="status">
                @if ($done === 'yes')
                    <p class="font-bold">ধন্যবাদ। আপনার লেখার অপেক্ষায় থাকলাম।</p>
                    <p class="mt-1">বিষয় “ফিরে আসা”: কোনো জায়গায়, কোনো মানুষের কাছে, কোনো অভ্যাসে, বা লেখাতেই ফিরে আসা। কবিতা, গল্প, স্মৃতিকথা, যা আপনার মন চায়। শেষ দিন {{ $last }}।</p>
                    <a href="{{ \App\Support\Url::to('/submit') }}" class="mt-4 inline-flex h-14 items-center rounded-full bg-red px-7 text-lg font-bold text-white shadow-[3px_3px_0_#13212C]">লেখা পাঠান</a>
                @elseif ($done === 'later')
                    <p class="font-bold">ঠিক আছে, এবার থাক।</p>
                    <p class="mt-1">যখন লেখা হবে, পাঠিয়ে দেবেন। হাতপাখার দরজা খোলা রইল।</p>
                @else
                    <p class="font-bold">বুঝেছি। এ নিয়ে আর চিঠি যাবে না।</p>
                    <p class="mt-1">কখনও লিখতে ইচ্ছে হলে হাতপাখা আছে।</p>
                @endif
            </div>
        @else
            <form method="post" action="{{ $action }}" class="mt-6 rounded-2xl border-2 border-navy bg-white p-5">
                @csrf
                <p class="text-lg font-bold">
                    {{ ['yes' => 'ফিরে আসা সংখ্যার জন্য একটা লেখা পাঠাবেন?', 'later' => 'এবার লেখা হচ্ছে না, পরে পাঠাবেন?', 'no' => 'এ নিয়ে আর চিঠি চান না?'][$answer] }}
                </p>
                <button class="mt-4 h-14 rounded-full bg-red px-7 text-lg font-bold text-white shadow-[3px_3px_0_#13212C]">
                    {{ ['yes' => 'হ্যাঁ, লিখব', 'later' => 'হ্যাঁ, পরে লিখব', 'no' => 'হ্যাঁ, আর চিঠি নয়'][$answer] }}
                </button>
            </form>
        @endif
    </section>
</x-layouts.app>
