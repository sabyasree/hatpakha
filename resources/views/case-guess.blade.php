{{-- D213: "who did it?" in a detective case. Guess before the last episode; the result after it. $serial: Serial --}}
@php
    use App\Support\Bn;
    use App\Support\CaseGuesses;
    $suspects = CaseGuesses::suspects($serial);
    $open = $suspects ? CaseGuesses::open($serial) : false;
    $result = $suspects ? CaseGuesses::result($serial) : null;
    $mine = $suspects ? CaseGuesses::mine($serial, auth()->user()) : null;
@endphp
@if ($open)
    <section class="mt-6 rounded-2xl border-2 border-navy bg-white p-5 shadow-[4px_4px_0_#13212C]" id="onuman">
        <p class="font-hand text-2xl text-red">আপনিও গোয়েন্দা</p>
        <h2 class="font-display text-2xl font-extrabold">কে করেছে বলে মনে হয়?</h2>
        <p class="mt-1 text-ink2">শেষ পর্ব বেরোনোর আগে পর্যন্ত অনুমান পাঠানো বা বদলানো যায়। আপনার অনুমান আর কেউ দেখবে না। শেষ পর্বের পরে জানানো হবে কতজন ঠিক ধরেছিলেন।</p>
        @if (session('guess_status'))<p class="mt-3 rounded-xl bg-sky-tint p-3 font-semibold text-sky-night" role="status">{{ session('guess_status') }}</p>@endif
        @auth
            <form method="POST" action="{{ \App\Support\Url::to('/dharabahik/'.$serial->id.'/guess') }}" class="mt-4">@csrf
                <fieldset>
                    <legend class="font-bold">একজনকে বেছে নিন</legend>
                    <div class="mt-2 grid gap-2">
                        @foreach ($suspects as $name)
                            <label class="flex min-h-12 cursor-pointer items-center gap-3 rounded-xl border-2 border-navy bg-paper px-4 py-2 font-bold has-[:checked]:bg-navy has-[:checked]:text-white">
                                <input type="radio" name="suspect" value="{{ $name }}" required class="h-5 w-5 shrink-0 accent-red" @checked(old('suspect', $mine?->suspect) === $name)> <span>{{ $name }}</span></label>
                        @endforeach
                    </div>
                    @error('suspect')<p class="mt-1 font-bold text-red" role="alert">{{ $message }}</p>@enderror
                </fieldset>
                <label class="mt-4 block"><span class="font-bold">কেন মনে হল? <span class="font-normal text-ink2">(না লিখলেও চলে)</span></span>
                    <textarea name="why" rows="3" maxlength="600" class="mt-1 block w-full rounded-xl border-2 border-navy bg-white p-3" placeholder="কোন সূত্রটা চোখে পড়ল?">{{ old('why', $mine?->why) }}</textarea></label>
                <label class="mt-3 flex min-h-11 cursor-pointer items-center gap-3"><input type="hidden" name="show_name" value="0">
                    <input type="checkbox" name="show_name" value="1" class="h-5 w-5 accent-red" @checked((bool) old('show_name', $mine ? $mine->show_name : true))>
                    <span>ঠিক ধরলে আমার নাম এই পাতায় দেখানো যাবে</span></label>
                <button class="mt-3 h-12 rounded-full bg-red px-6 text-lg font-bold text-white shadow-[3px_3px_0_#13212C]">{{ $mine ? 'অনুমান বদলান' : 'অনুমান পাঠান' }}</button>
                @if ($mine)<p class="mt-2 text-sm font-semibold text-sky-night">আপনার এখনকার অনুমান: {{ $mine->suspect }}</p>@endif
            </form>
        @else
            <a href="{{ \App\Support\Url::to('/login') }}" class="mt-4 inline-flex h-12 items-center rounded-full bg-navy px-6 font-bold text-white">অনুমান পাঠাতে লগ ইন করুন</a>
        @endauth
    </section>
@elseif ($result && $result['total'] > 0)
    <section class="mt-6 rounded-2xl border-2 border-navy bg-peach-light p-5">
        <h2 class="font-display text-2xl font-extrabold">পাঠকদের অনুমান</h2>
        <p class="mt-1 font-serif text-lg">{{ Bn::num((string) $result['total']) }} জন অনুমান পাঠিয়েছিলেন। ঠিক ধরেছিলেন {{ Bn::num((string) $result['right']) }} জন।</p>
        @if ($result['names'])<p class="mt-2"><strong>ঠিক ধরেছিলেন:</strong> {{ implode(', ', $result['names']) }}</p>@endif
        @if ($mine)<p class="mt-2 font-semibold">আপনার অনুমান ছিল: {{ $mine->suspect }}।</p>@endif
    </section>
@endif
