{{-- Account home (owner + UX review): only what needs the writer now, a few short previews, one line of numbers. --}}
@php
    use App\Support\Bn;
    use App\Support\Url;
    $S = \App\Http\Controllers\AccountController::STATUS;
    $published = (int) ($counts['published'] ?? 0);
    $inReview = (int) ($counts['pending'] ?? 0) + (int) ($counts['scheduled'] ?? 0);
    $total = (int) $counts->sum();
@endphp
<x-layouts.app :seo="$seo">
    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
        // Old links to the one-page account (#profile, #security, #letters, #reading, #writing) still land in the right place.
        (function () { var h = location.hash.slice(1), m = { profile: 'profile/', security: 'settings/#security', letters: 'settings/#letters', reading: 'reading/', writing: 'writings/' };
            if (m[h]) location.replace('{{ Url::to('/account') }}' + m[h]); })();
    </script>
    <x-account-shell tab="home" :user="$user" :author="$author" :counts="$counts" :two-factor-on="$twoFactorOn" :cert-count="$certCount">
        @if ($needsVerification)
            <div class="mb-5 rounded-xl border-2 border-red bg-peach-light p-4 font-semibold">জমা দেওয়ার আগে ইমেল যাচাই করুন। আপনার ইনবক্সে লিংক আছে।</div>
        @endif

        @if ($total === 0)
            <div class="rounded-3xl border-2 border-dashed border-navy p-8 text-center">
                <p class="font-serif text-xl">এখনও কোনো লেখা পাঠাননি।</p>
                <p class="mt-2 text-ink2">প্রথম লেখাটি পাঠানোর আগে <a href="{{ Url::to('/submissions') }}" class="underline">লেখা পাঠানোর নিয়ম</a> একবার দেখে নিন।</p>
            </div>
        @else
            {{-- One line of numbers; each part opens its list --}}
            @php
                $nums = array_filter([
                    [$published, 'প্রকাশিত', 'published'], [$inReview, 'সম্পাদকের কাছে', 'review'], [$certCount, 'শংসাপত্র', 'certificates'],
                ], fn ($x) => $x[0] > 0);
            @endphp
            <p class="flex flex-wrap gap-x-3 gap-y-1 text-sm font-bold">
                @foreach ($nums as [$v, $label, $key])
                    @if (! $loop->first)<span aria-hidden="true" class="text-ink2">·</span>@endif
                    <a href="{{ Url::to('/account/writings') }}?status={{ $key }}" class="underline decoration-rule underline-offset-4 hover:text-red">{{ Bn::num((string) $v) }} {{ $label }}</a>
                @endforeach
            </p>
        @endif

        {{-- Your turn --}}
        @if ($act->isNotEmpty() || $drafts->isNotEmpty())
            <h2 class="mt-7 font-display text-2xl font-extrabold">আপনার পালা</h2>
            <ul class="mt-3 space-y-3">
                @foreach ($act as $p)
                    <li class="rounded-2xl border-2 border-red bg-peach-light p-4">
                        <div class="flex items-start gap-3">
                            <div class="min-w-0 flex-1">
                                <div class="font-serif text-lg font-bold leading-snug">{{ $p->title }}</div>
                                <div class="text-sm text-ink2">{{ $S[$p->status][1] ?? '' }}</div>
                            </div>
                            <a href="{{ Url::to('/submit/'.$p->id) }}" class="flex h-11 flex-none items-center rounded-full bg-red px-4 font-bold text-white">{{ ['changes' => 'ঠিক করুন', 'confirm' => 'দেখে সম্মতি দিন'][$p->status] }}</a>
                        </div>
                        @if ($p->status === 'changes' && $p->review_note)
                            <div class="mt-3 rounded-xl bg-white p-3 text-sm"><strong>সম্পাদকের মন্তব্য:</strong> {{ $p->review_note }}</div>
                        @endif
                    </li>
                @endforeach
                @foreach ($drafts as $p)
                    <li>
                        <a href="{{ Url::to('/submit/'.$p->id) }}" class="flex items-center gap-3 rounded-2xl border-2 border-rule bg-white p-4 hover:border-navy">
                            <span class="min-w-0 flex-1"><span class="block font-serif text-lg font-bold leading-snug">{{ $p->title ?: 'শিরোনামহীন খসড়া' }}</span>
                                <span class="text-sm text-ink2">খসড়া · {{ Bn::date($p->updated_at) }}</span></span>
                            <span class="flex-none font-bold text-red">লিখতে থাকুন →</span>
                        </a>
                    </li>
                @endforeach
            </ul>
            @if ($draftCount > $drafts->count())
                <a href="{{ Url::to('/account/writings') }}?status=draft" class="mt-3 inline-flex min-h-11 items-center font-bold text-sky-deep underline">সব খসড়া ({{ Bn::num((string) $draftCount) }}) →</a>
            @endif
        @endif

        {{-- With the editor: the only place the four-step tracker means something --}}
        @if ($review->isNotEmpty())
            <h2 class="mt-8 font-display text-2xl font-extrabold">সম্পাদকের কাছে</h2>
            <ul class="mt-3 divide-y divide-rule rounded-2xl border-2 border-navy bg-white">
                @foreach ($review as $p)
                    @php $step = ['pending' => 2, 'scheduled' => 3][$p->status] ?? 1; @endphp
                    <li class="p-4">
                        <div class="font-serif text-lg font-bold leading-snug">{{ $p->title }}</div>
                        <ol class="mt-2 flex flex-wrap items-center gap-1 text-xs font-bold" aria-label="অবস্থা">
                            @foreach (['জমা', 'পড়া হচ্ছে', 'মনোনীত', 'প্রকাশিত'] as $i => $stage)
                                <li class="flex items-center gap-1 {{ $i + 1 <= $step ? 'text-sky-night' : 'text-ink2 opacity-60' }}" @if ($i + 1 === $step) aria-current="step" @endif>
                                    <span class="inline-block h-2.5 w-2.5 rounded-full {{ $i + 1 <= $step ? 'bg-sky-deep' : 'bg-rule' }}"></span>{{ $stage }}
                                    @if (! $loop->last)<span class="mx-0.5 opacity-50">—</span>@endif
                                </li>
                            @endforeach
                        </ol>
                    </li>
                @endforeach
            </ul>
            @if ($inReview > $review->count())
                <a href="{{ Url::to('/account/writings') }}?status=review" class="mt-3 inline-flex min-h-11 items-center font-bold text-sky-deep underline">সব ({{ Bn::num((string) $inReview) }}) →</a>
            @endif
        @endif

        {{-- Recently published: three rows, then the full list --}}
        @if ($recent->isNotEmpty())
            <h2 class="mt-8 font-display text-2xl font-extrabold">সাম্প্রতিক প্রকাশিত</h2>
            <ul class="mt-2 divide-y divide-rule border-y border-rule">
                @foreach ($recent as $p)
                    <li><a href="{{ $p->url() }}" class="flex min-h-14 items-center gap-3 py-2 hover:text-red">
                        <span class="min-w-0 flex-1"><span class="block truncate font-serif text-lg font-bold">{{ $p->title }}</span>
                            <span class="text-sm text-ink2">{{ $p->section?->name_bn }} · {{ Bn::date($p->published_at) }}</span></span>
                        <span aria-hidden="true">→</span></a></li>
                @endforeach
            </ul>
            <a href="{{ Url::to('/account/writings') }}" class="mt-3 inline-flex min-h-11 items-center font-bold text-sky-deep underline">সব লেখা ({{ Bn::num((string) $total) }}) →</a>
        @endif

        @if (! $twoFactorOn)
            {{-- D59: 2FA is optional, asked kindly, in one line --}}
            <a href="{{ Url::to('/account/settings') }}#security" class="mt-8 flex min-h-12 items-center gap-3 rounded-2xl border-2 border-dashed border-peach-deep bg-peach-light px-4 py-3 text-sm hover:bg-peach">
                <svg class="h-6 w-6 flex-none text-navy" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                <span><strong>অ্যাকাউন্টটি আরও নিরাপদ করুন:</strong> দুই-ধাপের যাচাই, দুই মিনিট লাগে।</span>
                <span class="ml-auto font-bold text-red" aria-hidden="true">→</span>
            </a>
        @endif

        <form method="POST" action="{{ Url::to('/logout') }}" class="mt-10 border-t-2 border-rule pt-6 text-center">@csrf<button class="min-h-11 font-bold text-red underline">লগ-আউট</button></form>
    </x-account-shell>
</x-layouts.app>
