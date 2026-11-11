{{-- "আমার পাঠক" (owner, 2026-11-12): a writer's own numbers, plainly, and a card to share each piece on Facebook.
     Totals only: nothing about any reader is kept or shown. --}}
@php
    use App\Support\Bn;
    $n = fn ($v) => Bn::num((string) $v);
    $max = max(1, $s['days']->max());
    $writerName = $authors->first()?->publicName() ?? $user->name;
@endphp
<x-layouts.app :seo="$seo">
    <x-account-shell tab="writings" :user="$user" :author="$author" :counts="$counts" :two-factor-on="$twoFactorOn" :cert-count="$certCount">
        <a href="{{ \App\Support\Url::to('/account/writings') }}" class="inline-flex min-h-11 items-center text-sm font-bold text-sky-deep underline">← আমার লেখা</a>
        <h2 class="font-display text-3xl font-extrabold">আমার পাঠক</h2>
        <p class="mt-1 text-ink2">আপনার লেখা কতবার পড়া হচ্ছে, কতজনের ভালো লাগছে।</p>

        @if ($s['rows']->isEmpty())
            <div class="mt-6 rounded-3xl border-2 border-dashed border-navy p-6 text-center">
                <p class="text-lg font-bold">এখনও আপনার কোনো লেখা প্রকাশিত হয়নি।</p>
                <p class="mt-1 text-ink2">লেখা প্রকাশ হলেই এখানে তার পাঠকের হিসাব দেখা যাবে।</p>
                <a href="{{ \App\Support\Url::to('/submit') }}" class="mt-4 inline-flex h-12 items-center rounded-full bg-red px-6 font-bold text-white">লেখা পাঠান</a>
            </div>
        @else
            {{-- The four numbers that matter --}}
            <dl class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
                @foreach ([['মোট পড়া হয়েছে', $s['reads'], 'বার'], ['গত ৩০ দিনে', $s['month'], 'বার'], ['ভালো লেগেছে', $s['likes'], 'জনের'], ['পরে পড়বেন বলে রেখেছেন', $s['saves'], 'জন']] as [$label, $value, $unit])
                    <div class="rounded-2xl border-2 border-navy bg-white p-4">
                        <dt class="text-sm text-ink2">{{ $label }}</dt>
                        <dd class="mt-1 font-display text-3xl font-extrabold leading-none tabular-nums">{{ $n($value) }} <span class="text-base font-bold text-ink2">{{ $unit }}</span></dd>
                    </div>
                @endforeach
            </dl>
            <p class="mt-3 text-ink2">@if ($s['followers'])<b class="text-navy">{{ $n($s['followers']) }} জন</b> আপনাকে অনুসরণ করেন: নতুন লেখা বেরোলেই তাঁরা খবর পান।@else এখনও কেউ অনুসরণ করেননি; লেখক-পাতাটি শেয়ার করলে পাঠকেরা অনুসরণ করতে পারবেন।@endif</p>

            @if ($s['best'] && $s['best']['reads'] > 0)
                <div class="mt-5 flex flex-wrap items-center gap-3 rounded-3xl bg-navy p-5 text-paper">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-bold text-peach">সবচেয়ে বেশি পড়া</p>
                        <p class="font-serif text-xl font-bold leading-snug">{{ $s['best']['post']->title }}</p>
                        <p class="text-sm text-peach-light">{{ $n($s['best']['reads']) }} বার পড়া হয়েছে</p>
                    </div>
                    <button type="button" class="h-12 rounded-full bg-red px-5 font-bold text-white" data-share-card
                        data-title="{{ $s['best']['post']->title }}" data-by="{{ $writerName }}" data-section="{{ $s['best']['post']->section?->name_bn }}" data-url="{{ $s['best']['post']->url() }}">শেয়ার কার্ড বানান</button>
                </div>
            @endif

            {{-- 30 days --}}
            <section class="mt-8" aria-labelledby="st-days">
                <h3 id="st-days" class="text-lg font-extrabold">গত ৩০ দিন</h3>
                <p class="mt-2 text-sm text-ink2">সবচেয়ে বেশি একদিনে: <b class="text-navy">{{ $n($max) }} বার</b> · আজ এখনও পর্যন্ত: <b class="text-red">{{ $n($s['days']->last()) }} বার</b></p>
                <div class="mt-2 flex h-32 items-end gap-[2px] rounded-2xl border-2 border-rule bg-white p-3" aria-hidden="true">
                    @foreach ($s['days'] as $day => $v)
                        <span class="flex-1 rounded-t-sm {{ $loop->last ? 'bg-red' : 'bg-navy' }}" style="height: {{ max(2, round(100 * $v / $max)) }}%" title="{{ Bn::date(\Illuminate\Support\Carbon::parse($day)) }}: {{ $n($v) }}"></span>
                    @endforeach
                </div>
                <p class="mt-1 flex justify-between text-xs text-ink2"><span>৩০ দিন আগে</span><span>আজ</span></p>
                <p class="sr-only">গত ৩০ দিনে মোট {{ $n($s['month']) }} বার পড়া হয়েছে; সবচেয়ে বেশি একদিনে {{ $n($max) }} বার।</p>
            </section>

            {{-- Each piece --}}
            <section class="mt-8" aria-labelledby="st-pieces">
                <h3 id="st-pieces" class="text-lg font-extrabold">প্রতিটি লেখা</h3>
                <ul class="mt-2 divide-y divide-rule border-y border-rule">
                    @foreach ($s['rows'] as $r)
                        <li class="flex flex-wrap items-center gap-3 py-3">
                            <div class="min-w-0 flex-1">
                                <a href="{{ $r['post']->url() }}" class="block font-serif text-lg font-bold leading-snug hover:text-red">{{ $r['post']->title }}</a>
                                <p class="text-sm text-ink2">{{ Bn::date($r['post']->published_at) }} · {{ $r['post']->section?->name_bn }}</p>
                                <p class="mt-1 text-sm"><b class="tabular-nums">{{ $n($r['reads']) }}</b> বার পড়া · গত ৩০ দিনে {{ $n($r['month']) }}</p>
                                <p class="text-sm text-ink2">ভালো লেগেছে {{ $n($r['likes']) }} জনের · পরে পড়বেন বলে রেখেছেন {{ $n($r['saves']) }} জন</p>
                            </div>
                            <button type="button" class="h-11 flex-none rounded-full border-2 border-navy px-4 font-bold hover:bg-peach-light" data-share-card
                                data-title="{{ $r['post']->title }}" data-by="{{ $writerName }}" data-section="{{ $r['post']->section?->name_bn }}" data-url="{{ $r['post']->url() }}">শেয়ার কার্ড</button>
                        </li>
                    @endforeach
                </ul>
            </section>
            <p class="mt-6 text-sm text-ink2">কীভাবে গোনা হয়: লেখার পাতা কয়েক সেকেন্ড খোলা থাকলে একবার; একই জন আধঘণ্টার মধ্যে আবার খুললে আর গোনা হয় না। কে পড়েছেন, তা রাখা হয় না; শুধু মোট সংখ্যা।</p>
        @endif

        {{-- The share card ("আমার লেখা হাতপাখায়"): drawn in the browser, so Bengali letters come out right. --}}
        <dialog data-card-dialog class="w-[min(92vw,460px)] rounded-3xl border-2 border-navy p-0 backdrop:bg-navy/70">
            <div class="p-4">
                <div class="flex items-center justify-between gap-2">
                    <h3 class="text-lg font-extrabold">আমার লেখা হাতপাখায়</h3>
                    <button type="button" data-card-close class="h-10 w-10 rounded-full border-2 border-navy font-bold" aria-label="বন্ধ করুন">✕</button>
                </div>
                <canvas data-card-canvas width="1080" height="1350" class="mt-3 w-full rounded-xl border border-rule" role="img" aria-label="শেয়ার কার্ড"></canvas>
                <p class="mt-2 text-sm text-ink2">ছবিটি ফেসবুকে পোস্ট করুন, সঙ্গে লেখার লিংকটিও দিন (“লিংক কপি” চাপলেই কপি হয়ে যাবে)।</p>
                <div class="mt-3 grid gap-2 sm:grid-cols-3">
                    <button type="button" data-card-share hidden class="h-12 rounded-full bg-red px-3 font-bold text-white sm:col-span-3">ফেসবুক / হোয়াটসঅ্যাপে পাঠান</button>
                    <button type="button" data-card-download class="h-12 rounded-full bg-navy font-bold text-white">ছবি নামান</button>
                    <button type="button" data-card-copy class="h-12 rounded-full border-2 border-navy font-bold">লিংক কপি</button>
                </div>
                <p data-card-msg class="mt-2 text-center text-sm font-bold text-sky-night" role="status"></p>
            </div>
        </dialog>
    </x-account-shell>
    <script src="{{ asset('js/hp-share-card.js') }}?v=2" defer></script>
</x-layouts.app>
