{{-- The writer's tracker (owner, 2026-11-24): পাঠানো → সম্পাদকের কাছে পৌঁছেছে / সম্পাদক পড়ছেন → বাছাই → প্রকাশিত, with dates.
     "সম্পাদক পড়ছেন" only once an editor has really opened the piece in the desk (posts.desk_opened_at). --}}
@php
    $steps = \App\Support\WriterJourney::steps($p);
    $current = collect($steps)->filter(fn ($s) => $s['done'])->keys()->last();
@endphp
<ol class="{{ $class ?? 'mt-2' }} flex flex-wrap items-start gap-x-1 gap-y-2 text-sm font-bold" aria-label="{{ __('অবস্থা') }}" data-tracker>
    @foreach ($steps as $i => $s)
        <li class="flex items-start gap-1 {{ $s['done'] ? 'text-sky-night' : 'font-medium text-ink2' }}" @if ($i === $current) aria-current="step" @endif>
            <span class="mt-1 inline-block h-3 w-3 flex-none rounded-full {{ $s['done'] ? 'bg-sky-deep' : 'bg-rule' }}"></span>
            <span>{{ __($s['label']) }}@if ($s['date'])<span class="block text-xs font-medium text-ink2">{{ $s['date'] }}</span>@endif</span>
            @if (! $loop->last)<span class="mx-0.5 text-ink2" aria-hidden="true">—</span>@endif
        </li>
    @endforeach
</ol>
