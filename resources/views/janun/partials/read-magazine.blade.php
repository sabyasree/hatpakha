{{-- D326 (strategy review): reference pages lead into the magazine itself: three recent pieces and the newsletter.
     The list is cached as ids (Laravel 13 will not unserialize models from the cache, D18). --}}
@php
    $ids = \Illuminate\Support\Facades\Cache::remember('janun.read-magazine', 1800, fn () => \App\Models\Post::published()
        ->whereHas('section', fn ($q) => $q->where('slug', '!=', \App\Models\Section::KIDS))->latest('published_at')->limit(3)->pluck('id')->all());
    $pieces = \App\Models\Post::with('section')->whereIn('id', $ids)->get()->sortBy(fn ($p) => array_search($p->id, $ids))->values();
@endphp
@if ($pieces->isNotEmpty())
    <section class="mt-10 rounded-2xl border-2 border-navy bg-peach-light p-4">
        <h2 class="font-display text-xl font-extrabold">হাতপাখায় এখন পড়ুন</h2>
        <p class="text-base text-ink2">২০১২ থেকে বাংলা সাহিত্য পত্রিকা: গল্প, কবিতা, প্রবন্ধ, ভ্রমণ</p>
        <ul class="mt-3 grid gap-2 sm:grid-cols-3">
            @foreach ($pieces as $p)
                <li><a href="{{ $p->url() }}" class="block min-h-11 rounded-xl border-2 border-navy bg-paper p-3 hover:bg-white"><span class="block text-sm font-bold text-red">{{ $p->section?->name_bn }}</span><span class="block font-display text-lg font-extrabold leading-snug">{{ $p->title }}</span></a></li>
            @endforeach
        </ul>
    </section>
@endif
<x-newsletter-signup variant="card" />
