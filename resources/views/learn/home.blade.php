{{-- D321: বাংলা শিখি, the course home (for parents and older learners; ads allowed, owner 2026-11-27) --}}
@php
    $U = fn ($p) => \App\Support\Url::to($p);
    $N = fn ($n) => \App\Support\Bn::num((string) $n);
    $C = \App\Support\LearnCourse::class;
@endphp
<x-layouts.app :seo="$seo">
    <article class="mx-auto max-w-[860px] px-5 py-8">
        @include('janun.partials.head', ['crumbs' => [['হাতপাখা', $U('/')], ['জেনে রাখুন', $U('/janun')], ['বাংলা শিখি', '']], 'kicker' => 'জেনে রাখুন · বিনা পয়সায়', 'title' => 'বাংলা শিখি'])
        <p lang="en" class="mt-1 text-base text-ink2">Learn to read Bengali: a free course for children, from the first letter to the first story</p>
        <p class="mt-4 font-serif text-xl leading-relaxed">অক্ষর থেকে প্রথম গল্প। ছোট ছোট পাঠ, ফোনে খেলার মতো: অক্ষর চেনা, আঙুলে লেখা, শব্দ সাজানো, জোরে পড়া। প্রতিটি শব্দের পাশে রোমান হরফে উচ্চারণ আর ইংরেজি মানে, যাতে বাংলা কম পড়েন এমন বাবা-মাও সঙ্গে থাকতে পারেন।</p>
        <div class="mt-6 grid gap-3 sm:grid-cols-2">
            <a href="{{ $C::url('start') }}" class="flex min-h-20 flex-col justify-center rounded-2xl bg-red px-5 py-4 text-white shadow-[4px_4px_0_#13212C]"><span class="font-display text-2xl font-extrabold">শিশুর পাঠ শুরু করুন →</span><span class="text-base">ফোনে বা ট্যাবে, রোজ দশ মিনিট</span></a>
            <a href="{{ $C::url('parents') }}" class="flex min-h-20 flex-col justify-center rounded-2xl border-2 border-navy bg-paper px-5 py-4"><span class="font-display text-2xl font-extrabold">বাবা-মায়ের জন্য</span><span class="text-base">কীভাবে সাহায্য করবেন, অগ্রগতি</span></a>
        </div>
        <a href="{{ $C::url('english') }}" lang="en" class="mt-3 flex min-h-16 items-center justify-between gap-3 rounded-2xl border-2 border-sky-deep bg-sky-tint px-5 py-3"><span><span class="block font-display text-xl font-extrabold">Don't read Bengali yourself? Start here, in English</span><span class="block text-base">How the script works, how each letter sounds, and the course with English instructions</span></span><span aria-hidden="true">→</span></a>
        <ul class="mt-6 grid gap-2 text-lg sm:grid-cols-3">
            <li class="rounded-2xl bg-sky-tint p-3"><b>{{ $N($C::count()) }}টি পাঠ</b>, ৫–৮ মিনিটের</li>
            <li class="rounded-2xl bg-sky-tint p-3"><b>প্রতিটি পাঠের ওয়ার্কশিট</b>, A4 ও Letter</li>
            <li class="rounded-2xl bg-sky-tint p-3"><b>দুই বাংলার শব্দ</b>, সব পরিবারের জন্য</li>
        </ul>
        <x-ad-slot spot="list" :height="250" />
        <h2 class="mt-8 font-display text-3xl font-extrabold">ধাপে ধাপে পড়া</h2>
        <ol class="mt-4 space-y-3">
            <li><a href="{{ $U('/bornomala') }}" class="flex min-h-16 items-center gap-4 rounded-2xl border-2 border-navy p-4 hover:bg-peach-light"><span class="text-4xl" aria-hidden="true">🔤</span><span><span class="block font-display text-xl font-extrabold">অ আ ক খ: বর্ণমালা</span><span class="block text-base text-ink2">প্রতিটি অক্ষর ছবি আর শব্দে</span></span></a></li>
            @foreach ($units as $slug => $u)
                <li><a href="{{ $C::url($slug) }}" class="flex min-h-16 items-center gap-4 rounded-2xl border-2 border-navy p-4 hover:bg-peach-light">
                    <span class="flex h-12 w-12 flex-none items-center justify-center rounded-full bg-peach text-2xl" aria-hidden="true">{{ $u['sticker'][0] }}</span>
                    <span class="min-w-0"><span class="block font-display text-xl font-extrabold">{{ $N($u['n']) }}. {{ $u['title'] }}</span><span lang="en" class="block text-base text-ink2">{{ $u['en'] }} · {{ count($u['lessons']) }} lessons</span></span></a></li>
                @if ($u['n'] === 5)<x-ad-slot spot="list" :height="250" />@endif
            @endforeach
        </ol>
        <h2 class="mt-10 font-display text-2xl font-extrabold">খুঁজে দেখার চার্ট</h2>
        <div class="mt-3 flex flex-wrap gap-2">
            @foreach ($refs as $k => $r)<a href="{{ $U('/janun/learn-bengali/'.$k) }}" class="flex min-h-11 items-center rounded-full border-2 border-navy px-4 font-bold hover:bg-peach-light">{{ $r['title'] }}</a>@endforeach
            <a href="{{ $C::url('worksheets') }}" class="flex min-h-11 items-center rounded-full bg-navy px-4 font-bold text-white">সব ওয়ার্কশিট (PDF)</a>
        </div>
        <section class="mt-10 rounded-2xl border-2 border-navy p-5">
            <h2 class="font-display text-2xl font-extrabold">যা জানা দরকার</h2>
            <dl class="mt-3 space-y-3 font-serif text-lg">
                <div><dt class="font-bold">কত বয়স থেকে?</dt><dd>চার-পাঁচ বছর থেকে, বাবা-মায়ের সঙ্গে। বড়রাও নিজে নিজে করতে পারেন।</dd></div>
                <div><dt class="font-bold">অ্যাকাউন্ট লাগবে?</dt><dd>না। তারা আর স্টিকার ফোনেই থাকে, কোথাও পাঠানো হয় না। অন্য ফোনে নিতে চাইলে <a href="{{ $C::url('parents') }}" class="underline">বাবা-মায়ের পাতায়</a> একটা কোড আছে।</dd></div>
                <div><dt class="font-bold">বিজ্ঞাপন?</dt><dd>শিশুর পাঠ, ম্যাপ আর ওয়ার্কশিটে কোনো বিজ্ঞাপন নেই। বড়দের পাতায় (এই পাতার মতো) বিজ্ঞাপন থাকে, তাতেই এই পাঠ বিনা পয়সায় চলে।</dd></div>
                <div><dt class="font-bold">আওয়াজ?</dt><dd>এখন পাঠ চলে আওয়াজ ছাড়াই, বাবা-মা পড়ে শোনান। মানুষের গলায় রেকর্ডিং ধীরে ধীরে যোগ হচ্ছে।</dd></div>
            </dl>
        </section>
        <x-newsletter-signup variant="card" />
    </article>
</x-layouts.app>
