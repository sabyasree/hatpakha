{{-- Monthly report / site health for the owner (D48). Inline styles: /desk has no utility CSS. --}}
@php($p = (array) $task->payload)
@php($n = fn ($v) => \App\Support\Bn::num((string) $v))
<div style="line-height:1.9">
    @if ($task->type === 'report')
        <ul style="margin:0;padding-inline-start:1.2rem;list-style:disc">
            <li>{{ __('প্রকাশিত লেখা') }}: <strong>{{ $n($p['published'] ?? 0) }}</strong></li>
            <li>{{ __('জমা পড়েছে') }}: {{ $n($p['received'] ?? 0) }} · {{ __('মনোনীত') }} {{ $n($p['approved'] ?? 0) }} · {{ __('বদল চাওয়া') }} {{ $n($p['changes'] ?? 0) }} · {{ __('বাতিল') }} {{ $n($p['rejected'] ?? 0) }}</li>
            <li>{{ __('মনোনয়নের হার') }}: {{ $n($p['acceptance'] ?? 0) }}%</li>
            <li>{{ __('নতুন লেখক') }}: {{ $n($p['new_writers'] ?? 0) }} · {{ __('প্রকাশিত মন্তব্য') }}: {{ $n($p['comments'] ?? 0) }}</li>
            <li>{{ __('সবচেয়ে বেশি লেখা') }}: {{ collect($p['sections'] ?? [])->map(fn ($c, $s) => $s.' '.$n($c))->implode(' · ') ?: '—' }}</li>
            <li>{{ __('চিঠির পাঠক (সম্মতি দিয়েছেন)') }}: {{ $n($p['subscribers'] ?? 0) }}</li>
            <li>{{ __('এআই খরচ') }}: {{ $n(number_format((float) ($p['ai_cost'] ?? 0), 2)) }} {{ __('ডলার') }}</li>
        </ul>
    @else
        <ol style="margin:0;padding-inline-start:1.4rem;list-style:decimal">
            @foreach ((array) ($p['problems'] ?? []) as $pr)
                <li><a href="{{ \App\Filament\Resources\Posts\PostResource::getUrl('edit', ['record' => $pr['post']]) }}" style="text-decoration:underline">{{ $pr['title'] }}</a>
                    <span style="opacity:.75">· {{ __($pr['what']) }} · {{ $pr['url'] }}</span></li>
            @endforeach
        </ol>
    @endif
</div>
