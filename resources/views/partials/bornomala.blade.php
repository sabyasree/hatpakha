{{-- D101: the alphabet for children (abroad). Only pictures and recordings the owner approved are shown. --}}
@php
    $letters = collect(\App\Ai\KidsAlphabet::forPage());
    $groups = ['vowel' => 'স্বরবর্ণ', 'consonant' => 'ব্যঞ্জনবর্ণ'];
    $kids = \App\Models\Section::where('slug', \App\Models\Section::KIDS)->first();
@endphp
@foreach ($groups as $kind => $label)
    <section class="mt-10" aria-labelledby="bm-{{ $kind }}">
        <h2 id="bm-{{ $kind }}" class="font-display text-3xl font-extrabold">{{ $label }}</h2>
        <ul class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
            @foreach ($letters->where('kind', $kind) as $l)
                <li>
                    <button type="button" class="lift flex w-full flex-col items-center rounded-2xl border-2 border-navy bg-white p-3 text-center hover:bg-peach-light disabled:cursor-default disabled:hover:bg-white"
                        @if ($l['audio']) data-say="{{ $l['audio'] }}" aria-label="{{ $l['letter'] }} — {{ $l['word'] }}, শুনুন" @else disabled aria-label="{{ $l['letter'] }} — {{ $l['word'] }}" @endif>
                        @if ($l['pic'])
                            <img src="{{ $l['pic'] }}" alt="" width="512" height="512" loading="lazy" class="aspect-square w-full rounded-xl object-cover">
                        @endif
                        <span class="mt-2 font-display text-6xl font-extrabold leading-none text-red" aria-hidden="true">{{ $l['letter'] }}</span>
                        <span class="mt-1 font-serif text-xl font-bold" aria-hidden="true">{{ $l['word'] }}</span>
                        @if ($l['audio'])<span class="mt-1 text-sm font-bold text-sky-night" aria-hidden="true">🔊 শুনুন</span>@endif
                    </button>
                </li>
            @endforeach
        </ul>
    </section>
@endforeach
@if ($letters->whereNotNull('audio')->isNotEmpty())
    <p class="mt-6 text-sm text-ink2">কণ্ঠ: হাতপাখার যন্ত্রকণ্ঠ@if ($letters->whereNotNull('pic')->isNotEmpty()) · ছবি: এআই-এর সাহায্যে আঁকা@endif</p>
    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
        (() => {
            const player = new Audio();
            document.querySelectorAll('[data-say]').forEach((b) => b.addEventListener('click', () => {
                player.pause(); player.src = b.dataset.say; player.currentTime = 0; player.play().catch(() => {});
            }));
        })();
    </script>
@elseif ($letters->whereNotNull('pic')->isNotEmpty())
    <p class="mt-6 text-sm text-ink2">ছবি: এআই-এর সাহায্যে আঁকা</p>
@endif
@if ($kids)
    <a href="{{ $kids->url() }}" class="mt-10 flex min-h-14 items-center justify-between gap-3 rounded-2xl border-2 border-navy bg-sky-tint px-4 py-3 font-bold hover:bg-peach-light">
        <span>ছোটদের হাতপাখা — ছড়া আর গল্প পড়ুন</span><span aria-hidden="true">→</span></a>
@endif
