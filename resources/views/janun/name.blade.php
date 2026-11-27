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
        <p lang="en" class="mt-1 text-sm text-ink2">Bengali baby {{ strtolower($gEn) }} names starting with {{ $NP::LETTERS[$p->letter] }} ({{ strtoupper($p->letter) }}), with meanings</p>
        @if ($p->intro)<p class="mt-4 font-serif text-lg leading-relaxed">{{ $p->intro }}</p>@endif
        <label class="mt-5 block font-bold" for="name-q">নাম বা অর্থ খুঁজুন</label>
        <input id="name-q" type="search" data-name-q autocomplete="off" class="mt-1 block min-h-12 w-full rounded-xl border-2 border-navy bg-white px-4 text-lg" placeholder="যেমন: সূর্য, light">
        <p class="mt-2 text-sm text-ink2" data-name-count>{{ $N(count($names)) }}টি নাম</p>
        <div class="mt-2 overflow-x-auto">
            <table class="w-full text-left">
                <thead><tr class="border-b-2 border-navy text-sm"><th class="py-2 pr-2">নাম</th><th class="py-2 pr-2">অর্থ</th><th class="py-2">উৎস</th></tr></thead>
                <tbody>
                    @foreach ($names as $n)
                        <tr class="border-b border-rule" data-name-row="{{ mb_strtolower($n['bn'].' '.$n['roman'].' '.$n['meaning_bn'].' '.$n['meaning_en']) }}">
                            <th scope="row" class="py-2 pr-2 align-top"><span class="font-display text-xl font-extrabold">{{ $n['bn'] }}</span><span lang="en" class="block text-sm font-normal text-ink2">{{ $n['roman'] }}</span></th>
                            <td class="py-2 pr-2 align-top font-serif">{{ $n['meaning_bn'] }}<span lang="en" class="block font-sans text-sm text-ink2">{{ $n['meaning_en'] }}</span></td>
                            <td class="py-2 align-top text-sm">{{ $n['origin'] }}</td>
                        </tr>
                        @if ($loop->iteration === 12)<tr><td colspan="3"><x-ad-slot spot="list" :height="250" /></td></tr>@endif
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="mt-3 text-sm text-ink2">প্রতিটি অর্থ বাংলা একাডেমির বাংলা-ইংরেজি অভিধান (accessibledictionary.gov.bd) ও উইকিঅভিধানের অন্তত দুটির সঙ্গে মিলিয়ে দেখা। নাম রাখার আগে পরিবারের রীতি আর বানান নিজেরাও দেখে নিন।</p>
        <x-ad-slot spot="list" :height="250" />
        <div class="mt-4 flex flex-wrap gap-2">
            @if ($other)<a href="{{ $other->url() }}" class="flex min-h-11 items-center rounded-full bg-red px-4 font-bold text-white hover:bg-red-deep">{{ $other->title() }}</a>@endif
            @foreach ($same as $o)@if (! $o->is($p))<a href="{{ $o->url() }}" class="flex min-h-11 min-w-11 items-center justify-center rounded-full border-2 border-navy px-3 font-display text-lg font-extrabold hover:bg-peach-light" aria-label="{{ $o->title() }}">{{ $NP::LETTERS[$o->letter] }}</a>@endif @endforeach
            <a href="{{ $U('/janun/names') }}" class="flex min-h-11 items-center rounded-full border-2 border-navy px-4 font-bold hover:bg-peach-light">সব অক্ষর</a>
        </div>
    </article>
    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
    (() => {
        const q = document.querySelector('[data-name-q]'), rows = [...document.querySelectorAll('[data-name-row]')], count = document.querySelector('[data-name-count]');
        const bn = (n) => String(n).replace(/\d/g, (d) => '০১২৩৪৫৬৭৮৯'[d]);
        q.addEventListener('input', () => {
            const t = q.value.trim().toLowerCase(); let n = 0;
            rows.forEach((r) => { const ok = !t || r.dataset.nameRow.includes(t); r.hidden = !ok; n += ok; });
            count.textContent = bn(n) + 'টি নাম';
        });
    })();
    </script>
</x-layouts.app>
