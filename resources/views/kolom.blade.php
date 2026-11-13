{{-- D175: পাঠকের কলম. This week's theme and a one-minute form (no account needed); the picks once the owner publishes. --}}
@php
    use App\Models\ReaderPrompt;
    use App\Support\Bn;
    use App\Support\Url;
    $shown = $prompt ?? $latest;   // the published week whose picks are on this page
    $area = 'mt-1 block w-full rounded-xl border-2 border-navy bg-white p-3 text-lg';
@endphp
<x-layouts.app :seo="$seo">
    <section class="mx-auto max-w-[760px] px-5 pb-12 pt-6">
        <p class="font-hand text-2xl text-red">প্রতি সপ্তাহে একটি বিষয়</p>
        <h1 class="font-display text-4xl font-extrabold leading-tight">পাঠকের কলম</h1>
        <p class="mt-2 text-ink2">এক মিনিটে লিখুন। লগ-ইন লাগে না। সেরা দশটি লেখা রবিবার এখানে ছাপা হয়, আপনার নামসহ।</p>

        @if ($current && ! $prompt)
            <div id="form" class="mt-6 scroll-mt-24 rounded-3xl border-2 border-navy bg-white p-5 shadow-[4px_4px_0_#13212C]">
                <p class="text-sm font-bold text-red">এ সপ্তাহের বিষয় · {{ ReaderPrompt::KINDS[$current->kind] }}</p>
                <h2 class="mt-1 font-display text-3xl font-extrabold leading-tight">{{ $current->title }}</h2>
                <p class="mt-2 font-serif text-xl">{{ $current->ask }}</p>
                @if ($current->closes_at)<p class="mt-1 text-sm text-ink2">পাঠানো যাবে {{ Bn::date($current->closes_at->copy()->setTimezone('Asia/Kolkata')) }}, সন্ধ্যা ৬টা পর্যন্ত।</p>@endif

                @if (session('kolom_sent'))
                    <div role="status" class="mt-4 rounded-2xl bg-navy p-4 font-bold text-white">✓ পেয়েছি, ধন্যবাদ! সম্পাদকেরা পড়ে দেখবেন। বাছাই হলে রবিবার এই পাতায় ছাপা হবে। চাইলে আর একটা লিখতে পারেন।</div>
                @endif
                @if ($errors->any())
                    <div role="alert" class="mt-4 rounded-xl border-2 border-red bg-peach-light p-4 font-semibold text-red-dark">@foreach ($errors->all() as $e)<p>{{ $e }}</p>@endforeach</div>
                @endif

                <form method="POST" action="{{ Url::to('/kolom') }}" class="mt-4">@csrf
                    <label class="block"><span class="text-lg font-bold">আপনার লেখা</span> <span class="text-ink2">(সর্বোচ্চ {{ Bn::num((string) $current->max_chars) }} অক্ষর{{ $current->kind === 'six' ? ', ছয়টি শব্দ' : '' }})</span>
                        <textarea name="body" rows="{{ $current->kind === 'letter' ? 6 : 3 }}" required minlength="8" maxlength="{{ $current->max_chars }}" class="{{ $area }} font-serif">{{ old('body') }}</textarea></label>
                    @guest
                        <label class="mt-4 block"><span class="text-lg font-bold">আপনার নাম</span>
                            <input name="name" required maxlength="80" value="{{ old('name') }}" autocomplete="name" class="{{ $area }} h-12 py-0"></label>
                    @endguest
                    <div class="hidden" aria-hidden="true"><label>Website <input name="website" tabindex="-1" autocomplete="off"></label></div>
                    <label class="mt-4 flex min-h-11 items-center gap-3"><input type="checkbox" name="anonymous" value="1" class="h-6 w-6 flex-none" @checked(old('anonymous'))> <span>ছাপার সময় আমার নাম দেখাবেন না</span></label>
                    <label class="mt-1 flex min-h-11 items-start gap-3"><input type="checkbox" name="agree" value="1" required class="mt-1 h-6 w-6 flex-none" @checked(old('agree'))> <span>লেখাটি আমার নিজের। হাতপাখা এটি ছাপতে ও শেয়ার করতে পারে।</span></label>
                    <x-turnstile action="kolom" class="mt-3" appearance="interaction-only" />
                    <button class="mt-4 h-12 w-full rounded-full bg-red px-8 text-lg font-bold text-white shadow-[3px_3px_0_#13212C] sm:w-auto">পাঠান</button>
                </form>
            </div>
        @elseif (! $prompt)
            <p class="mt-6 rounded-2xl border-2 border-dashed border-navy p-5 text-center font-bold">এ সপ্তাহের লেখা নেওয়া শেষ, বাছাই চলছে। সোমবার নতুন বিষয় আসবে।</p>
        @endif

        @if ($shown)
            <div class="mt-10">
                <p class="text-sm font-bold text-red">{{ $prompt ? ReaderPrompt::KINDS[$shown->kind] : 'গত সপ্তাহের বাছাই · '.ReaderPrompt::KINDS[$shown->kind] }}</p>
                <h2 class="font-display text-3xl font-extrabold leading-tight">{{ $shown->title }}</h2>
                <p class="mt-1 text-ink2">{{ $shown->ask }}</p>
                <ol class="mt-4 grid gap-4">
                    @foreach ($shown->picked as $e)
                        <li id="e{{ $e->id }}" class="scroll-mt-24 rounded-2xl border-2 border-navy bg-peach-light p-5">
                            <p class="whitespace-pre-line font-serif text-xl leading-relaxed"><span class="text-red" aria-hidden="true">“</span>{{ $e->body }}<span class="text-red" aria-hidden="true">”</span></p>
                            <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
                                <span class="font-bold">— {{ $e->shownName() }}</span>
                                <a href="https://wa.me/?text={{ rawurlencode('“'.\Illuminate\Support\Str::limit(preg_replace('/\s+/u', ' ', $e->body), 300).'” — '.$e->shownName()."\n".'হাতপাখার পাঠকের কলম: '.$shown->url().'#e'.$e->id) }}" target="_blank" rel="noopener"
                                    class="flex h-11 items-center rounded-full border-2 border-navy bg-white px-4 text-sm font-bold">WhatsApp-এ পাঠান</a>
                            </div>
                        </li>
                    @endforeach
                </ol>
                @if ($prompt && $current)
                    <a href="{{ Url::to('/kolom') }}#form" class="mt-6 flex h-12 items-center justify-center rounded-full bg-red px-6 text-lg font-bold text-white shadow-[3px_3px_0_#13212C]">এ সপ্তাহের বিষয়ে আপনিও লিখুন →</a>
                @endif
            </div>
        @endif

        @if ($past->count() > 1)
            <h2 class="mt-10 font-display text-2xl font-extrabold">আগের সপ্তাহগুলো</h2>
            <ul class="mt-2 divide-y divide-rule border-y border-rule">
                @foreach ($past->skip(1) as $p)
                    <li><a href="{{ $p->url() }}" class="flex min-h-14 items-center justify-between gap-3 py-2 font-bold hover:text-red"><span>{{ $p->title }}<span class="block text-sm font-medium text-ink2">{{ ReaderPrompt::KINDS[$p->kind] }} · {{ Bn::date($p->published_at) }}</span></span><span aria-hidden="true">→</span></a></li>
                @endforeach
            </ul>
        @endif
    </section>
</x-layouts.app>
