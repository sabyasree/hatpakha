<x-layouts.app :seo="$seo">
    <section class="mx-auto max-w-[900px] px-5 pb-16 pt-10">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <div class="font-hand text-2xl text-red">নমস্কার, {{ $user->name }}</div>
                <h1 class="font-display text-4xl font-extrabold md:text-5xl">আমার লেখা</h1>
            </div>
            <a href="{{ \App\Support\Url::to('/submit') }}" class="flex h-12 items-center rounded-full bg-red px-6 font-bold text-white">+ নতুন লেখা পাঠান</a>
        </div>

        @if (session('status'))
            <div role="status" class="mt-5 rounded-xl border-2 border-sky bg-sky-tint p-4 font-semibold text-sky-night">{{ session('status') }}</div>
        @endif
        @if ($needsVerification)
            <div class="mt-5 rounded-xl border-2 border-red bg-peach-light p-4">
                <p class="font-semibold">লেখা জমা দেওয়ার আগে ইমেল যাচাই করুন।</p>
                <form method="POST" action="{{ \App\Support\Url::to('/email/verification-notification') }}" class="mt-2">@csrf<button class="font-bold text-red underline">যাচাইয়ের লিংক পাঠান</button></form>
            </div>
        @endif

        @if ($posts->isEmpty())
            <div class="mt-10 rounded-2xl border-2 border-dashed border-navy p-8 text-center">
                <p class="font-serif text-xl">এখনও কোনও লেখা নেই।</p>
                <p class="mt-2 text-ink2">প্রথম লেখাটি পাঠানোর আগে <a href="{{ \App\Support\Url::to('/submissions') }}" class="underline">লেখা পাঠানোর নিয়ম</a> একবার দেখে নিন।</p>
            </div>
        @else
            <ul class="mt-8 divide-y divide-rule border-y-2 border-navy">
                @foreach ($posts as $p)
                    @php([$label, $hint] = \App\Http\Controllers\AccountController::STATUS[$p->status] ?? [$p->status, ''])
                    <li class="flex flex-wrap items-center justify-between gap-3 py-4">
                        <div class="min-w-0">
                            <div class="font-serif text-lg font-bold leading-snug">{{ $p->title }}</div>
                            <div class="text-sm text-ink2">{{ $p->section?->name_bn }} · {{ \App\Support\Bn::date($p->submitted_at ?? $p->updated_at) }}@if ($hint) · {{ $hint }}@endif</div>
                            @if ($p->status === 'changes' && $p->review_note)
                                <div class="mt-2 rounded-lg bg-peach-light p-3 text-sm"><strong>সম্পাদকের মন্তব্য:</strong> {{ $p->review_note }}</div>
                            @endif
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="rounded-full px-3 py-1 text-sm font-bold {{ in_array($p->status, ['published', 'scheduled']) ? 'bg-sky-tint text-sky-night' : ($p->status === 'changes' ? 'bg-red text-white' : 'bg-peach-light') }}">{{ $label }}</span>
                            @if (in_array($p->status, ['draft', 'changes']))
                                <a href="{{ \App\Support\Url::to('/submit/'.$p->id) }}" class="font-bold text-red underline">খুলুন</a>
                            @elseif ($p->status === 'published')
                                <a href="{{ $p->url() }}" class="font-bold text-sky-deep underline">দেখুন</a>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif

        <details class="mt-10 rounded-xl border-2 border-rule p-4">
            <summary class="cursor-pointer font-bold">অ্যাকাউন্ট</summary>
            <form method="POST" action="{{ \App\Support\Url::to('/account/name') }}" class="mt-4 max-w-[420px]">
                @csrf
                <x-field name="name" label="ছাপার নাম" :value="$user->name" maxlength="80" required />
                <button class="h-11 rounded-full border-2 border-navy px-5 font-bold">নাম বদলান</button>
            </form>
            <p class="mt-4 text-sm text-ink2">ইমেল: {{ $user->email }}</p>
            <form method="POST" action="{{ \App\Support\Url::to('/logout') }}" class="mt-4">@csrf<button class="font-bold text-red underline">লগ-আউট</button></form>
        </details>
    </section>
</x-layouts.app>
