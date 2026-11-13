{{-- D178 (owner): the person's badges, each in three tiers, with how far the next tier is. $badges: Badges::for(user) --}}
@php
    use App\Support\Bn;
    $tierStyle = [0 => 'border-rule bg-paper text-ink2', 1 => 'border-navy bg-peach-deep text-navy', 2 => 'border-navy bg-sky-tint text-navy', 3 => 'border-navy bg-peach text-navy'];
    $shape = ['reader' => '<path d="M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2z"/><path d="M4 19V5"/>', 'streak' => '<path d="M12 3c1 4 5 5 5 10a5 5 0 0 1-10 0c0-2 1-3 2-4 0 2 1 3 2 3 0-3-1-5 1-9z"/>',
        'player' => '<rect x="3" y="3" width="8" height="8" rx="1.5"/><rect x="13" y="3" width="8" height="8" rx="1.5"/><rect x="3" y="13" width="8" height="8" rx="1.5"/><rect x="13" y="13" width="8" height="8" rx="1.5"/>',
        'kolom' => '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="M13.5 6.5l4 4"/>', 'writer' => '<path d="M5 3h10l4 4v14H5z"/><path d="M9 12h6M9 16h6M9 8h3"/>',
        'voice' => '<path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.5A8 8 0 1 1 21 12z"/>', 'reviewer' => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4M8.5 11l2 2 3.5-4"/>'];
@endphp
<ul class="mt-3 grid gap-3 sm:grid-cols-2">
    @foreach ($badges as $b)
        <li class="flex items-start gap-3 rounded-2xl border-2 {{ $b['tier'] ? 'border-navy bg-white' : 'border-dashed border-rule bg-paper' }} p-3">
            <span class="flex h-14 w-14 flex-none items-center justify-center rounded-full border-2 {{ $tierStyle[$b['tier']] }}" aria-hidden="true">
                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $shape[$b['key']] !!}</svg></span>
            <span class="min-w-0 flex-1">
                <span class="block text-lg font-extrabold leading-tight">{{ __($b['name']) }}@if ($b['tier']) <span class="ml-1 rounded-full bg-navy px-2 py-0.5 align-middle text-xs text-white">{{ __($b['tierName']) }}</span>@endif</span>
                <span class="block text-sm text-ink2">{{ Bn::num((string) $b['count']) }}{{ str_starts_with($b['unit'], 'টি') ? '' : ' ' }}{{ __($b['unit']) }}</span>
                @if ($b['next'])
                    <span class="mt-1.5 block h-2 overflow-hidden rounded-full bg-rule" aria-hidden="true"><span class="block h-full rounded-full bg-sky-deep" style="width: {{ $b['percent'] }}%"></span></span>
                    <span class="mt-1 block text-sm">{{ __(':tier পেতে আর :n', ['tier' => __($b['nextName']), 'n' => Bn::num((string) ($b['next'] - $b['count']))]) }}</span>
                @else
                    <span class="mt-1 block text-sm font-bold text-red">{{ __('সবচেয়ে উঁচু ধাপ!') }}</span>
                @endif
                @unless ($b['tier'])<span class="block text-sm text-ink2">{{ __($b['how']) }}</span>@endunless
            </span>
        </li>
    @endforeach
</ul>
