{{-- All writings (owner + UX review): filter chips with counts, title search, compact rows, a ⋯ menu per piece, 20 at a time. --}}
@php
    use App\Support\Bn;
    use App\Support\Url;
    $S = \App\Http\Controllers\AccountController::STATUS;
    $n = fn ($statuses) => (int) collect($statuses)->sum(fn ($s) => $counts[$s] ?? 0);
    $chips = [
        'all' => ['সব', (int) $counts->sum()],
        'action' => ['ঠিক করতে হবে', $n($groups['action'])],
        'draft' => ['খসড়া', $n($groups['draft'])],
        'review' => ['সম্পাদকের কাছে', $n($groups['review'])],
        'published' => ['প্রকাশিত', $n($groups['published'])],
        'certificates' => ['শংসাপত্র', $certCount],
        'other' => ['অন্যান্য', $n($groups['other'])],
    ];
    $base = Url::to('/account/writings');
@endphp
<x-layouts.app :seo="$seo">
    <x-account-shell tab="writings" :user="$user" :author="$author" :counts="$counts" :two-factor-on="$twoFactorOn" :cert-count="$certCount">
        <div class="flex flex-wrap gap-2" role="list" aria-label="লেখা বাছুন">
            @foreach ($chips as $key => [$label, $count])
                @continue($count === 0 && ! in_array($key, ['all', $filter], true))
                <a role="listitem" href="{{ $base }}{{ $key === 'all' ? '' : '?status='.$key }}" @if ($filter === $key) aria-current="true" @endif
                    class="flex h-11 items-center gap-1.5 rounded-full border-2 px-4 font-bold {{ $filter === $key ? 'border-navy bg-navy text-white' : ($key === 'action' ? 'border-red bg-peach-light text-red-dark' : 'border-navy bg-white hover:bg-peach-light') }}">
                    {{ $label }} <span class="{{ $filter === $key ? 'text-peach-light' : 'text-ink2' }}">{{ Bn::num((string) $count) }}</span></a>
            @endforeach
        </div>

        @if ($filter !== 'certificates' && $counts->sum() > 10)
            <form method="GET" action="{{ $base }}" role="search" class="mt-4 flex gap-2">
                @if ($filter !== 'all')<input type="hidden" name="status" value="{{ $filter }}">@endif
                <label for="wq" class="sr-only">লেখার নাম দিয়ে খুঁজুন</label>
                <input id="wq" name="q" type="search" value="{{ $q }}" maxlength="80" placeholder="লেখার নাম দিয়ে খুঁজুন" class="h-12 min-w-0 flex-1 rounded-full border-2 border-navy bg-white px-5">
                <button class="h-12 flex-none rounded-full border-2 border-navy px-5 font-bold">খুঁজুন</button>
            </form>
        @endif

        @if ($filter === 'certificates')
            <ul class="mt-5 grid gap-3 sm:grid-cols-2">
                @forelse ($certificates as $c)
                    <li><a href="{{ $c->verifyUrl() }}" class="flex min-h-16 items-center gap-3 rounded-2xl border-2 border-navy bg-white p-4 hover:bg-peach-light">
                        <svg class="h-8 w-8 flex-none text-red" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="9" r="6"/><path d="m8.5 14-1.5 7 5-3 5 3-1.5-7"/></svg>
                        <span class="min-w-0"><span class="block font-serif font-bold">{{ $c->title_snapshot }}</span>
                            <span class="text-sm text-ink2">{{ \App\Models\Certificate::THEMES[$c->theme()][0] }} · {{ Bn::date($c->issued_at) }} · দেখুন, নামান, প্রিন্ট</span></span></a></li>
                @empty
                    <li class="text-ink2">এখনও কোনো শংসাপত্র নেই। প্রকাশিত লেখার ⋯ থেকে চাইতে পারেন।</li>
                @endforelse
            </ul>
        @else
            @if ($q !== '')<p class="mt-4 text-sm text-ink2">"{{ $q }}" — {{ Bn::num((string) $posts->total()) }}টি লেখা · <a href="{{ $base }}{{ $filter !== 'all' ? '?status='.$filter : '' }}" class="font-bold underline">সব দেখুন</a></p>@endif
            <ul class="mt-5 divide-y divide-rule rounded-2xl border-2 border-navy bg-white" data-list>
                @forelse ($posts as $p)
                    @include('account.partials.writing-row', ['p' => $p])
                @empty
                    <li class="p-6 text-center text-ink2">এখানে কিছু নেই।</li>
                @endforelse
            </ul>
            @if ($posts->hasMorePages())
                <a href="{{ $posts->nextPageUrl() }}" data-more-rows class="mt-4 flex h-12 w-full items-center justify-center rounded-full border-2 border-navy bg-white font-bold hover:bg-peach-light">
                    আরও দেখুন ({{ Bn::num((string) ($posts->total() - $posts->currentPage() * $posts->perPage())) }}টি বাকি)</a>
            @endif
        @endif
    </x-account-shell>
</x-layouts.app>
