{{-- D302: festival times in any time zone. $festivals = FestivalPlan::upcoming(); $heading; $links (bool) --}}
@php($livePages = ($links ?? true) ? \App\Support\FestivalPlan::livePages()->map(fn ($p) => $p[0].'|'.$p[1])->flip() : collect())
@php($pages = ($links ?? true) ? \App\Models\JanunPage::live()->whereIn('key', collect(\App\Support\FestivalPlan::FESTIVALS)->pluck(1)->filter()->all())->get(['id', 'key', 'section', 'slug'])->keyBy('key') : collect())
<section data-tz-box class="mt-6 rounded-2xl border-2 border-sky-deep bg-sky-tint p-4">
    <h2 class="font-display text-xl font-extrabold">{{ $heading ?? 'তিথির সময়' }}</h2>
    <p data-tz-nojs class="mt-2 text-sm text-ink2">সব সময় ভারতের সময়ে (IST) দেখানো। নিজের দেশের সময়ে দেখতে ব্রাউজারে জাভাস্ক্রিপ্ট চালু রাখুন।</p>
    <label data-tz-js hidden class="mt-2 block text-sm font-bold print:hidden" for="tz-{{ $boxId = 'b'.substr(md5(($heading ?? '').count($festivals)), 0, 6) }}">কোন দেশের সময়ে দেখবেন?</label>
    <select id="tz-{{ $boxId }}" data-tz data-tz-js hidden class="mt-1 block print:hidden min-h-12 w-full rounded-xl border-2 border-navy bg-white px-3 text-base">
        @foreach (\App\Support\FestivalPlan::ZONES as $z => $label)<option value="{{ $z }}">{{ $label }}</option>@endforeach
    </select>
    <input type="search" data-tz-find data-tz-js hidden aria-label="শহর বা দেশের নাম (ইংরেজিতে) লিখে খুঁজুন" placeholder="শহর খুঁজুন (ইংরেজিতে), যেমন Chicago, London, Tokyo" class="mt-2 block min-h-11 w-full rounded-xl border-2 border-rule bg-white px-3 text-base print:hidden">
    <p data-tz-none hidden class="mt-1 text-sm font-bold text-red">মেলেনি; শহরের নাম ইংরেজিতে লিখুন (যেমন London)।</p>
    <p class="mt-1 text-sm text-ink2">সময় দেখানো হচ্ছে: <b data-tz-name aria-live="polite">ভারত</b></p>
    @if ($festivals->isEmpty())<p class="mt-4 rounded-xl bg-white p-3">এ বছরের পুজোর সময় মিলিয়ে দেখা হচ্ছে; শিগগির এখানে দেওয়া হবে।</p>@endif
    @foreach ($festivals as [$key, $name, $cycle, $rows])
        <div data-festival class="mt-4 rounded-xl bg-white p-3">
            <h3 class="font-display text-lg font-extrabold">
                @php($fp = ($links ?? true) && $livePages->has($key.'|'.$cycle) ? \App\Support\FestivalPlan::pageUrl($key, $cycle) : (($pg = $pages[\App\Support\FestivalPlan::FESTIVALS[$key][1] ?? ''] ?? null) ? $pg->url() : null))
                @if ($fp)<a href="{{ $fp }}" class="inline-flex min-h-11 items-center underline">{{ $name }} {{ \App\Support\Bn::num((string) $cycle) }}</a>@else{{ $name }} {{ \App\Support\Bn::num((string) $cycle) }}@endif
            </h3>
            <dl class="mt-2 space-y-3">
                @foreach ($rows as $t)
                    <div>
                        <dt class="font-bold">{{ $t->label }}</dt>
                        @if ($t->starts_at)
                            <dd class="text-base">শুরু: <time datetime="{{ $t->starts_at->toIso8601String() }}" data-utc="{{ $t->starts_at->toIso8601String() }}">{{ \App\Support\FestivalPlan::show($t->starts_at) }}</time></dd>
                            <dd class="text-base">শেষ: <time datetime="{{ $t->ends_at->toIso8601String() }}" data-utc="{{ $t->ends_at->toIso8601String() }}">{{ \App\Support\FestivalPlan::show($t->ends_at) }}</time></dd>
                        @endif
                        @if ($t->day)<dd class="text-sm text-ink2">কলকাতায় পালনের দিন: {{ \App\Support\FestivalPlan::show($t->day, false) }}</dd>@endif
                    </div>
                @endforeach
            </dl>
            <p data-tz-differs hidden class="mt-2 rounded-lg bg-peach-light p-2 text-sm">আপনার দেশে তিথির তারিখ কলকাতার চেয়ে আলাদা পড়ছে। কোন দিন পুজো, তা নিজের এলাকার পুরোহিত বা পুজো কমিটির কাছে জেনে নিন।</p>
        </div>
    @endforeach
    <p class="mt-3 text-sm text-ink2">সময়: বিশুদ্ধ সিদ্ধান্ত পঞ্জিকা (কলকাতা), প্রকাশিত সূত্রে মিলিয়ে, আপনার দেশের ঘড়িতে বদলে দেখানো। তিথি একই মুহূর্তে সারা পৃথিবীতে শুরু হয়, তাই কোনো দেশে তারিখ এক দিন আগে-পরে পড়তে পারে। বিদেশের অনেক পুজো কমিটি সপ্তাহান্তে পুজো করেন; নিজের এলাকার সময় তাঁদের কাছে জেনে নিন।</p>
</section>
@once
    <script src="{{ asset('js/hp-tz.js') }}?v=4" defer></script>
@endonce
