{{-- D266: পাঠকের কলম, the week's invitation with the form always open, the thank-you card, or the closed notice.
     Placed above the picks mid-week, below them while the picks are fresh (Sunday, Monday). --}}
@php
    use App\Models\ReaderPrompt;
    use App\Support\Bn;
    use App\Support\Url;
    $sent = session('kolom_sent');
    $in = 'mt-1 block w-full rounded-xl border-2 border-navy bg-white p-3 text-lg';
    $deadline = $current?->closes_at?->copy()->setTimezone('Asia/Kolkata');
    $limit = $current?->limit();
@endphp
@if ($open && ! $prompt)
    <section id="form" class="relative mt-8 scroll-mt-24 rounded-3xl border-2 border-navy bg-peach-light p-5 shadow-[4px_4px_0_#13212C]" aria-labelledby="kolom-theme">
        <span class="absolute -top-3 right-4 rotate-3 rounded-md border-2 border-dashed border-paper bg-peach-deep px-3 py-1 text-sm font-extrabold text-navy outline outline-2 outline-peach-deep" aria-hidden="true">✎ {{ ReaderPrompt::KINDS[$current->kind] }}</span>
        <p class="font-hand text-2xl text-red">এ সপ্তাহের বিষয়</p>
        <h2 id="kolom-theme" class="mt-1 font-serif text-4xl font-extrabold leading-tight sm:text-5xl">{{ $current->title }}</h2>
        <p class="mt-2 font-serif text-xl leading-relaxed">{{ $current->ask }}</p>
        @if ($deadline)
            <p class="mt-4 inline-flex flex-wrap items-center gap-x-1 rounded-2xl bg-navy px-4 py-2 font-bold text-white">
                <span>জমার শেষ সময়:</span> <span>{{ Bn::date($deadline) }}, সন্ধ্যা ৬টা</span> <span class="font-medium">(ভারতের সময়)</span>
            </p>
        @endif
        <p class="mt-3 text-base leading-relaxed text-navy">সোমবার নতুন বিষয় · শনিবার সন্ধ্যা ৬টায় জমা শেষ · রবিবার সকালে সেরা দশটি লেখা এখানে{{ $writers ? ' · এ সপ্তাহে '.Bn::num((string) $writers).' জন লিখেছেন' : '' }}</p>

        @if (is_array($sent))
            {{-- The thank-you card: the reader's own words, kept --}}
            <div id="sent" role="status" class="relative mt-5 scroll-mt-24 rounded-2xl border-2 border-navy bg-white p-5">
                <span class="absolute right-3 top-3 flex h-16 w-16 -rotate-12 items-center justify-center rounded-full border-[3px] border-red text-base font-extrabold text-red" aria-hidden="true">পেয়েছি</span>
                <p class="pr-16 font-hand text-2xl text-red">আপনার লেখা পৌঁছেছে</p>
                @if (filled($sent['body'] ?? ''))<p class="mt-3 whitespace-pre-line pr-4 font-serif text-xl leading-relaxed">“{{ $sent['body'] }}”</p>@endif
                <p class="mt-3 text-lg">বাছাই হলে রবিবার সকালে এই পাতায় ছাপা হবে।@auth আপনাকে ই-মেলেও জানাব।@endauth</p>
                <div class="mt-4 grid gap-2 sm:grid-cols-2">
                    <a href="{{ $wa('হাতপাখার পাঠকের কলমে এ সপ্তাহের বিষয় “'.$current->title.'”। এক মিনিটে আপনিও লিখুন: '.Url::to('/kolom')) }}" target="_blank" rel="noopener"
                        class="flex min-h-12 items-center justify-center rounded-full bg-red px-5 py-2 text-center text-lg font-bold leading-snug text-white shadow-[3px_3px_0_#13212C] flex-col">বন্ধুকেও লিখতে বলুন <span class="text-sm font-semibold">WhatsApp-এ পাঠান</span></a>
                    @if (($sent['left'] ?? 0) > 0)
                        <a href="#kolom-body" class="flex min-h-12 flex-col items-center justify-center rounded-full border-2 border-navy bg-white px-5 py-1 text-center text-lg font-bold leading-snug">আর একটা লিখুন <span class="text-sm font-semibold text-ink2">এ সপ্তাহে আরও {{ Bn::num((string) $sent['left']) }}টি</span></a>
                    @else
                        <p class="flex min-h-12 items-center justify-center rounded-full bg-sky-tint px-5 text-center font-bold">এ সপ্তাহে আপনার তিনটি লেখাই পেয়েছি</p>
                    @endif
                </div>
            </div>
        @endif
        @if ($errors->any())
            <div role="alert" class="mt-5 rounded-xl border-2 border-red bg-white p-4 text-lg font-semibold text-red-dark">@foreach ($errors->all() as $e)<p>{{ $e }}</p>@endforeach</div>
        @endif

        <form method="POST" action="{{ Url::to('/kolom') }}" class="mt-5 rounded-2xl border-2 border-navy bg-white p-4">
            @csrf
            <label class="block" for="kolom-body"><span class="text-lg font-bold">আপনার লেখা</span></label>
            <textarea id="kolom-body" name="body" rows="{{ $current->kind === 'letter' ? 7 : 3 }}" required minlength="8" maxlength="{{ $limit }}"
                data-kolom-count data-kind="{{ $current->kind }}" data-max="{{ $limit }}" aria-describedby="kolom-count"
                placeholder="{{ $current->kind === 'letter' ? 'প্রিয় …' : 'এখানে লিখুন' }}" class="{{ $in }} font-serif text-xl leading-relaxed">{{ old('body') }}</textarea>
            <p id="kolom-count" class="mt-1 text-right text-base font-semibold text-ink2" aria-live="polite">{{ $current->kind === 'six' ? 'ছয়টি শব্দ' : 'সর্বোচ্চ '.Bn::num((string) $limit).' অক্ষর' }}{{ $current->kind === 'memory' ? ', এক-দুই লাইনে' : '' }}</p>
            @guest
                <label class="mt-3 block"><span class="text-lg font-bold">আপনার নাম</span>
                    <input name="name" required maxlength="80" value="{{ old('name') }}" autocomplete="name" class="{{ $in }} h-12 py-0"></label>
            @endguest
            <div class="hidden" aria-hidden="true"><label>Website <input name="website" tabindex="-1" autocomplete="off"></label></div>
            <label class="mt-3 flex min-h-11 items-center gap-3 text-lg"><input type="checkbox" name="anonymous" value="1" class="h-6 w-6 flex-none accent-red" @checked(old('anonymous'))> <span>ছাপার সময় আমার নাম দেখাবেন না</span></label>
            <label class="mt-1 flex min-h-11 items-start gap-3 text-lg"><input type="checkbox" name="agree" value="1" required class="mt-1 h-6 w-6 flex-none accent-red" @checked(old('agree'))> <span>লেখাটি আমার নিজের। হাতপাখা এটি ছাপতে ও শেয়ার করতে পারে।</span></label>
            {{-- D265: a signed-in writer is told by e-mail when chosen; a guest is offered that --}}
            @auth
                <p class="mt-3 rounded-xl bg-sky-tint p-3 text-base">বাছাই হলে রবিবার আপনাকে ই-মেলে জানিয়ে দেব।</p>
            @else
                <p class="mt-3 rounded-xl bg-sky-tint p-3 text-base"><a href="{{ Url::to('/login').'?next='.rawurlencode('/kolom#form') }}" class="font-bold underline">সাইন ইন করে লিখলে</a> বাছাই হওয়ার খবর ই-মেলে পাবেন। না করলেও লেখা যায়।</p>
            @endauth
            <x-turnstile action="kolom" class="mt-3" appearance="interaction-only" />
            <button class="mt-4 h-14 w-full rounded-full bg-red px-8 text-xl font-bold text-white shadow-[3px_3px_0_#13212C]">পাঠান</button>
        </form>
        @if ($hasPicks && ! $readingFirst)
            <a href="#picks" class="mt-4 flex min-h-11 items-center justify-center text-lg font-bold underline">গত সপ্তাহের সেরা দশটি লেখা পড়ুন ↓</a>
        @endif
    </section>
@elseif (! $prompt)
    <section class="mt-8 rounded-3xl border-2 border-dashed border-navy bg-white p-5 text-center">
        @if ($judging)
            <p class="font-hand text-2xl text-red">জমা শেষ, বাছাই চলছে</p>
            <p class="mt-1 text-lg">রবিবার সকালে সেরা দশটি লেখা এখানে। সোমবার সকালে নতুন বিষয়: <strong class="font-serif">“{{ $next[1] }}”</strong>।</p>
        @else
            <p class="font-hand text-2xl text-red">সোমবার সকালে নতুন বিষয়</p>
            <p class="mt-1 font-serif text-2xl font-extrabold">“{{ $next[1] }}”</p>
            <p class="mt-1 text-lg">{{ ReaderPrompt::KINDS[$next[0]] }}: {{ $next[2] }}</p>
        @endif
    </section>
@endif
