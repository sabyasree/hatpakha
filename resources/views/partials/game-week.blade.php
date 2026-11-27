{{-- D330: this week, Sunday to Saturday, as seven dots: played days filled, today ringed. A missed day is one empty
     dot, never "your streak is lost". The dots take the full width and the words sit below them (UX panel: side by
     side they broke into one word a line on a 360px phone). $days: GameScores::weekDays(). --}}
@php
    $names = ['রবি', 'সোম', 'মঙ্গল', 'বুধ', 'বৃহঃ', 'শুক্র', 'শনি'];
    $today = (int) now('Asia/Kolkata')->dayOfWeek;
    $count = count(array_filter($days));
@endphp
<div aria-label="এ সপ্তাহে {{ \App\Support\Bn::num((string) $count) }} দিন খেলেছেন">
    <ol class="flex justify-between" aria-hidden="true">
        @foreach ($days as $i => $on)
            <li class="flex flex-col items-center gap-0.5">
                <span class="flex h-8 w-8 items-center justify-center rounded-full border-2 text-sm font-extrabold {{ $on ? 'border-navy bg-red text-white' : ($i === $today ? 'border-red bg-white text-red' : ($i < $today ? 'border-dashed border-ink2 bg-white text-ink2' : 'border-navy bg-white text-ink2')) }}">{{ $on ? '✓' : '' }}</span>
                <span class="text-[13px] font-bold {{ $i === $today ? 'text-red' : 'text-ink2' }}">{{ $names[$i] }}</span>
            </li>
        @endforeach
    </ol>
    <p class="mt-1.5 text-[15px] font-bold leading-snug">এ সপ্তাহে {{ \App\Support\Bn::num((string) $count) }} দিন খেলেছেন। @if ($count < \App\Support\GameScores::WEEK_MIN_DAYS)<span class="font-normal text-ink2">{{ \App\Support\Bn::num((string) \App\Support\GameScores::WEEK_MIN_DAYS) }} দিন খেললেই সপ্তাহের সেরার দৌড়ে।</span>@else<span class="font-normal text-ink2">সপ্তাহের সেরার দৌড়ে আছেন।</span>@endif</p>
</div>
