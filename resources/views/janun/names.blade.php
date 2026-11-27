{{-- D316: every letter page, boys and girls --}}
@php($U = fn ($p) => \App\Support\Url::to($p))
<x-layouts.app :seo="$seo">
    <article class="mx-auto max-w-[760px] px-5 py-8">
        @include('janun.partials.head', ['crumbs' => [['হাতপাখা', $U('/')], ['জেনে রাখুন', $U('/janun')], ['নাম ও অর্থ', '']],
            'kicker' => 'জেনে রাখুন', 'title' => 'বাংলা নাম ও অর্থ'])
        <p lang="en" class="mt-1 text-sm text-ink2">Bengali baby names with meanings</p>
        <p class="mt-4 font-serif text-lg leading-relaxed">ছেলে আর মেয়েদের বাংলা নাম, অক্ষর ধরে সাজানো, অর্থ আর ইংরেজি বানানসহ। প্রতিটি নামের অর্থ দুটি অভিধানে মিলিয়ে দেখা; যে নাম অভিধানে মেলেনি, তা এখানে নেই। প্রতি সপ্তাহে নতুন অক্ষর যোগ হয়।</p>
        @foreach (\App\Support\NamePlan::GENDERS as $g => [$gBn])
            @continue($live->keys()->doesntContain(fn ($k) => str_starts_with($k, $g.'|')))
            <h2 class="mt-8 font-display text-2xl font-extrabold">{{ $gBn }} নাম</h2>
            <ul class="mt-3 grid grid-cols-5 gap-2 sm:grid-cols-8">
                @foreach (\App\Support\NamePlan::LETTERS as $l => $bn)
                    @if ($p = $live->get($g.'|'.$l))
                        <li><a href="{{ $p->url() }}" class="flex min-h-12 items-center justify-center rounded-xl border-2 border-navy font-display text-2xl font-extrabold hover:bg-peach-light" title="{{ $bn }} দিয়ে {{ $gBn }} নাম">{{ $bn }}<span class="sr-only"> দিয়ে {{ $gBn }} নাম</span><span lang="en" class="ml-1 text-xs font-bold text-ink2">{{ \App\Support\NamePlan::EN_LETTER[$l] }}</span></a></li>
                    @endif
                @endforeach
            </ul>
            @if ($loop->first)<x-ad-slot spot="list" :height="250" />@endif
        @endforeach
        <section lang="en" class="mt-8">
            <h2 class="font-display text-2xl font-extrabold">Bengali baby names A–Z</h2>
            <p class="text-base text-ink2">By the English first letter (one English letter can be two Bengali letters, e.g. A = অ and আ).</p>
            <dl class="mt-3 grid gap-2 sm:grid-cols-2">
                @foreach (collect(\App\Support\NamePlan::EN_LETTER)->groupBy(fn ($v) => strtoupper(substr($v, 0, 1)), true)->sortKeys() as $first => $group)
                    @php($pages = collect(array_keys($group->all()))->flatMap(fn ($l) => collect(['boy', 'girl'])->map(fn ($g) => $live->get($g.'|'.$l)))->filter())
                    @continue($pages->isEmpty())
                    <div class="flex flex-wrap items-center gap-2 rounded-xl border border-rule p-2"><dt class="w-8 font-display text-xl font-extrabold">{{ $first }}</dt>
                        <dd class="flex flex-wrap gap-2">@foreach ($pages as $pg)<a href="{{ $pg->url() }}" class="flex min-h-11 items-center rounded-full border-2 border-navy px-3 text-sm font-bold">{{ $pg->gender === 'boy' ? 'Boys' : 'Girls' }} · <span lang="bn" class="ml-1">{{ \App\Support\NamePlan::LETTERS[$pg->letter] }}</span></a>@endforeach</dd></div>
                @endforeach
            </dl>
        </section>
        <p class="mt-6 text-sm text-ink2">অর্থ মেলানো হয় বাংলা একাডেমির বাংলা-ইংরেজি অভিধান (accessibledictionary.gov.bd) আর উইকিঅভিধানের সঙ্গে।</p>
    </article>
</x-layouts.app>
