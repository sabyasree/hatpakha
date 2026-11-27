{{-- D317: বাংলা শিখি, the starter's map: the বর্ণমালা first, then four lessons --}}
@php($U = fn ($p) => \App\Support\Url::to($p))
<x-layouts.app :seo="$seo">
    <article class="mx-auto max-w-[760px] px-5 py-8">
        @include('janun.partials.head', ['crumbs' => [['হাতপাখা', $U('/')], ['জেনে রাখুন', $U('/janun')], ['বাংলা শিখি', '']],
            'kicker' => 'জেনে রাখুন · বিনা পয়সায়', 'title' => 'বাংলা শিখি'])
        <p lang="en" class="mt-1 text-sm text-ink2">Learn to read Bengali: a free starter for children and families abroad</p>
        <p class="mt-4 font-serif text-lg leading-relaxed">বাংলা পড়তে শেখা পাঁচ ধাপে। আগে অক্ষর চেনা, তারপর কার আর ফলা, তারপর যুক্তাক্ষর, শেষে চেনা শব্দ আর ছোট বাক্য। প্রতিটি শব্দের পাশে ইংরেজি মানে আর রোমান হরফে উচ্চারণ, যাতে বাবা-মা নিজেরা বাংলা কম পড়লেও সঙ্গে থাকতে পারেন। প্রতিটি পাঠের ওয়ার্কশিট ছেপে নেওয়া যায়।</p>
        <ol class="mt-6 grid gap-3">
            <li><a href="{{ $U('/bornomala') }}" class="flex min-h-16 items-center gap-4 rounded-2xl border-2 border-navy bg-peach-light p-4 hover:bg-peach">
                <span class="flex h-12 w-12 flex-none items-center justify-center rounded-full bg-navy font-display text-xl font-extrabold text-white">১</span>
                <span><span class="block font-display text-xl font-extrabold">অ আ ক খ: বর্ণমালা</span><span lang="en" class="block text-sm text-ink2">The alphabet, one word and picture per letter</span></span></a></li>
            @foreach (\App\Http\Controllers\LearnController::LESSONS as $i => $k)
                <li><a href="{{ $U('/janun/learn-bengali/'.$k) }}" class="flex min-h-16 items-center gap-4 rounded-2xl border-2 border-navy p-4 hover:bg-peach-light">
                    <span class="flex h-12 w-12 flex-none items-center justify-center rounded-full bg-red font-display text-xl font-extrabold text-white">{{ \App\Support\Bn::num((string) ($i + 2)) }}</span>
                    <span><span class="block font-display text-xl font-extrabold">{{ $lessons[$k]['title'] }}</span><span lang="en" class="block text-sm text-ink2">{{ $lessons[$k]['en'] }}</span></span></a></li>
            @endforeach
        </ol>
        <section class="mt-6 rounded-2xl bg-sky-tint p-4">
            <h2 class="font-display text-xl font-extrabold">বাবা-মায়েদের জন্য</h2>
            <ul class="mt-2 list-disc space-y-1 pl-5 font-serif">
                <li>রোজ দশ মিনিট, একসঙ্গে, জোরে পড়ে। একদিনে একটা কার বা দুটো যুক্তাক্ষরই যথেষ্ট।</li>
                <li>ছাপা ওয়ার্কশিটে আগে ধূসর অক্ষরের ওপর হাত বোলানো, তারপর ফাঁকা ঘরে নিজে লেখা।</li>
                <li>ছোটদের জন্য সহজ গল্প আর ছড়া পাবেন <a href="{{ $U('/category/chhotoder') }}" class="underline">ছোটদের হাতপাখা</a>-য়।</li>
            </ul>
        </section>
        <x-newsletter-signup variant="card" />
    </article>
</x-layouts.app>
