{{-- D101: the alphabet for children (abroad). Only pictures and recordings the owner approved are shown. --}}
@php
    $letters = collect(\App\Ai\KidsAlphabet::forPage());
    $groups = ['vowel' => 'স্বরবর্ণ', 'consonant' => 'ব্যঞ্জনবর্ণ'];
    $kids = \App\Models\Section::where('slug', \App\Models\Section::KIDS)->first();
    // D321: a human recording uploaded at the desk (বাংলা শিখি: রেকর্ডিং) comes before the machine voice
    $human = \App\Models\LearnAudio::urls($letters->pluck('letter')->merge($letters->pluck('word'))->all());
    $letters = $letters->map(fn ($l) => array_merge($l, ['audio' => $human[\App\Support\LearnCourse::nfc($l['letter'])] ?? $human[\App\Support\LearnCourse::nfc($l['word'])] ?? $l['audio']]));
    $anyAudio = $letters->whereNotNull('audio')->isNotEmpty();
@endphp
<p class="mt-6 rounded-2xl bg-sky-tint p-4 text-lg">@if ($anyAudio)যে অক্ষরে 🔊 আছে, ছুঁলে শোনা যাবে। বাকিগুলো বড়রা পড়ে শোনান।@else এখন আওয়াজ নেই: অক্ষরটা ছুঁয়ে বড় করে দেখো, আর বাবা-মা পড়ে শোনান। মানুষের গলায় রেকর্ডিং শিগগির আসছে।@endif</p>
@foreach ($groups as $kind => $label)
    <section class="mt-10" aria-labelledby="bm-{{ $kind }}">
        <h2 id="bm-{{ $kind }}" class="font-display text-3xl font-extrabold">{{ $label }}</h2>
        <ul class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
            @foreach ($letters->where('kind', $kind) as $l)
                <li>
                    <button type="button" data-card aria-pressed="false" class="lift flex w-full flex-col items-center rounded-2xl border-2 border-navy bg-white p-3 text-center hover:bg-peach-light aria-pressed:bg-peach-light"
                        @if ($l['audio']) data-say="{{ $l['audio'] }}" aria-label="{{ $l['letter'] }} — {{ $l['word'] }}, শুনুন" @else aria-label="{{ $l['letter'] }} — {{ $l['word'] }}" @endif>
                        @if ($l['pic'])
                            <img src="{{ $l['pic'] }}" alt="" width="512" height="512" loading="lazy" class="aspect-square w-full rounded-xl object-cover">
                        @endif
                        <span class="mt-2 font-display text-6xl font-extrabold leading-none text-red" aria-hidden="true">{{ $l['letter'] }}</span>
                        <span class="mt-1 font-serif text-xl font-bold transition-all [[aria-pressed=true]_&]:font-display [[aria-pressed=true]_&]:text-4xl [[aria-pressed=true]_&]:font-extrabold" aria-hidden="true">{{ $l['word'] }}</span>
                        @if ($l['audio'])<span class="mt-1 text-sm font-bold text-sky-night" aria-hidden="true">🔊 শুনুন</span>@endif
                    </button>
                </li>
            @endforeach
        </ul>
    </section>
@endforeach
@if ($anyAudio)<p class="mt-6 text-sm text-ink2">কণ্ঠ: @if ($human)হাতপাখা@else হাতপাখার যন্ত্রকণ্ঠ@endif @if ($letters->whereNotNull('pic')->isNotEmpty()) · ছবি: এআই@endif</p>
@elseif ($letters->whereNotNull('pic')->isNotEmpty())<p class="mt-6 text-[11px] text-ink2">ছবি: এআই</p>@endif
<script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
    (() => {
        const player = new Audio();
        document.querySelectorAll('[data-card]').forEach((b) => b.addEventListener('click', () => {
            b.setAttribute('aria-pressed', b.getAttribute('aria-pressed') === 'true' ? 'false' : 'true');   // the word grows: something happens even without sound
            if (b.dataset.say) { player.pause(); player.src = b.dataset.say; player.currentTime = 0; player.play().catch(() => {}); }
        }));
    })();
</script>
@if ($kids)
    <a href="{{ $kids->url() }}" class="mt-10 flex min-h-14 items-center justify-between gap-3 rounded-2xl border-2 border-navy bg-sky-tint px-4 py-3 font-bold hover:bg-peach-light">
        <span>ছোটদের হাতপাখা — ছড়া আর গল্প পড়ুন</span><span aria-hidden="true">→</span></a>
@endif
<a href="{{ \App\Support\LearnCourse::url('start') }}" class="mt-4 flex min-h-14 items-center justify-between gap-3 rounded-2xl bg-red px-4 py-3 font-bold text-white shadow-[4px_4px_0_#13212C]">   {{-- D317/D321: the next step --}}
    <span>পরের ধাপ: বাংলা শিখি, খেলতে খেলতে পড়া</span><span aria-hidden="true">→</span></a>
<p class="mt-2 text-base"><a href="{{ \App\Support\LearnCourse::url() }}" class="inline-flex min-h-11 items-center underline">বাবা-মায়ের জন্য: বাংলা শিখি কীভাবে চলে, ওয়ার্কশিট</a></p>
