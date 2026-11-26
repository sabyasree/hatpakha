{{-- "পরের পুজো কবে": the next festival with a checked day (SEO review, 2026-11-27) --}}
@if ($festival)
    @php
        $nName = $festival[0];
        $nDay = $festival[1];
        $nLink = $festival[2];
    @endphp
    <a href="{{ $nLink }}" class="mt-6 flex min-h-12 flex-wrap items-center gap-x-2 rounded-2xl border-2 border-sky-deep bg-sky-tint px-4 py-3">
        <span class="text-sm font-bold">পরের পুজো:</span>
        <span class="font-display text-lg font-extrabold">{{ $nName }}</span>
        <span class="text-sm">{{ \App\Http\Controllers\BanglaCalendarController::short($nDay) }} {{ \App\Support\Bn::num((string) $nDay->year) }}, {{ \App\Support\BanglaDate::WEEKDAYS[$nDay->dayOfWeek] }} · তিথি আর আপনার দেশের সময় →</span>
    </a>
@endif
