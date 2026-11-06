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
    @elseif ($task->type === 'quality')
        <p style="margin:0 0 .6rem">{{ $p['summary'] ?? '' }}</p>
        <ul style="margin:0 0 .6rem;padding-inline-start:1.2rem;list-style:disc">
            @foreach ((array) ($p['cases'] ?? []) as $c)
                <li><strong>{{ $c['title'] }}</strong> · {{ ['fair' => __('ঠিক'), 'too_harsh' => __('বেশি কড়া'), 'too_generous' => __('বেশি নরম'), 'wrong_decision' => __('সিদ্ধান্ত ভুল')][$c['verdict']] ?? $c['verdict'] }} — {{ $c['comment'] }}</li>
            @endforeach
        </ul>
        @if (! empty($p['suggestions']))
            <p style="margin:0;font-weight:700">{{ __('মানদণ্ডে যা যোগ করা যায়') }}</p>
            <ol style="margin:0;padding-inline-start:1.4rem;list-style:decimal">@foreach ($p['suggestions'] as $s)<li>{{ $s }}</li>@endforeach</ol>
        @endif
    @elseif ($task->type === 'insights')
        <p style="margin:0 0 .4rem">{{ __('মোট পড়া') }}: <strong>{{ $n($p['views'] ?? 0) }}</strong> · {{ __('খোঁজ থেকে') }} {{ $n($p['search'] ?? 0) }} · {{ __('সামাজিক মাধ্যম থেকে') }} {{ $n($p['social'] ?? 0) }} · {{ __('সাইটের ভেতর থেকে') }} {{ $n($p['internal'] ?? 0) }}</p>
        @if (! empty($p['points']))<ul style="margin:0 0 .6rem;padding-inline-start:1.2rem;list-style:disc">@foreach ($p['points'] as $pt)<li>{{ $pt }}</li>@endforeach</ul>@endif
        <p style="margin:0;font-weight:700">{{ __('সবচেয়ে বেশি পড়া') }}</p>
        <ol style="margin:0 0 .6rem;padding-inline-start:1.4rem;list-style:decimal">@foreach ((array) ($p['top'] ?? []) as $t)<li>{{ $t['title'] }} · {{ $n($t['views']) }}</li>@endforeach</ol>
        @if (! empty($p['old']))<p style="margin:0;font-weight:700">{{ __('পুরনো লেখা, এখনও পড়া হচ্ছে') }}</p>
            <ul style="margin:0;padding-inline-start:1.2rem;list-style:disc">@foreach ($p['old'] as $t)<li>{{ $t['title'] }} · {{ $n($t['views']) }}</li>@endforeach</ul>@endif
    @elseif ($task->type === 'team_review')
        <ul style="margin:0 0 .6rem;padding-inline-start:1.2rem;list-style:disc">
            @foreach ((array) ($p['agents'] ?? []) as $key => $a)
                <li>{{ __(\App\Ai\Team::AGENTS[$key][0] ?? $key) }}: {{ $n($a['calls']) }} {{ __('কাজ') }} · {{ $n(number_format((float) $a['cost'], 2)) }} {{ __('ডলার') }}</li>
            @endforeach
        </ul>
        <p style="margin:0">{{ __('গত মাসের মোট খরচ') }}: <strong>{{ $n(number_format((float) ($p['total'] ?? 0), 2)) }} {{ __('ডলার') }}</strong> · {{ __('এই মাসের আনুমানিক খরচ') }}: <strong>{{ $n(number_format((float) ($p['forecast'] ?? 0), 2)) }} {{ __('ডলার') }}</strong></p>
        @if (! empty($p['open']))<p style="margin:.4rem 0 0;opacity:.8">{{ __('খোলা কাজ') }}: {{ $n(array_sum($p['open'])) }}</p>@endif
    @elseif ($task->type === 'seo_refresh')
        <p style="margin:0 0 .4rem">{{ __('এই পুরনো লেখাগুলো গুগল থেকে এখনও পাঠক পাচ্ছে। এসইও বিশেষজ্ঞ নতুন তথ্য প্রস্তাব করেছে; প্রতিটি খুলে "এসইও" থেকে দেখে নিন।') }}</p>
        <ul style="margin:0;padding-inline-start:1.2rem;list-style:disc">
            @foreach ((array) ($p['posts'] ?? []) as $pp)<li><a href="{{ \App\Filament\Resources\Posts\PostResource::getUrl('edit', ['record' => $pp['post']]) }}" style="text-decoration:underline">{{ $pp['title'] }}</a></li>@endforeach
        </ul>
    @elseif ($task->type === 'plagiarism')
        <p style="margin:0">{{ $task->subject?->title }}</p>
        <ul style="margin:.4rem 0 0;padding-inline-start:1.2rem;list-style:disc">
            @foreach ((array) ($p['matches'] ?? []) as $m)<li><a href="{{ $m['url'] }}" target="_blank" rel="noopener noreferrer nofollow" style="text-decoration:underline">{{ $m['url'] }}</a> <span style="opacity:.75">{{ $m['line'] }}</span></li>@endforeach
        </ul>
        @if ($task->subject)<p style="margin:.6rem 0 0"><a href="{{ \App\Filament\Resources\Posts\PostResource::getUrl('edit', ['record' => $task->subject]) }}" style="text-decoration:underline">{{ __('লেখাটি খুলুন') }}</a></p>@endif
    @elseif ($task->type === 'alert')
        <p style="margin:0">{{ $task->title }}</p>
        @if (! empty($p['errors']))<ul style="margin:.4rem 0 0;padding-inline-start:1.2rem;list-style:disc">@foreach ($p['errors'] as $e)<li style="opacity:.8">{{ $e }}</li>@endforeach</ul>@endif
        <p style="margin:.6rem 0 0;opacity:.75">{{ __('ঠিক হয়ে গেলে প্রধান সম্পাদক নিজেই সতর্কতা সরিয়ে দেবে।') }}</p>
    @else
        <ol style="margin:0;padding-inline-start:1.4rem;list-style:decimal">
            @foreach ((array) ($p['problems'] ?? []) as $pr)
                <li><a href="{{ \App\Filament\Resources\Posts\PostResource::getUrl('edit', ['record' => $pr['post']]) }}" style="text-decoration:underline">{{ $pr['title'] }}</a>
                    <span style="opacity:.75">· {{ __($pr['what']) }} · {{ $pr['url'] }}</span></li>
            @endforeach
        </ol>
    @endif
</div>
