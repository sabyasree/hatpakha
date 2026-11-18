{{-- D232: the readers' choice of each month, and the pieces readers answered most, one list a feeling. --}}
<x-layouts.app :seo="$seo">
    <section class="mx-auto max-w-[1100px] px-5 pb-16 pt-10">
        <div class="font-hand text-2xl text-red">পড়ে কেমন লাগল?</div>
        <h1 class="font-display text-5xl font-extrabold md:text-6xl" style="font-stretch: 80%">পাঠকের পছন্দ</h1>
        <p class="mt-3 max-w-[60ch] font-serif text-lg text-ink2">প্রতিটি লেখার নিচে পাঠকেরা জানান, লেখাটা পড়ে তাঁদের কেমন লাগল। সেই উত্তর থেকেই এই পাতা তৈরি হয়। আপনিও পড়ে জানান।</p>

        @if ($winners->isEmpty() && $lists->isEmpty())
            <p class="mt-8 font-serif text-lg">এখনও কেউ জানাননি। যে কোনো লেখা পড়ে নিচে একটি উত্তর বেছে নিন, এই পাতা ভরতে শুরু করবে।</p>
        @endif

        @if ($winners->isNotEmpty())
            <h2 class="mt-10 font-display text-3xl font-extrabold">মাসের পাঠকের পছন্দ</h2>
            <ul class="mt-3 divide-y divide-rule border-y-2 border-navy">
                @foreach ($winners as $p)
                    <li><x-post-row :post="$p" :meta="($p->authors->first()?->publicName() ?? '').' · '.\App\Support\Feelings::monthLabel($p->readers_choice)" /></li>
                @endforeach
            </ul>
        @endif

        @if ($lists->isNotEmpty())
            <nav aria-label="অনুভব" class="mt-10 flex flex-wrap gap-2">
                @foreach ($lists as $key => $posts)
                    <a href="#{{ $key }}" class="flex min-h-11 items-center rounded-full border-2 border-navy bg-white px-4 font-bold hover:bg-peach-light">{{ \App\Support\Feelings::LIST[$key][0] }}</a>
                @endforeach
            </nav>
            @foreach ($lists as $key => $posts)
                <h2 id="{{ $key }}" class="mt-10 scroll-mt-24 font-display text-3xl font-extrabold">{{ \App\Support\Feelings::LIST[$key][2] }}</h2>
                <ul class="mt-3 divide-y divide-rule border-y-2 border-navy">
                    @foreach ($posts as $p)
                        <li><x-post-row :post="$p" :meta="($p->authors->first()?->publicName() ?? '').' · '.str_replace(':n', \App\Support\Bn::num((string) $p->feeling_n), \App\Support\Feelings::LIST[$key][1])" /></li>
                    @endforeach
                </ul>
            @endforeach
        @endif
    </section>
</x-layouts.app>
