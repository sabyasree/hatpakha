{{-- D316: every letter page, boys and girls --}}
@php($U = fn ($p) => \App\Support\Url::to($p))
<x-layouts.app :seo="$seo">
    <article class="mx-auto max-w-[760px] px-5 py-8">
        @include('janun.partials.head', ['crumbs' => [['হাতপাখা', $U('/')], ['জেনে রাখুন', $U('/janun')], ['নাম ও অর্থ', '']],
            'kicker' => 'জেনে রাখুন', 'title' => 'বাংলা নাম ও অর্থ'])
        <p lang="en" class="mt-1 text-sm text-ink2">Bengali baby names with meanings</p>
        <p class="mt-4 font-serif text-lg leading-relaxed">ছেলে আর মেয়েদের বাংলা নাম, অক্ষর ধরে সাজানো, অর্থ আর ইংরেজি বানানসহ। প্রতিটি নামের অর্থ দুটি অভিধানে মিলিয়ে দেখা; যে নাম অভিধানে মেলেনি, তা এখানে নেই। প্রতি সপ্তাহে নতুন অক্ষর যোগ হয়।</p>
        @foreach (\App\Support\NamePlan::GENDERS as $g => [$gBn])
            <h2 class="mt-8 font-display text-2xl font-extrabold">{{ $gBn }} নাম</h2>
            <ul class="mt-3 grid grid-cols-5 gap-2 sm:grid-cols-8">
                @foreach (\App\Support\NamePlan::LETTERS as $l => $bn)
                    @if ($p = $live->get($g.'|'.$l))
                        <li><a href="{{ $p->url() }}" class="flex min-h-12 items-center justify-center rounded-xl border-2 border-navy font-display text-2xl font-extrabold hover:bg-peach-light" aria-label="{{ $bn }} দিয়ে {{ $gBn }} নাম">{{ $bn }}</a></li>
                    @endif
                @endforeach
            </ul>
            @if ($loop->first)<x-ad-slot spot="list" :height="250" />@endif
        @endforeach
        <p class="mt-6 text-sm text-ink2">অর্থ মেলানো হয় বাংলা একাডেমির বাংলা-ইংরেজি অভিধান (accessibledictionary.gov.bd) আর উইকিঅভিধানের সঙ্গে।</p>
    </article>
</x-layouts.app>
