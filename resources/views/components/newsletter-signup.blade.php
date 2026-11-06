@props(['variant' => 'card'])
{{-- Newsletter sign-up (D58, D74): double opt-in, hidden spam trap, invisible Turnstile; signed-in readers see their status. --}}
@php
    $id = 'newsletter-'.$variant;
    $mine = session('newsletter_from') === $variant;
    $email = old('email', auth()->user()?->email);
@endphp
@if ($variant === 'footer')
    <form id="{{ $id }}" method="POST" action="{{ route('newsletter.subscribe') }}" class="w-full max-w-[420px] scroll-mt-24">
        @csrf
        <input type="hidden" name="from" value="footer">
        <label for="{{ $id }}-email" class="block font-bold text-paper">সপ্তাহে একটি চিঠি: নতুন লেখার খবর, ই-মেলে</label>
        <div class="mt-2 flex gap-2">
            <input id="{{ $id }}-email" name="email" type="email" required maxlength="190" autocomplete="email" value="{{ $mine ? $email : auth()->user()?->email }}" placeholder="আপনার ই-মেল"
                class="h-12 min-w-0 flex-1 rounded-full border-2 border-paper bg-white px-4 text-navy">
            <button class="h-12 flex-none rounded-full bg-red px-5 font-bold text-white">চাই</button>
        </div>
        <div class="hidden" aria-hidden="true"><label>Website <input name="website" tabindex="-1" autocomplete="off"></label></div>
        <x-turnstile action="newsletter" class="mt-2" theme="dark" :lazy="true" />
        @if ($mine && session('newsletter'))<p role="status" class="mt-2 text-sm text-peach-light">{{ session('newsletter') }}</p>@endif
        @if ($mine)@error('email')<p class="mt-2 text-sm text-peach-light">{{ $message }}</p>@enderror @endif
        <p class="mt-2 text-xs text-peach-light">যখন খুশি বন্ধ করা যায়। <a href="{{ \App\Support\Url::to('/privacy-policy') }}" class="underline">গোপনীয়তা</a></p>
    </form>
@elseif (auth()->check())
    {{-- D74: signed-in readers already get the letter (D59); just say so, with the switch. --}}
    @php($on = ! auth()->user()->mail_opt_out)
    <div id="{{ $id }}" class="mt-10 flex flex-wrap items-center gap-3 rounded-2xl bg-sky-tint px-5 py-4">
        <span class="text-2xl" aria-hidden="true">✉</span>
        <p class="min-w-0 flex-1 text-ink2">
            @if ($on)<strong class="text-navy">হাতপাখার চিঠি আপনি পাচ্ছেন।</strong> সপ্তাহে একবার, নতুন লেখার খবর।
            @else<strong class="text-navy">হাতপাখার চিঠি বন্ধ আছে।</strong> সপ্তাহে একবার নতুন লেখার খবর পেতে চালু করুন।@endif
        </p>
        @if ($on)
            <a href="{{ \App\Support\Url::to('/account') }}#letters" class="text-sm font-bold underline">বদলান</a>
        @else
            <form method="POST" action="{{ \App\Support\Url::to('/account/newsletter') }}">@csrf<input type="hidden" name="newsletter" value="1">
                <button class="h-10 rounded-full bg-red px-5 font-bold text-white">চালু করুন</button></form>
        @endif
    </div>
@else
    <section id="{{ $id }}" class="mt-10 scroll-mt-24 overflow-hidden rounded-3xl bg-sky-tint" aria-labelledby="{{ $id }}-h">
        <div class="grid gap-5 p-6 md:grid-cols-[1fr_1.1fr] md:items-center md:p-8">
            <div>
                <div class="font-hand text-2xl text-red">হাতপাখার চিঠি</div>
                <h2 id="{{ $id }}-h" class="mt-1 font-display text-2xl font-extrabold leading-tight md:text-3xl">ভালো লাগলে, পরের লেখাগুলোও ই-মেলে পান</h2>
                <p class="mt-2 text-ink2">সপ্তাহে একটি চিঠি, নতুন গল্প-কবিতার খবর। বিজ্ঞাপন নয়, যখন খুশি বন্ধ।</p>
            </div>
            @if ($mine && session('newsletter'))
                <p role="status" class="rounded-2xl border-2 border-sky bg-white p-4 font-semibold text-sky-night">✓ {{ session('newsletter') }}</p>
            @else
                <form method="POST" action="{{ route('newsletter.subscribe') }}" class="rounded-2xl bg-white p-4 shadow-[4px_4px_0_#13212C] ring-2 ring-navy">
                    @csrf
                    <input type="hidden" name="from" value="{{ $variant }}">
                    <label class="block text-sm font-bold" for="{{ $id }}-email">আপনার ই-মেল</label>
                    <div class="mt-1 flex flex-col gap-2 sm:flex-row">
                        <input id="{{ $id }}-email" name="email" type="email" required maxlength="190" autocomplete="email" value="{{ $email }}" placeholder="name@example.com"
                            class="h-12 min-w-0 flex-1 rounded-xl border-2 border-navy bg-white px-4">
                        <button class="h-12 flex-none rounded-full bg-red px-6 font-bold text-white">চিঠি চাই</button>
                    </div>
                    <details class="mt-2 text-sm">
                        <summary class="cursor-pointer text-ink2">নাম দিতে চাইলে</summary>
                        <label class="sr-only" for="{{ $id }}-name">নাম (ঐচ্ছিক)</label>
                        <input id="{{ $id }}-name" name="name" maxlength="80" autocomplete="name" value="{{ old('name') }}" placeholder="নাম (ঐচ্ছিক)"
                            class="mt-2 h-11 w-full rounded-xl border-2 border-navy bg-white px-4">
                    </details>
                    <div class="hidden" aria-hidden="true"><label>Website <input name="website" tabindex="-1" autocomplete="off"></label></div>
                    <x-turnstile action="newsletter" class="mt-2" :lazy="true" appearance="interaction-only" />
                    @if ($mine)@error('email')<p class="mt-2 font-semibold text-red" role="alert">{{ $message }}</p>@enderror @endif
                    <p class="mt-2 text-xs text-ink2">একটি নিশ্চিত করার লিংক যাবে; সেটায় চাপলেই চালু। ই-মেল কাউকে দেওয়া হয় না (<a href="{{ \App\Support\Url::to('/privacy-policy') }}" class="underline">গোপনীয়তা</a>)।</p>
                </form>
            @endif
        </div>
    </section>
@endif
