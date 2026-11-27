{{-- D314: a year's holiday lists --}}
@php($U = fn ($p) => \App\Support\Url::to($p))
<x-layouts.app :seo="$seo">
    <article class="mx-auto max-w-[760px] px-5 py-8">
        @include('janun.partials.head', ['crumbs' => [['হাতপাখা', $U('/')], ['জেনে রাখুন', $U('/janun')], ['সরকারি ছুটি '.\App\Support\Bn::num((string) $year), '']],
            'kicker' => 'জেনে রাখুন', 'title' => 'সরকারি ছুটির তালিকা '.\App\Support\Bn::num((string) $year)])
        <p class="mt-4 font-serif text-lg leading-relaxed">সরকারি প্রজ্ঞাপন থেকে নেওয়া ছুটির তালিকা, প্রতিটি তারিখ অন্তত দুটি সূত্রে মিলিয়ে দেখা। প্রজ্ঞাপন বেরোলে তবেই তালিকা দেওয়া হয়।</p>
        <ul class="mt-5 grid gap-3 sm:grid-cols-2">
            @foreach ($lists as $l)
                <li><a href="{{ $l->url() }}" class="block rounded-2xl border-2 border-navy p-4 hover:bg-peach-light"><span class="block font-display text-xl font-extrabold">{{ $l->name() }}</span>
                    <span class="mt-1 block text-sm text-ink2">{{ \App\Support\Bn::num((string) $l->holidays()->where('status', 'verified')->count()) }}টি ছুটির দিন</span></a></li>
            @endforeach
        </ul>
        <x-ad-slot spot="list" :height="250" />
        <a href="{{ \App\Support\BanglaDate::url() }}" class="mt-4 inline-flex min-h-11 items-center font-bold text-sky-night underline">বাংলা ক্যালেন্ডার →</a>
    </article>
</x-layouts.app>
