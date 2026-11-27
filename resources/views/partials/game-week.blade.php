{{-- D330: this week, Sunday to Saturday, as seven dots: played days filled, today ringed. A missed day is one empty
     dot, never "your streak is lost". $days: GameScores::weekDays(). --}}
@php
    $names = ['র', 'সো', 'ম', 'বু', 'বৃ', 'শু', 'শ'];
    $today = (int) now('Asia/Kolkata')->dayOfWeek;
    $count = count(array_filter($days));
@endphp
<div class="flex items-center gap-3" aria-label="এ সপ্তাহে {{ \App\Support\Bn::num((string) $count) }} দিন খেলেছেন">
    <ol class="flex gap-1.5" aria-hidden="true">
        @foreach ($days as $i => $on)
            <li class="flex flex-col items-center gap-0.5">
                <span class="flex h-8 w-8 items-center justify-center rounded-full border-2 text-sm font-extrabold {{ $on ? 'border-navy bg-red text-white' : ($i === $today ? 'border-red bg-white text-red' : ($i < $today ? 'border-rule bg-white text-ink2' : 'border-dashed border-navy bg-white text-ink2')) }}">{{ $on ? '✓' : '' }}</span>
                <span class="text-xs font-bold {{ $i === $today ? 'text-red' : 'text-ink2' }}">{{ $names[$i] }}</span>
            </li>
        @endforeach
    </ol>
    <p class="text-sm font-bold leading-tight">এ সপ্তাহে {{ \App\Support\Bn::num((string) $count) }} দিন@if ($count < \App\Support\GameScores::WEEK_MIN_DAYS)<span class="block font-normal text-ink2">{{ \App\Support\Bn::num((string) \App\Support\GameScores::WEEK_MIN_DAYS) }} দিন খেললেই সপ্তাহের সেরার দৌড়ে</span>@else<span class="block font-normal text-ink2">সপ্তাহের সেরার দৌড়ে আছেন</span>@endif</p>
</div>
