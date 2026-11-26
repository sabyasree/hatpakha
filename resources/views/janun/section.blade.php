<x-layouts.app :seo="$seo">
    <section class="mx-auto max-w-[1000px] px-5 py-10">
        @include('janun.partials.head', ['crumbs' => [['হাতপাখা', \App\Support\Url::to('/')], ['জেনে রাখুন', \App\Support\Url::to('/janun')], [$name, '']], 'kicker' => 'জেনে রাখুন', 'title' => $name])
        <p class="mt-4 max-w-[720px] font-serif text-lg leading-relaxed">{{ $intro }}</p>
        <ul class="mt-6 grid gap-3 sm:grid-cols-2">
            @foreach ($pages as $pg)
                <li><a href="{{ $pg->url() }}" class="block rounded-2xl border-2 border-navy bg-paper p-4 hover:bg-peach-light">
                    <span class="block font-display text-lg font-extrabold">{{ $pg->title }}</span>
                    @if ($pg->kind === 'list')<span class="mt-1 block text-sm text-ink2">{{ \App\Support\Bn::num((string) count((array) ($pg->body['entries'] ?? []))) }}টি, অর্থ আর উদাহরণসহ</span>@endif
                </a></li>
                @if ($loop->iteration === 6 && $loop->remaining > 2)<li class="sm:col-span-2"><x-ad-slot spot="list" :height="250" class="!my-2" /></li>@endif
            @endforeach
        </ul>
        @php($others = collect(\App\Support\JanunPlan::SECTIONS)->keys()->reject(fn ($s) => $s === $section)->filter(fn ($s) => $live[$s]->isNotEmpty()))
        @if ($others->isNotEmpty())
            <h2 class="mt-10 font-display text-xl font-extrabold">অন্য বিভাগ</h2>
            <div class="mt-3 flex flex-wrap gap-2">
                @foreach ($others as $s)<a href="{{ \App\Support\Url::to('/janun/'.$s) }}" class="flex min-h-11 items-center rounded-full border-2 border-navy px-4 font-bold hover:bg-peach-light">{{ \App\Support\JanunPlan::SECTIONS[$s][0] }}</a>@endforeach
                @if ($section === 'bhasha')<a href="{{ \App\Support\Url::to('/khela/probad') }}" class="flex min-h-11 items-center rounded-full bg-peach-light px-4 font-bold">খেলুন: প্রবাদ পূরণ →</a>@endif
            </div>
        @endif
    </section>
</x-layouts.app>
