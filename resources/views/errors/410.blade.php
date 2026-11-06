@php
    $seo = new \App\Seo\Seo(title: '410 · এই পাতাটি আর নেই।', noindex: true, canonical: null);
    $suggest = \App\Models\Post::published()->with('authors', 'section')->where('language', 'bn')
        ->whereHas('section', fn ($q) => $q->whereIn('slug', ['poetry', 'short-stories']))->latest('published_at')->limit(4)->get();
@endphp
<x-layouts.app :seo="$seo">
    <section class="mx-auto max-w-[760px] px-5 pb-10 pt-14 text-center">
        <div class="font-display text-7xl font-extrabold text-red md:text-8xl">410</div>
        <h1 class="mt-3 font-display text-3xl font-extrabold">এই পাতাটি আর নেই।</h1>
        <p class="mt-3 font-serif text-lg text-ink2">লেখাটি সরিয়ে নেওয়া হয়েছে। নিচের লেখাগুলো পড়ে দেখতে পারেন।</p>
        <div class="mt-7 flex flex-wrap justify-center gap-3">
            <a href="{{ url('/') }}/" class="inline-flex h-12 items-center rounded-full bg-navy px-6 font-bold text-paper">প্রথম পাতায় ফিরুন</a>
            <a href="{{ \App\Support\Url::to('/category/poetry') }}" class="inline-flex h-12 items-center rounded-full border-2 border-navy px-6 font-bold">কবিতা</a>
            <a href="{{ \App\Support\Url::to('/category/short-stories') }}" class="inline-flex h-12 items-center rounded-full border-2 border-navy px-6 font-bold">গল্প</a>
        </div>
    </section>
    @if ($suggest->isNotEmpty())
        <section class="mx-auto max-w-[1100px] px-5 pb-6">
            <h2 class="mb-4 font-display text-2xl font-extrabold">সাম্প্রতিক কবিতা ও গল্প</h2>
            <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                @foreach ($suggest as $p)
                    <a href="{{ $p->url() }}" class="block"><x-title-card :post="$p" />@if ($p->hasCardImage())<span class="mt-2 block font-serif font-bold leading-snug">{{ $p->title }}</span>@endif<span class="mt-1 block text-sm font-semibold text-ink2">{{ $p->authors->first()?->publicName() }}</span></a>
                @endforeach
            </div>
        </section>
    @endif
</x-layouts.app>
