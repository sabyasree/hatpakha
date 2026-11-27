{{-- D316: one letter's names; only names whose meaning two dictionaries give --}}
@php
    $U = fn ($p) => \App\Support\Url::to($p);
    $N = fn ($n) => \App\Support\Bn::num((string) $n);
    $NP = \App\Support\NamePlan::class;
    [$gBn, $gEn] = $NP::GENDERS[$p->gender];
@endphp
<x-layouts.app :seo="$seo">
    <article class="mx-auto max-w-[760px] px-5 py-8">
        @include('janun.partials.head', ['crumbs' => [['হাতপাখা', $U('/')], ['জেনে রাখুন', $U('/janun')], ['নাম ও অর্থ', $U('/janun/names')], [$p->title(), '']],
            'kicker' => 'জেনে রাখুন · নাম ও অর্থ', 'title' => $title, 'trust' => 'অর্থ দুটি অভিধানে মিলিয়ে দেখা · '.\App\Support\Bn::date($p->checked_at), 'trustBy' => $U('/janun')])
        <p lang="en" class="mt-1 text-sm text-ink2">Bengali baby {{ strtolower($gEn) }} names starting with {{ $en }} ({{ $NP::LETTERS[$p->letter] }}), with meanings</p>
        @php($origins = collect($names)->countBy('origin')->sortDesc())
        <p class="mt-3 text-sm">এই পাতায় <b>{{ $N(count($names)) }}টি নাম</b>: {{ $origins->map(fn ($c, $o) => $N($c).'টি '.($o ?: 'অন্য').' থেকে')->implode(', ') }}।</p>
        @if ($p->intro)<p class="mt-4 font-serif text-lg leading-relaxed">{{ $p->intro }}</p>@endif
        @php($twins = \App\Models\NamePage::where('status', 'live')->where('gender', $p->gender)->whereIn('letter', array_keys(array_filter($NP::EN_LETTER, fn ($v, $k) => $v === $en && $k !== $p->letter, ARRAY_FILTER_USE_BOTH)))->get()->filter->isLive())
        @if ($twins->isNotEmpty())<p lang="en" class="mt-2 text-base">More {{ strtolower($gEn) }} names starting with "{{ $en }}" in English: @foreach ($twins as $t)<a href="{{ $t->url() }}" class="font-bold underline" lang="bn">{{ $NP::LETTERS[$t->letter] }} দিয়ে</a>@if (! $loop->last), @endif @endforeach</p>@endif
        <label class="mt-5 block font-bold" for="name-q">নাম বা অর্থ খুঁজুন</label>
        <input id="name-q" type="search" data-name-q autocomplete="off" enterkeyhint="search" inputmode="search" class="mt-1 block min-h-12 w-full rounded-xl border-2 border-navy bg-white px-4 text-lg" placeholder="যেমন: সূর্য, light">
        @if ($origins->count() > 1)
            <div class="mt-3 flex flex-wrap gap-2" role="group" aria-label="উৎস ধরে দেখুন">
                <button type="button" data-origin-chip="" aria-pressed="true" class="min-h-11 rounded-full border-2 border-navy px-4 font-bold aria-pressed:bg-navy aria-pressed:text-white">সব</button>
                @foreach ($origins as $o => $c)<button type="button" data-origin-chip="{{ $o }}" aria-pressed="false" class="min-h-11 rounded-full border-2 border-navy px-4 font-bold aria-pressed:bg-navy aria-pressed:text-white">{{ $o ?: 'অন্য' }} ({{ $N($c) }})</button>@endforeach
            </div>
        @endif
        <p class="mt-2 text-sm text-ink2" data-name-count role="status">{{ $N(count($names)) }}টি নাম</p>
        <div class="mt-2 overflow-x-auto">
            <table class="w-full text-left">
                <thead><tr class="border-b-2 border-navy text-sm"><th class="py-2 pr-2">নাম</th><th class="py-2 pr-2">অর্থ</th><th class="py-2">উৎস</th></tr></thead>
                <tbody>
                    @foreach ($names as $n)
                        <tr class="border-b border-rule" data-origin="{{ $n['origin'] }}" data-name-row="{{ mb_strtolower(\Normalizer::normalize($n['bn'].' '.$n['roman'].' '.$n['meaning_bn'].' '.$n['meaning_en'], \Normalizer::FORM_C) ?: '') }}">
                            <th scope="row" class="py-2 pr-2 align-top"><span class="font-display text-xl font-extrabold">{{ $n['bn'] }}</span><span lang="en" class="block text-sm font-normal text-ink2">{{ $n['roman'] }}</span></th>
                            <td class="py-2 pr-2 align-top font-serif">{{ $n['meaning_bn'] }}<span lang="en" class="block font-sans text-sm text-ink2">{{ $n['meaning_en'] }}</span></td>
                            <td class="py-2 align-top text-sm">{{ $n['origin'] }}</td>
                        </tr>
                        @if ($loop->iteration === 12)<tr data-name-ad><td colspan="3"><x-ad-slot spot="list" :height="250" /></td></tr>@endif
                    @endforeach
                </tbody>
            </table>
        </div>
        <p data-name-none hidden class="mt-3 font-bold">এই নাম পাওয়া যায়নি। অন্য বানানে খুঁজে দেখুন, বা অর্থ দিয়ে (যেমন: আলো)।</p>
        <p class="mt-3 text-sm text-ink2">প্রতিটি অর্থ বাংলা একাডেমির বাংলা-ইংরেজি অভিধান (accessibledictionary.gov.bd) ও উইকিঅভিধানের অন্তত দুটির সঙ্গে মিলিয়ে দেখা। নাম রাখার আগে পরিবারের রীতি আর বানান নিজেরাও দেখে নিন।</p>
        <x-ad-slot spot="list" :height="250" />
        <div class="mt-4 flex flex-wrap gap-2">
            @if ($other)<a href="{{ $other->url() }}" class="flex min-h-11 items-center rounded-full bg-red px-4 font-bold text-white hover:bg-red-deep">{{ $other->title() }}</a>@endif
            @foreach ($same as $o)@if (! $o->is($p))<a href="{{ $o->url() }}" class="flex min-h-11 min-w-11 items-center justify-center rounded-full border-2 border-navy px-3 font-display text-lg font-extrabold hover:bg-peach-light" title="{{ $o->title() }}">{{ $NP::LETTERS[$o->letter] }}<span class="sr-only"> দিয়ে {{ $gBn }} নাম</span></a>@endif @endforeach
            <a href="{{ $U('/janun/names') }}" class="flex min-h-11 items-center rounded-full border-2 border-navy px-4 font-bold hover:bg-peach-light">সব অক্ষর</a>
        </div>
        @include('janun.partials.read-magazine')
    </article>
    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
    (() => {
        const q = document.querySelector('[data-name-q]'), rows = [...document.querySelectorAll('[data-name-row]')], count = document.querySelector('[data-name-count]');
        const ad = document.querySelector('[data-name-ad]'), none = document.querySelector('[data-name-none]'), head = document.querySelector('thead');
        const bn = (n) => String(n).replace(/\d/g, (d) => '০১২৩৪৫৬৭৮৯'[d]);
        let origin = '';
        const apply = () => {
            const t = q.value.trim().normalize('NFC').toLowerCase(); let n = 0;
            rows.forEach((r) => { const ok = (!t || r.dataset.nameRow.includes(t)) && (!origin || r.dataset.origin === origin); r.hidden = !ok; n += ok; });
            if (ad) ad.hidden = t !== '' || origin !== '';   // no ad among filtered results (accidental clicks)
            none.hidden = n > 0; head.hidden = n === 0;
            count.textContent = bn(n) + 'টি নাম';
        };
        q.addEventListener('input', apply);
        document.querySelectorAll('[data-origin-chip]').forEach((b) => b.addEventListener('click', () => {
            origin = b.dataset.originChip;
            document.querySelectorAll('[data-origin-chip]').forEach((x) => x.setAttribute('aria-pressed', String(x === b)));
            apply();
        }));
    })();
    </script>
</x-layouts.app>
