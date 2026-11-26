<x-layouts.app :seo="$seo">
    <section class="mx-auto max-w-[1000px] px-5 py-10">
        @include('janun.partials.head', ['crumbs' => [['হাতপাখা', \App\Support\Url::to('/')], ['জেনে রাখুন', '']], 'kicker' => 'জেনে রাখুন', 'title' => 'জেনে রাখুন'])
        <p class="mt-4 max-w-[720px] font-serif text-lg leading-relaxed">প্রবাদ আর বাগধারার মানে, শুদ্ধ বানানের নিয়ম, পুজোর নিয়ম আর মন্ত্রের অর্থ, আর ছাত্রছাত্রীদের জন্য ভাবসম্প্রসারণ ও রচনা। হাতপাখা ডেস্কের লেখা, তথ্য যাচাই করে।</p>
        {{-- D303/D302: the calendar and the puja times, always useful --}}
        <div class="mt-6 grid gap-3 sm:grid-cols-2">
            <a href="{{ \App\Support\BanglaDate::url() }}" class="block rounded-2xl bg-navy p-4 text-white"><span class="block text-sm font-bold">বাংলা ক্যালেন্ডার</span><span class="mt-1 block font-display text-xl font-extrabold">{{ implode(' ', \App\Http\Controllers\BanglaCalendarController::todayLine()) }}</span></a>
            @if (\App\Support\FestivalPlan::pageLive())<a href="{{ \App\Support\Url::to('/janun/puja/pujor-somoy') }}" class="block rounded-2xl border-2 border-sky-deep bg-sky-tint p-4"><span class="block text-sm font-bold">পুজোর তিথি ও সময়</span><span class="mt-1 block font-display text-xl font-extrabold">আপনার দেশের ঘড়িতে</span></a>@endif
        </div>
        @foreach (\App\Support\JanunPlan::SECTIONS as $key => [$name, $kicker, $intro])
            @if ($live[$key]->isNotEmpty())
                <h2 class="mt-10 font-display text-2xl font-extrabold"><a href="{{ \App\Support\Url::to('/janun/'.$key) }}" class="hover:underline">{{ $name }}</a> <span class="text-base font-semibold text-ink2">· {{ $kicker }}</span></h2>
                <ul class="mt-3 grid gap-3 sm:grid-cols-2">
                    @foreach ($live[$key] as $pg)
                        <li><a href="{{ $pg->url() }}" class="block min-h-11 rounded-2xl border-2 border-navy bg-paper p-4 font-bold hover:bg-peach-light">{{ $pg->title }}</a></li>
                    @endforeach
                </ul>
                @if ($loop->first)<x-ad-slot spot="list" :height="250" />@endif
            @endif
        @endforeach
        @if (collect($live)->flatten()->count() >= 6)<x-ad-slot spot="list" :height="250" />@endif   {{-- at the end, once the hub has a few pages (owner, 2026-11-27) --}}
    </section>
</x-layouts.app>
