{{-- D166 "আমার লেখা" (a writer's first page; owner + UX review + tests with older users): what needs the writer first,
     drafts, pieces with the editor, published pieces with plain actions, reader numbers, the writer page and its link. --}}
@php
    use App\Support\Bn;
    use App\Support\Url;
    $S = \App\Http\Controllers\AccountController::STATUS;
    $chip = 'inline-flex min-h-11 items-center justify-center rounded-full border-2 border-navy bg-white px-4 text-[15px] font-bold hover:bg-peach-light';
    $wa = fn (string $text) => 'https://wa.me/?text='.rawurlencode($text);
@endphp
<x-layouts.app :seo="$seo">
    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
        // Old links to the one-page account (#profile, #security, #letters, #reading, #writing) still land in the right place.
        (function () { var h = location.hash.slice(1), m = { profile: 'profile/', security: 'settings/#security', letters: 'settings/#letters', reading: 'reading/', writing: 'writings/' };
            if (m[h]) location.replace('{{ Url::to('/account') }}' + m[h]); })();
    </script>
    <x-account-shell tab="home" :user="$user" :author="$author" :counts="$counts" :two-factor-on="$twoFactorOn" :cert-count="$certCount">
        @if ($needsVerification)
            <div class="mb-5 rounded-xl border-2 border-red bg-peach-light p-4 font-semibold">{{ __('লেখা পাঠানোর আগে ই-মেল ভেরিফাই করুন। আপনার ইনবক্সে লিংক আছে।') }}</div>
        @endif
        <a href="{{ Url::to('/submit') }}" class="flex h-14 w-full items-center justify-center rounded-full bg-red text-lg font-bold text-white shadow-[3px_3px_0_#13212C] sm:w-auto sm:px-10">+ {{ __('নতুন লেখা পাঠান') }}</a>

        @if ($act->isNotEmpty())
            <h2 class="mt-8 font-display text-2xl font-extrabold">{{ __('আপনাকে দেখতে হবে') }}</h2>
            @foreach ($act as $p)
                <div class="mt-3 rounded-2xl border-2 border-red bg-peach-light p-4 shadow-[4px_4px_0_#CB1B32]">
                    <div class="font-serif text-lg font-bold leading-snug">{{ $p->title }}</div>
                    <div class="text-ink2">{{ $p->status === 'changes' ? __('সম্পাদক কিছু বদল চেয়েছেন') : __('সম্পাদক কয়েকটি শব্দ বদলাতে চান: দেখে সম্মতি দিন') }}</div>
                    @if ($p->status === 'changes' && $p->review_note)
                        <div class="mt-3 whitespace-pre-line rounded-xl bg-white p-3 leading-relaxed"><strong>{{ __('সম্পাদকের কথা:') }}</strong> {{ $p->review_note }}</div>
                    @endif
                    <a href="{{ Url::to('/submit/'.$p->id) }}" class="mt-3 flex h-12 items-center justify-center rounded-full bg-red font-bold text-white">{{ $p->status === 'changes' ? __('লেখাটি খুলে ঠিক করুন') : __('দেখে সম্মতি দিন') }}</a>
                    @if ($p->status === 'changes')
                        <p class="mt-2 text-sm text-ink2">{{ __('ঠিক করে নিচের “আবার পাঠান” বোতাম টিপুন। চাইলে কিছু না বদলেও আবার পাঠাতে পারেন; সম্পাদক আবার মন দিয়ে পড়বেন।') }}</p>
                    @endif
                </div>
            @endforeach
        @endif

        @if ($drafts->isNotEmpty())
            <h2 class="mt-8 font-display text-2xl font-extrabold">{{ __('লিখতে থাকুন') }}</h2>
            @foreach ($drafts as $p)
                <div class="mt-3 rounded-2xl border-2 border-navy bg-white p-4">
                    <div class="font-serif text-lg font-bold leading-snug">{{ $p->title ?: __('শিরোনাম ছাড়া খসড়া') }}</div>
                    <div class="text-ink2">{{ __('খসড়া, শেষ লিখেছেন :date', ['date' => Bn::date($p->updated_at)]) }}</div>
                    <a href="{{ Url::to('/submit/'.$p->id) }}" class="mt-3 flex h-12 items-center justify-center rounded-full border-2 border-navy font-bold hover:bg-peach-light">{{ __('খুলে লিখতে থাকুন') }}</a>
                </div>
            @endforeach
            @if ($draftCount > $drafts->count())
                <a href="{{ Url::to('/account/writings') }}?status=draft" class="mt-3 inline-flex min-h-11 items-center font-bold text-sky-night underline">{{ __('সব খসড়া (:count) →', ['count' => Bn::num((string) $draftCount)]) }}</a>
            @endif
        @endif

        @if ($review->isNotEmpty())
            <h2 class="mt-8 font-display text-2xl font-extrabold">{{ __('সম্পাদকের কাছে') }}</h2>
            @foreach ($review as $p)
                @php($step = ['pending' => 2, 'scheduled' => 3][$p->status] ?? 1)
                <div class="mt-3 rounded-2xl border-2 border-navy bg-white p-4">
                    <div class="font-serif text-lg font-bold leading-snug">{{ $p->title }}</div>
                    <ol class="mt-2 flex flex-wrap items-center gap-x-1 gap-y-1 text-sm font-bold" aria-label="{{ __('অবস্থা') }}">
                        @foreach (['পাঠানো', 'পড়া হচ্ছে', 'বাছাই হয়েছে', 'প্রকাশিত'] as $i => $stage)
                            <li class="flex items-center gap-1 {{ $i + 1 <= $step ? 'text-sky-night' : 'font-medium text-ink2' }}" @if ($i + 1 === $step) aria-current="step" @endif>
                                <span class="inline-block h-3 w-3 rounded-full {{ $i + 1 <= $step ? 'bg-sky-deep' : 'bg-rule' }}"></span>{{ __($stage) }}@if (! $loop->last)<span class="mx-0.5 text-ink2">—</span>@endif
                            </li>
                        @endforeach
                    </ol>
                    <p class="mt-2 text-sm text-ink2">{{ $p->status === 'scheduled' ? __('প্রকাশের দিন ঠিক হয়েছে: :date', ['date' => Bn::date($p->published_at)]) : __('সিদ্ধান্ত হলে ই-মেলে জানাব।') }}</p>
                </div>
            @endforeach
        @endif

        @if ($published->isNotEmpty())
            <div class="mt-8 flex items-baseline justify-between gap-3">
                <h2 class="font-display text-2xl font-extrabold">{{ __('প্রকাশিত লেখা') }}</h2>
                <a href="{{ Url::to('/account/writings') }}?status=published" class="inline-flex min-h-11 items-center font-bold text-sky-night underline">{{ __('সব দেখুন') }}</a>
            </div>
            @foreach ($published as $p)
                @php($n = (int) ($reads[$p->id] ?? 0))
                <div class="mt-3 rounded-2xl border-2 border-navy bg-white p-4">
                    <div class="font-serif text-lg font-bold leading-snug">{{ $p->title }}</div>
                    <div class="text-ink2">{{ $p->section?->name_bn }} · {{ Bn::date($p->published_at) }} · <strong class="text-navy">{{ __(':count বার খোলা হয়েছে', ['count' => Bn::num((string) $n)]) }}</strong></div>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <a href="{{ $wa(__('“:title”, হাতপাখায় আমার লেখা: :url', ['title' => $p->title, 'url' => $p->url()])) }}" target="_blank" rel="noopener" class="{{ $chip }}">{{ __('WhatsApp-এ পাঠান') }}</a>
                        @if ($c = $certs->get($p->id))
                            <a href="{{ $c->verifyUrl() }}" class="{{ $chip }}">{{ __('সার্টিফিকেট') }}</a>
                        @else
                            <form method="POST" action="{{ Url::to('/account/certificate/'.$p->id) }}">@csrf<button class="{{ $chip }}">{{ __('সার্টিফিকেট') }}</button></form>
                        @endif
                        <a href="{{ $p->url() }}" class="{{ $chip }}">{{ __('পড়ে দেখুন') }}</a>
                    </div>
                </div>
            @endforeach
            <p class="mt-3 text-sm text-ink2">{{ __('“সার্টিফিকেট”: প্রতিটি প্রকাশিত লেখার জন্য হাতপাখার একটি ফ্রি সার্টিফিকেট। ডাউনলোড করে বা স্ক্রিনশট নিয়ে WhatsApp বা ফেসবুকে শেয়ার করতে পারেন।') }}</p>

            <div class="mt-8 rounded-2xl border-2 border-navy bg-peach-light p-4">
                <div class="flex items-baseline justify-between gap-3"><h2 class="font-display text-2xl font-extrabold">{{ __('আমার পাঠক') }}</h2><a href="{{ Url::to('/account/stats') }}" class="inline-flex min-h-11 items-center font-bold text-sky-night underline">{{ __('বিস্তারিত') }}</a></div>
                <div class="mt-3 grid grid-cols-2 gap-3">
                    <div class="rounded-xl bg-white p-3"><div class="text-3xl font-extrabold">{{ Bn::num((string) $stats['reads']) }}</div><div class="text-sm text-ink2">{{ __('বার খোলা হয়েছে (সব লেখা মিলিয়ে)') }}</div></div>
                    <div class="rounded-xl bg-white p-3"><div class="text-3xl font-extrabold">{{ Bn::num((string) $stats['likes']) }}</div><div class="text-sm text-ink2">{{ __('টি লাইক') }}</div></div>
                </div>
            </div>
        @endif

        @if ($writerPage)
            <h2 class="mt-8 font-display text-2xl font-extrabold">{{ __('আমার লেখক-পাতা') }}</h2>
            <p class="text-ink2">{{ __('পাঠকেরা এখানে আপনার সব লেখা পান। WhatsApp বা ফেসবুকে শেয়ার করুন।') }}</p>
            <div class="mt-2 flex items-center gap-2 rounded-xl border-2 border-navy bg-white py-1.5 pl-3 pr-1.5">
                <span class="min-w-0 flex-1 truncate text-sm text-ink2" data-copy-source>{{ $writerPage }}</span>
                <button type="button" data-copy="{{ $writerPage }}" data-copied="{{ __('✓ কপি হয়েছে') }}" class="flex h-11 flex-none items-center rounded-full bg-navy px-4 text-sm font-bold text-white">{{ __('লিংক কপি') }}</button>
            </div>
            <div class="mt-3 flex flex-wrap gap-2">
                <a href="{{ $wa(__('হাতপাখায় আমার লেখাগুলো: :url', ['url' => $writerPage])) }}" target="_blank" rel="noopener" class="{{ $chip }}">{{ __('WhatsApp-এ পাঠান') }}</a>
                <a href="https://www.facebook.com/sharer/sharer.php?u={{ rawurlencode($writerPage) }}" target="_blank" rel="noopener" class="{{ $chip }}">{{ __('ফেসবুকে শেয়ার') }}</a>
                <a href="{{ $writerPage }}" class="{{ $chip }}">{{ __('পাতাটি দেখুন') }}</a>
                <a href="{{ Url::to('/account/profile') }}" class="{{ $chip }}">{{ __('ছবি ও নাম বদলান') }}</a>
            </div>
        @endif
    </x-account-shell>
</x-layouts.app>
