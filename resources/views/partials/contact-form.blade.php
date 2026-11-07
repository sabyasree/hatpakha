{{-- D89: the contact / advertising form, instead of an e-mail address. $kind: contact | advertise.
     Guards: CSRF, honeypot, "too fast" token, Turnstile (shown only if Cloudflare needs a click), server limits. --}}
@php
    $topics = \App\Models\ContactMessage::TOPICS[$kind];
    $me = auth()->user();
    $input = 'block h-12 w-full rounded-xl border-2 border-navy bg-white px-4 text-lg focus:border-sky-deep aria-invalid:border-red';
    $err = fn ($f) => $errors->has($f) ? 'aria-invalid=true aria-describedby='.$f.'-err' : '';
@endphp
<section id="form" class="mt-10 scroll-mt-24 rounded-3xl border-2 border-navy bg-white p-5 shadow-[4px_4px_0_#13212C] sm:p-7">
    @if (session('contact_sent'))
        <div role="status" class="text-center">
            <div class="font-hand text-3xl text-red">পৌঁছেছে!</div>
            <p class="mt-2 text-lg">{{ session('contact_sent') }}</p>
            <a href="{{ \App\Support\Url::to('/') }}" class="mt-5 inline-flex h-12 items-center rounded-full bg-navy px-6 font-bold text-white">প্রথম পাতায় ফিরুন</a>
        </div>
    @else
        <h2 class="font-display text-2xl font-extrabold">{{ $kind === 'advertise' ? 'প্রস্তাব পাঠান' : 'আমাদের লিখুন' }}</h2>
        <form method="POST" action="{{ \App\Support\Url::to('/contact/'.$kind) }}" class="relative mt-4" novalidate>
            @csrf
            <input type="hidden" name="started" value="{{ \App\Http\Controllers\CommentController::startedToken() }}">
            @if ($errors->any())
                <p role="alert" class="mb-4 rounded-xl border-2 border-red p-3 font-bold text-red">কয়েকটি ঘর ঠিক করতে হবে, নিচে লাল লেখা দেখুন।</p>
            @endif
            <div aria-hidden="true" class="absolute -left-[9999px] h-px w-px overflow-hidden"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

            <fieldset class="mb-5 min-w-0">
                <legend class="mb-2 font-bold">{{ $kind === 'advertise' ? 'কী ধরনের সহযোগিতা' : 'কী বিষয়ে' }}</legend>
                <div class="relative flex flex-wrap gap-2">
                    @foreach ($topics as $key => $label)
                        <label class="relative cursor-pointer">
                            <input type="radio" name="topic" value="{{ $key }}" class="peer sr-only" @checked(old('topic', count($topics) === 1 ? $key : null) === $key) required>
                            <span class="flex min-h-11 items-center rounded-full border-2 border-navy px-4 font-bold peer-checked:bg-navy peer-checked:text-white peer-focus-visible:outline peer-focus-visible:outline-3 peer-focus-visible:outline-sky-deep">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
                @error('topic')<p id="topic-err" class="mt-2 font-semibold text-red">{{ $message }}</p>@enderror
            </fieldset>

            <div class="grid gap-x-4 sm:grid-cols-2">
                <label class="mb-4 block"><span class="mb-1 block font-bold">আপনার নাম</span>
                    <input name="name" value="{{ old('name', $me?->name) }}" autocomplete="name" required maxlength="80" class="{{ $input }}" {{ $err('name') }}>
                    @error('name')<span id="name-err" class="mt-1 block font-semibold text-red">{{ $message }}</span>@enderror</label>
                <label class="mb-4 block"><span class="mb-1 block font-bold">ই-মেল <span class="font-normal text-ink2">(উত্তর এখানে যাবে)</span></span>
                    <input name="email" type="email" inputmode="email" value="{{ old('email', $me?->email) }}" autocomplete="email" required maxlength="190" class="{{ $input }}" {{ $err('email') }}>
                    @error('email')<span id="email-err" class="mt-1 block font-semibold text-red">{{ $message }}</span>@enderror</label>
                @if ($kind === 'advertise')
                    <label class="mb-4 block"><span class="mb-1 block font-bold">প্রতিষ্ঠান বা উদ্যোগ</span>
                        <input name="organisation" value="{{ old('organisation') }}" autocomplete="organization" required maxlength="120" class="{{ $input }}" {{ $err('organisation') }}>
                        @error('organisation')<span id="organisation-err" class="mt-1 block font-semibold text-red">{{ $message }}</span>@enderror</label>
                    <label class="mb-4 block"><span class="mb-1 block font-bold">ফোন <span class="font-normal text-ink2">(ঐচ্ছিক)</span></span>
                        <input name="phone" type="tel" inputmode="tel" value="{{ old('phone') }}" autocomplete="tel" maxlength="30" class="{{ $input }}" {{ $err('phone') }}>
                        @error('phone')<span id="phone-err" class="mt-1 block font-semibold text-red">{{ $message }}</span>@enderror</label>
                    <label class="mb-4 block sm:col-span-2"><span class="mb-1 block font-bold">কোন সময়ে <span class="font-normal text-ink2">(ঐচ্ছিক, যেমন: পুজো সংখ্যা ২০২৭)</span></span>
                        <input name="when" value="{{ old('when') }}" maxlength="120" class="{{ $input }}"></label>
                @else
                    <label class="mb-4 block sm:col-span-2"><span class="mb-1 block font-bold">লেখা বা পাতার লিংক <span class="font-normal text-ink2">(থাকলে)</span></span>
                        <input name="link" type="text" inputmode="url" autocapitalize="off" spellcheck="false" value="{{ old('link') }}" placeholder="hatpakha.com/…" maxlength="500" class="{{ $input }}" {{ $err('link') }}>
                        @error('link')<span id="link-err" class="mt-1 block font-semibold text-red">{{ $message }}</span>@enderror</label>
                @endif
            </div>

            <label class="mb-2 block"><span class="mb-1 block font-bold">{{ $kind === 'advertise' ? 'কী করতে চান' : 'আপনার বার্তা' }}</span>
                <textarea name="body" rows="5" required minlength="10" maxlength="3000" class="block w-full rounded-xl border-2 border-navy bg-white px-4 py-3 text-lg leading-relaxed focus:border-sky-deep aria-invalid:border-red" {{ $err('body') }}
                    placeholder="{{ $kind === 'advertise' ? 'আপনার প্রস্তাব, বাজেট বা প্রশ্ন দু-চার লাইনে…' : 'কী জানাতে চান, দু-চার লাইনে লিখুন…' }}">{{ old('body') }}</textarea>
                @error('body')<span id="body-err" class="mt-1 block font-semibold text-red">{{ $message }}</span>@enderror</label>
            <p class="mb-4 text-sm text-ink2">আপনার নাম আর ই-মেল শুধু উত্তর দেওয়ার জন্য রাখা হয়, কোথাও প্রকাশ হয় না।</p>

            <x-turnstile :action="$kind" class="mb-3" :lazy="true" appearance="interaction-only" />
            <button data-sending="পাঠানো হচ্ছে…" class="flex h-14 w-full items-center justify-center rounded-full bg-red text-lg font-bold text-white shadow-[4px_4px_0_#13212C] disabled:opacity-60 sm:w-fit sm:px-10">পাঠান</button>
        </form>
        <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
            // One tap sends once: after the page's own token check lets the form go, the button shows it is sending.
            document.querySelector('#form form')?.addEventListener('submit', function (e) {
                var b = this.querySelector('[data-sending]');
                setTimeout(function () { if (!e.defaultPrevented && b) { b.disabled = true; b.textContent = b.dataset.sending; } }, 0);
            });
        </script>
    @endif
</section>
