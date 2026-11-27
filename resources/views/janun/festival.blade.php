{{-- D308: one festival's year, from checked times only; every sentence is built from the data (no AI text) --}}
@php
    $U = fn ($p) => \App\Support\Url::to($p);
    $B = \App\Support\BanglaDate::class;
    $F = \App\Support\FestivalPlan::class;
    $N = fn ($n) => \App\Support\Bn::num((string) $n);
    $timed = $rows->filter(fn ($t) => $t->starts_at)->values();
    $first = $timed->first();
@endphp
<x-layouts.app :seo="$seo">
    <article class="mx-auto max-w-[760px] px-5 py-8">
        @include('janun.partials.head', ['crumbs' => [['হাতপাখা', $U('/')], ['জেনে রাখুন', $U('/janun')], ['পুজো-পার্বণ', $U('/janun/puja')], ['পুজো-পার্বণ '.$N($year), $U('/janun/puja/'.$year)], [$nameY, '']],
            'kicker' => 'পুজো-পার্বণ '.$N($year), 'title' => $nameY.': তারিখ, তিথি ও সময়', 'trust' => 'শেষ যাচাই: '.\App\Support\Bn::date(\Carbon\Carbon::parse($rows->max('updated_at')))])
        <p lang="en" class="mt-1 text-sm text-ink2">{{ $en }} {{ $year }}: date and tithi time</p>
        @if ($day)
            <p class="mt-4 font-serif text-lg leading-relaxed">
                {{ $N($year) }} সালে {{ $name }} <b>{{ $F::show($day, false) }}</b>@if ($wb) (পশ্চিমবঙ্গে <b>{{ $B::text($wb) }}</b>@if ($bd && $bd !== $wb), বাংলাদেশে {{ $B::text($bd) }}@endif)@endif।
                @if ($first) {{ $first->label }} শুরু ভারতের সময়ে {{ $F::show($first->starts_at) }}, শেষ {{ $F::show($first->ends_at) }}।@endif
            </p>
        @endif

        @include('janun.partials.times', ['festivals' => $festivals, 'heading' => 'আপনার দেশের সময়ে', 'links' => false])

        @foreach ($timed as $t)
            <h2 class="mt-8 font-display text-xl font-extrabold">{{ $t->label }}: শহর ধরে সময়</h2>
            <div class="mt-2 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead><tr class="border-b-2 border-navy"><th class="py-2 pr-2">শহর</th><th class="py-2 pr-2">শুরু</th><th class="py-2">শেষ</th></tr></thead>
                    <tbody>
                        @foreach ($F::CITIES as $zone => $city)
                            <tr class="border-b border-rule"><th scope="row" class="py-2 pr-2 font-bold">{{ $city }}</th><td class="py-2 pr-2">{{ $F::show($t->starts_at, true, $zone) }}</td><td class="py-2">{{ $F::show($t->ends_at, true, $zone) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($loop->first)<x-ad-slot spot="list" :height="250" />@endif
        @endforeach

        @if ($guide || $printable)
            <div class="mt-8 grid gap-3 sm:grid-cols-2">
                @if ($guide)<a href="{{ $guide->url() }}" class="flex min-h-12 items-center rounded-2xl border-2 border-navy px-4 font-bold hover:bg-peach-light">{{ $name }}: নিয়ম ও মন্ত্র (অর্থসহ) →</a>@endif
                @if ($printable)<a href="{{ $U('/janun/puja/'.$guide->slug.'/chhapun') }}" class="flex min-h-12 items-center rounded-2xl border-2 border-navy px-4 font-bold hover:bg-peach-light">ছাপার পুস্তিকা (বিনামূল্যে) →</a>@endif
            </div>
        @endif

        <h2 class="mt-10 font-display text-2xl font-extrabold">প্রশ্নোত্তর</h2>
        @if ($day)
            <details class="mt-2 rounded-xl border-2 border-rule p-3"><summary class="flex min-h-11 cursor-pointer items-center font-bold">{{ $nameY }} কবে?</summary>
                <p class="mt-2 font-serif leading-relaxed">{{ $F::show($day, false) }}।@if ($wb) পশ্চিমবঙ্গের পঞ্জিকায় {{ $B::text($wb) }}@if ($bd), বাংলাদেশের ক্যালেন্ডারে {{ $B::text($bd) }}@endif বঙ্গাব্দ।@endif</p></details>
        @endif
        @if ($first)
            <details class="mt-2 rounded-xl border-2 border-rule p-3"><summary class="flex min-h-11 cursor-pointer items-center font-bold">{{ $first->label }} কখন শুরু, কখন শেষ?</summary>
                <p class="mt-2 font-serif leading-relaxed">ভারতের সময়ে শুরু {{ $F::show($first->starts_at) }}, শেষ {{ $F::show($first->ends_at) }}। বাংলাদেশের সময়ে শুরু {{ $F::show($first->starts_at, true, 'Asia/Dhaka') }}।</p></details>
            <details class="mt-2 rounded-xl border-2 border-rule p-3"><summary class="flex min-h-11 cursor-pointer items-center font-bold">আমেরিকা, ব্রিটেন বা অস্ট্রেলিয়ায় কবে?</summary>
                <p class="mt-2 font-serif leading-relaxed">তিথি সারা পৃথিবীতে একই মুহূর্তে শুরু হয়। নিউ ইয়র্কে শুরু {{ $F::show($first->starts_at, true, 'America/New_York') }}; লন্ডনে {{ $F::show($first->starts_at, true, 'Europe/London') }}; সিডনিতে {{ $F::show($first->starts_at, true, 'Australia/Sydney') }}। বিদেশের অনেক পুজো কমিটি সপ্তাহান্তে পুজো করেন; নিজের এলাকার সময় তাঁদের কাছে জেনে নিন।</p></details>
        @endif
        <p class="mt-4 text-sm text-ink2">সময়: বিশুদ্ধ সিদ্ধান্ত পঞ্জিকা (কলকাতা), প্রকাশিত সূত্রে মিলিয়ে।</p>

        @if ($years->count() > 1)
            <h2 class="mt-8 font-display text-lg font-extrabold">অন্য বছরের {{ $name }}</h2>
            <div class="mt-2 flex flex-wrap gap-2">@foreach ($years as $y)@if ($y !== $year)<a href="{{ $F::pageUrl($key, $y) }}" class="flex min-h-11 items-center rounded-full border-2 border-navy px-4 font-bold">{{ $name }} {{ $N($y) }}</a>@endif @endforeach</div>
        @endif
        @if ($others->isNotEmpty())
            <h2 class="mt-8 font-display text-lg font-extrabold">{{ $N($year) }} সালের অন্য পুজো-পার্বণ</h2>
            <ul class="mt-2 grid gap-2 sm:grid-cols-2">@foreach ($others as [$k, $y])<li><a href="{{ $F::pageUrl($k, $y) }}" class="flex min-h-11 items-center rounded-xl border-2 border-navy px-4 font-bold hover:bg-peach-light">{{ $F::FESTIVALS[$k][0] }} {{ $N($y) }}</a></li>@endforeach</ul>
        @endif
        <x-ad-slot spot="list" :height="250" />
        <div class="mt-4 flex flex-wrap gap-x-4">
            @foreach (\App\Http\Controllers\GreetingController::live()->filter(fn ($g) => (\App\Support\GreetingPlan::OCCASIONS[$g->slug][2] ?? null) === $key) as $g)<a href="{{ $g->url() }}" class="inline-flex min-h-11 items-center font-bold text-sky-night underline">{{ $g->name() }} বার্তা →</a>@endforeach   {{-- D315 --}}
            <a href="{{ $U('/janun/puja/'.$year) }}" class="inline-flex min-h-11 items-center font-bold text-sky-night underline">পুজো-পার্বণ {{ $N($year) }}: সব তারিখ →</a>
            <a href="{{ \App\Support\BanglaDate::url() }}" class="inline-flex min-h-11 items-center font-bold text-sky-night underline">বাংলা ক্যালেন্ডার →</a>
        </div>
    </article>
</x-layouts.app>
