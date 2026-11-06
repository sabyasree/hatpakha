@props(['variant' => 'card'])
{{-- Newsletter sign-up (D58): double opt-in, hidden spam trap, Turnstile when switched on. --}}
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
@else
    <section id="{{ $id }}" class="mt-10 scroll-mt-24 rounded-3xl border-2 border-navy bg-sky-tint p-6" aria-labelledby="{{ $id }}-h">
        <div class="font-hand text-2xl text-red">হাতপাখার চিঠি</div>
        <h2 id="{{ $id }}-h" class="font-display text-2xl font-extrabold">ভালো লাগলে, পরের লেখাগুলোও ই-মেলে পান</h2>
        <p class="mt-1 text-ink2">সপ্তাহে একটি চিঠি, নতুন গল্প-কবিতার খবর। বিজ্ঞাপন নয়, যখন খুশি বন্ধ করা যায়।</p>
        @if ($mine && session('newsletter'))
            <p role="status" class="mt-4 rounded-xl border-2 border-sky bg-white p-3 font-semibold text-sky-night">{{ session('newsletter') }}</p>
        @else
            <form method="POST" action="{{ route('newsletter.subscribe') }}" class="mt-4 grid gap-3 sm:grid-cols-[1fr_1fr_auto]">
                @csrf
                <input type="hidden" name="from" value="{{ $variant }}">
                <label class="sr-only" for="{{ $id }}-email">ই-মেল</label>
                <input id="{{ $id }}-email" name="email" type="email" required maxlength="190" autocomplete="email" value="{{ $email }}" placeholder="আপনার ই-মেল"
                    class="h-12 rounded-xl border-2 border-navy bg-white px-4">
                <label class="sr-only" for="{{ $id }}-name">নাম (ঐচ্ছিক)</label>
                <input id="{{ $id }}-name" name="name" maxlength="80" autocomplete="name" value="{{ old('name') }}" placeholder="নাম (ঐচ্ছিক)"
                    class="h-12 rounded-xl border-2 border-navy bg-white px-4">
                <button class="h-12 rounded-full bg-red px-6 font-bold text-white">চিঠি চাই</button>
                <div class="hidden" aria-hidden="true"><label>Website <input name="website" tabindex="-1" autocomplete="off"></label></div>
                <x-turnstile action="newsletter" class="sm:col-span-3" />
            </form>
            @if ($mine)@error('email')<p class="mt-2 font-semibold text-red">{{ $message }}</p>@enderror @endif
            <p class="mt-3 text-sm text-ink2">একটি নিশ্চিত করার লিংক যাবে; সেটায় চাপলেই চালু। আপনার ই-মেল কাউকে দেওয়া হয় না (<a href="{{ \App\Support\Url::to('/privacy-policy') }}" class="underline">গোপনীয়তা নীতি</a>)।</p>
        @endif
    </section>
@endif
