{{-- D302: festival times in any time zone. $festivals = FestivalPlan::upcoming(); $heading; $links (bool) --}}
@php($pages = ($links ?? true) ? \App\Models\JanunPage::live()->whereIn('key', collect(\App\Support\FestivalPlan::FESTIVALS)->pluck(1)->filter()->all())->get(['id', 'key', 'section', 'slug'])->keyBy('key') : collect())
<section data-tz-box class="mt-6 rounded-2xl border-2 border-sky-deep bg-sky-tint p-4">
    <h2 class="font-display text-xl font-extrabold">{{ $heading ?? 'তিথির সময়' }}</h2>
    <label class="mt-2 block text-sm font-bold print:hidden" for="tz-{{ $boxId = 'b'.substr(md5(($heading ?? '').count($festivals)), 0, 6) }}">কোন দেশের সময়ে দেখবেন?</label>
    <select id="tz-{{ $boxId }}" data-tz class="mt-1 block print:hidden min-h-12 w-full rounded-xl border-2 border-navy bg-white px-3 text-base">
        @foreach (\App\Support\FestivalPlan::ZONES as $z => $label)<option value="{{ $z }}">{{ $label }}</option>@endforeach
    </select>
    <p class="mt-1 text-sm text-ink2">সময় দেখানো হচ্ছে: <b data-tz-name>ভারত</b></p>
    @foreach ($festivals as [$key, $name, $cycle, $rows])
        <div class="mt-4 rounded-xl bg-white p-3">
            <h3 class="font-display text-lg font-extrabold">
                @if ($pg = $pages[\App\Support\FestivalPlan::FESTIVALS[$key][1] ?? ''] ?? null)<a href="{{ $pg->url() }}" class="underline">{{ $name }} {{ \App\Support\Bn::num((string) $cycle) }}</a>@else{{ $name }} {{ \App\Support\Bn::num((string) $cycle) }}@endif
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
        </div>
    @endforeach
    <p class="mt-3 text-sm text-ink2">সময়: বিশুদ্ধ সিদ্ধান্ত পঞ্জিকা (কলকাতা), অন্তত দুটি সূত্রে মিলিয়ে, আপনার দেশের ঘড়িতে বদলে দেখানো। তিথি একই মুহূর্তে সারা পৃথিবীতে শুরু হয়, তাই কোনো দেশে তারিখ এক দিন আগে-পরে পড়তে পারে। বিদেশের অনেক পুজো কমিটি সপ্তাহান্তে পুজো করেন; নিজের এলাকার সময় তাঁদের কাছে জেনে নিন।</p>
</section>
@once
    <script src="{{ asset('js/hp-tz.js') }}?v=1" defer></script>
@endonce
