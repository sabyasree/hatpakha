{{-- D177, D243: under a finished game. A signed-in player sees today's total, what is left, the place on the board and
     the next level (filled in by public/js/hp-after.js from the score's answer). A guest is told what an account gives. --}}
@if (! empty($practice))
    <a href="{{ \App\Support\Url::to('/khela/purono') }}" class="mt-4 flex h-12 items-center justify-center rounded-full bg-red px-6 font-bold text-white">আরেকটা পুরোনো খেলা →</a>
@else
@auth
    <div data-game-after hidden class="mt-4">
        <div class="rounded-2xl border-2 border-navy bg-white p-3">
            <p data-after-full hidden class="mb-2 inline-block -rotate-2 rounded-lg border-2 border-red px-3 py-1 font-display text-xl font-extrabold text-red">আজকের দিন পূর্ণ ✓</p>
            <div class="flex justify-between font-extrabold"><span>আজ মোট</span><span data-after-total></span></div>
            <div class="mt-1.5 h-3 overflow-hidden rounded-full border-2 border-navy bg-rule"><i class="block h-full bg-red" data-after-bar></i></div>
            <div class="mt-2 grid grid-cols-2 gap-1.5 text-sm font-bold" data-after-games></div>
            <p class="mt-3 text-sm font-bold" data-after-more-label></p>
            <div class="mt-1 grid grid-cols-2 gap-1.5 text-sm font-bold" data-after-rest></div>
        </div>
        <p class="mt-3 text-lg font-bold" data-after-rank></p>
        <a href="#" data-after-next hidden class="mt-3 flex h-14 items-center justify-center rounded-full bg-red px-6 text-lg font-bold text-white shadow-[3px_3px_0_#13212C]"></a>
        <div class="mt-4" data-after-level-box>
            <div class="flex justify-between text-sm font-bold"><span data-after-level></span><span data-after-level-next></span></div>
            <div class="mt-1 h-2.5 overflow-hidden rounded-full border-2 border-navy bg-rule"><i class="block h-full bg-sky" data-after-level-bar></i></div>
            <p class="mt-1 text-xs text-ink2">স্তরে সব খেলার পয়েন্ট জমে, দিনের ৩০০-র বাইরেরগুলোও।</p>
        </div>
        <div class="mt-3" data-after-week hidden>
            <ol class="flex justify-between" aria-hidden="true" data-after-week-dots></ol>
            <p class="mt-1 text-sm font-bold leading-tight" data-after-week-text></p>
        </div>
        <p class="mt-3 font-bold" data-after-streak></p>
    </div>
    <a href="{{ \App\Support\Url::to('/khela/board') }}" class="mt-2 flex min-h-11 items-center justify-center font-bold text-sky-night underline">সেরাদের তালিকা দেখুন →</a>
    {{-- D182: an evening reminder on days the reader has a run going and has not played yet --}}
    <p class="mt-2 flex justify-center"><x-push-button topic="games" off="🔔 রোজ খেলার কথা মনে করিয়ে দিন" on="✓ খেলার রিমাইন্ডার চালু (বন্ধ করতে টিপুন)" :small="true" /></p>
@else
    @php
        // D330 (UX panel): a guest's visit no longer ends with the result; the next game open to all, then the account
        $open = [['শব্দ-খেলা', '/khela'], ['সহজ ধাঁধা', '/khela/dhadha'], ['বানান-যাচাই', '/khela/banan'], ['সহজ শব্দজব্দ', '/khela/shobdojobdo/sohoj']];
        $here = '/'.trim(request()->path(), '/');
        $guestNext = collect($open)->first(fn ($g) => $g[1] !== $here);
    @endphp
    <a href="{{ \App\Support\Url::to($guestNext[1]) }}" class="mt-4 flex h-14 items-center justify-center rounded-full bg-red px-6 text-lg font-bold text-white shadow-[3px_3px_0_#13212C]">এবার {{ $guestNext[0] }} খেলুন →</a>
    <div class="mt-3 rounded-2xl border-2 border-navy bg-white p-4">
        <p class="text-lg font-bold">পয়েন্ট জমিয়ে রাখতে চান?</p>
        <p class="mt-1 text-ink2">বিনামূল্যে অ্যাকাউন্ট খুলুন: রোজকার পয়েন্ট জমবে, সব খেলা খুলবে, সেরাদের তালিকায় নাম উঠবে, মাসের সেরার খেতাব জেতা যাবে।</p>
        <a href="{{ \App\Support\Url::to('/register') }}?next={{ rawurlencode('/khela/aaj/') }}" class="mt-3 flex h-12 items-center justify-center rounded-full bg-red px-6 font-bold text-white shadow-[3px_3px_0_#13212C]">বিনামূল্যে অ্যাকাউন্ট খুলুন</a>
        <p class="mt-2 text-center"><a href="{{ \App\Support\Url::to('/login') }}?next={{ rawurlencode('/'.trim(request()->path(), '/').'/') }}" class="inline-flex min-h-11 items-center font-bold text-sky-night underline">সাইন ইন</a> · <a href="{{ \App\Support\Url::to('/khela/board') }}" class="inline-flex min-h-11 items-center font-bold text-sky-night underline">কারা এগিয়ে, দেখুন</a></p>
    </div>
@endauth
@endif
