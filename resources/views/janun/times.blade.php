{{-- D302: every coming festival's checked tithi times, in the reader's own time zone --}}
@php($U = fn ($p) => \App\Support\Url::to($p))
<x-layouts.app :seo="$seo">
    <article class="mx-auto max-w-[760px] px-5 py-8">
        @include('janun.partials.head', ['crumbs' => [['হাতপাখা', $U('/')], ['জেনে রাখুন', $U('/janun')], ['পুজো-পার্বণ', $U('/janun/puja')], ['পুজোর তিথি ও সময়', '']],
            'kicker' => 'জেনে রাখুন · পুজো-পার্বণ', 'title' => 'পুজোর তিথি ও সময়, আপনার দেশের ঘড়িতে', 'trust' => 'শেষ যাচাই: '.\App\Support\Bn::date(\App\Models\FestivalTime::where('status', 'verified')->max('updated_at') ? \Carbon\Carbon::parse(\App\Models\FestivalTime::where('status', 'verified')->max('updated_at')) : now())])
        <p class="mt-5 font-serif text-lg leading-relaxed">কলকাতা, ঢাকা, লন্ডন, নিউ ইয়র্ক, টোকিও বা সিডনি: যেখানেই থাকুন, নিচে দেশ বেছে নিলে প্রতিটি তিথির শুরু আর শেষ আপনার নিজের ঘড়িতে দেখা যাবে। আপনার ফোন বা কম্পিউটার যে দেশের সময়ে চলে, সেটি নিজে থেকেই বেছে নেওয়া থাকে।</p>
        @include('janun.partials.times', ['festivals' => $festivals, 'heading' => 'সামনের পুজো-পার্বণ'])
        <x-ad-slot spot="list" :height="250" />
        <h2 class="mt-8 font-display text-2xl font-extrabold">বিদেশে পুজোর দিন ঠিক করবেন কীভাবে?</h2>
        <p class="mt-3 font-serif text-lg leading-relaxed">তিথি চাঁদ আর সূর্যের অবস্থান ধরে একই মুহূর্তে সারা পৃথিবীতে শুরু আর শেষ হয়। তাই আমেরিকায় একটি তিথি অনেক সময় আগের দিনের তারিখে পড়ে, জাপান বা অস্ট্রেলিয়ায় পরের দিনে। যে দিনের বেশির ভাগ সময় জুড়ে তিথি থাকে, বা যে দিন সকালে তিথি থাকে, সাধারণত সেই দিনই পুজো হয়; তবে নিয়ম পুজো অনুযায়ী আলাদা, আর অনেক পুজো কমিটি সপ্তাহান্তে পুজো রাখেন। নিজের এলাকার পুরোহিত বা পুজো কমিটির কাছে জেনে নেওয়াই ভালো।</p>
        <p class="mt-3 font-serif text-lg leading-relaxed">বাংলাদেশে পয়লা বৈশাখ প্রতি বছর একই দিনে (১৪ এপ্রিল) পালিত হয়; পশ্চিমবঙ্গে পঞ্জিকা অনুযায়ী দিনটি এক দিন আগে-পরে হতে পারে।</p>
        <a href="{{ $U('/janun/puja') }}" class="mt-6 inline-flex min-h-11 items-center font-bold text-sky-night underline">পুজোর নিয়ম ও মন্ত্র: সব পাতা →</a>
    </article>
</x-layouts.app>
