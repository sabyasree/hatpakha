{{-- D166 "ই-মেল ও পাসওয়ার্ড" (now "সেটিংস", D169) (owner + UX review + tests with older users): switches that look like a phone's, each
     saying what it does now; the reading history with what deleting it removes; password; optional extra security
     (calm, folded away); help; লগ-আউট. --}}
@php
    use App\Support\Url;
    $card = 'mt-3 rounded-2xl border-2 border-navy bg-white px-4';
    $setRow = 'flex items-center justify-between gap-4 border-b border-rule py-4 last:border-b-0';
    $switch = function (bool $on) {
        return '<span class="flex flex-none flex-col items-center gap-1"><span class="relative block h-9 w-16 rounded-full border-2 border-navy '.($on ? 'bg-sky-deep' : 'bg-rule').'" aria-hidden="true">'
            .'<span class="absolute top-0.5 block h-7 w-7 rounded-full border-2 border-navy bg-white transition-all '.($on ? 'left-[1.85rem]' : 'left-0.5').'"></span></span>'
            .'<span class="whitespace-nowrap text-sm font-bold">'.e($on ? __('চালু') : __('বন্ধ')).'</span></span>';
    };
    $more = 'flex min-h-14 cursor-pointer list-none items-center justify-between gap-3 px-4 py-2 text-lg font-bold [&::-webkit-details-marker]:hidden';
@endphp
<x-layouts.app :seo="$seo">
    <x-account-shell tab="settings" :user="$user" :author="$author" :counts="$counts" :two-factor-on="$twoFactorOn" :cert-count="$certCount">
        <div class="max-w-2xl">
        {{-- D169: বাংলা or English for the account, sign-in pages and menus --}}
        <section id="language" class="mb-8 flex flex-wrap items-center justify-between gap-3 rounded-2xl border-2 border-navy bg-white p-4">
            <span><span class="block text-lg font-bold">{{ __('ভাষা') }} · Language</span><span class="block text-ink2">{{ __('অ্যাকাউন্ট আর মেনু কোন ভাষায় দেখবেন। লেখাগুলো যেমন আছে তেমনই থাকবে।') }}</span></span>
            @include('partials.language-switch')
        </section>
        <h2 id="letters" class="scroll-mt-24 font-display text-2xl font-extrabold">{{ __('ই-মেল আর নোটিফিকেশন') }}</h2>
        <div class="{{ $card }}">
            <form method="POST" action="{{ Url::to('/account/newsletter') }}">@csrf
                <input type="hidden" name="newsletter" value="{{ $user->mail_opt_out ? 1 : 0 }}">
                <button class="{{ $setRow }} w-full text-left" role="switch" aria-checked="{{ $user->mail_opt_out ? 'false' : 'true' }}">
                    <span><span class="block text-lg font-bold">{{ __('সাপ্তাহিক ই-মেল') }}</span><span class="block text-ink2">{{ __('নতুন লেখার খবর। বন্ধ করলে এই ই-মেল আর আসবে না। আপনার নিজের লেখার খবর (যেমন সম্পাদকের মেসেজ) তবুও আসবে।') }}</span></span>
                    {!! $switch(! $user->mail_opt_out) !!}
                </button>
            </form>
            @if (\App\Support\WebPush::publicKey())
                <div id="phone" class="{{ $setRow }} scroll-mt-24 flex-wrap">
                    <span class="min-w-0 flex-1"><span class="block text-lg font-bold">{{ __('নতুন সংখ্যা বেরোলে ফোনে নোটিফিকেশন') }}</span><span class="block text-ink2">{{ __('মাসে একবার, এই ফোনের স্ক্রিনে একটা ছোট নোটিফিকেশন আসবে। WhatsApp বা SMS নয়, ফোন নম্বর লাগবে না।') }}</span></span>
                    <x-push-button topic="issues" :off="__('চালু করুন')" :on="__('✓ চালু আছে (বন্ধ করতে টিপুন)')" />
                </div>
                <div class="{{ $setRow }} flex-wrap">
                    <span class="min-w-0 flex-1"><span class="block text-lg font-bold">{{ __('যাঁদের ফলো করেন, তাঁদের নতুন লেখা') }}</span><span class="block text-ink2">{{ __('এই ফোনে নোটিফিকেশন পাঠান।') }}</span></span>
                    <x-push-button topic="account" :off="__('চালু করুন')" :on="__('✓ চালু আছে (বন্ধ করতে টিপুন)')" />
                </div>
                <p class="pb-3 text-sm font-bold" data-push-status role="status"></p>
                <p class="pb-3 text-sm" data-push-unsupported hidden>{{ __('এই ব্রাউজারে নোটিফিকেশন চলে না। Chrome বা Firefox-এ খুলে দেখুন।') }}</p>
                <p class="pb-3 text-sm text-ink2" data-push-ios hidden>{{ __('আইফোনে: আগে Safari-তে এগুলো টিপুন:') }} <strong>{{ __('শেয়ার') }}</strong> (□↑) → <strong>Add to Home Screen</strong>{{ __('।') }} {{ __('তারপর হোম স্ক্রিনের হাতপাখা থেকে খুলে এখানে চালু করুন।') }} <a href="{{ Url::to('/app') }}#iphone" class="underline">{{ __('ছবিসহ দেখুন') }}</a></p>
            @endif
        </div>
        @if (\App\Support\WebPush::publicKey())
            @php($pushSections = \App\Models\Section::where('is_active', true)->where('slug', '!=', \App\Models\Section::KIDS)->orderBy('sort')->get(['id', 'name_bn']))
            <details class="group mt-3 rounded-2xl border-2 border-navy bg-white">
                <summary class="{{ $more }}"><span>{{ __('রোজের নোটিফিকেশন বা শুধু কিছু বিভাগের') }}<span class="block text-sm font-medium text-ink2">{{ __('ইচ্ছে হলে') }}</span></span><span class="transition group-open:rotate-180" aria-hidden="true">▾</span></summary>
                <div class="border-t-2 border-rule p-4">
                    <x-push-button topic="daily" :off="__('রোজ সন্ধ্যায় সব নতুন লেখার খবর চাই')" :on="__('✓ রোজের নোটিফিকেশন চালু (বন্ধ করতে টিপুন)')" />
                    <p class="mt-4 font-bold">{{ __('যে বিভাগের নতুন লেখা চান, টিপুন:') }}</p>
                    <div class="mt-2 flex flex-wrap gap-2">@foreach ($pushSections as $ps)<x-push-button :topic="'section:'.$ps->id" :off="$ps->name_bn" :on="'✓ '.$ps->name_bn" :small="true" />@endforeach</div>
                    <p class="mt-3 text-sm text-ink2">{{ __('প্রতিটি ফোন বা কম্পিউটারে আলাদা করে চালু করতে হয়।') }}</p>
                </div>
            </details>
        @endif

        <h2 id="reading" class="mt-8 scroll-mt-24 font-display text-2xl font-extrabold">{{ __('পড়ার হিস্টরি') }}</h2>
        <div class="{{ $card }}">
            <form method="POST" action="{{ Url::to('/account/reading-history') }}">@csrf
                <input type="hidden" name="on" value="{{ $user->reading_history ? 0 : 1 }}">
                <button class="{{ $setRow }} w-full text-left" role="switch" aria-checked="{{ $user->reading_history ? 'true' : 'false' }}">
                    <span><span class="block text-lg font-bold">{{ __('পড়ার হিস্টরি রাখুন') }}</span><span class="block text-ink2">{{ __('“পড়া চালিয়ে যান” আর “সম্প্রতি পড়া”-র জন্য। শুধু আপনি দেখতে পান। এক বছরের পুরনো হিস্টরি নিজে থেকেই মুছে যায়।') }}</span></span>
                    {!! $switch((bool) $user->reading_history) !!}
                </button>
            </form>
            <details class="group border-t border-rule py-3">
                <summary class="flex min-h-12 cursor-pointer list-none items-center justify-between gap-3 [&::-webkit-details-marker]:hidden">
                    <span><span class="block text-lg font-bold">{{ __('পড়ার হিস্টরি মুছে দিন') }}</span><span class="block text-ink2">{{ __('শুধু কোন লেখা কতটা পড়েছেন, সেই হিসেব মুছবে। সেভ করা লেখা, লাইক করা লেখা আর আপনার নিজের লেখা যেমন আছে থাকবে।') }}</span></span>
                    <span class="flex h-11 flex-none items-center rounded-full border-2 border-navy px-4 font-bold">{{ __('মুছুন') }}</span></summary>
                <form method="POST" action="{{ Url::to('/account/reading-history/clear') }}" class="mt-3 rounded-xl bg-peach-light p-3">@csrf
                    <p class="font-bold">{{ __('সত্যিই মুছবেন? পরে আর ফেরানো যাবে না।') }}</p>
                    <button class="mt-2 h-12 rounded-full bg-navy px-6 font-bold text-white">{{ __('হ্যাঁ, মুছে দিন') }}</button>
                </form>
            </details>
        </div>

        <h2 id="security" class="mt-8 scroll-mt-24 font-display text-2xl font-extrabold">{{ __('পাসওয়ার্ড') }}</h2>
        <details class="group mt-3 rounded-2xl border-2 border-navy bg-white" @if ($errors->hasAny(['current_password', 'password'])) open @endif>
            <summary class="{{ $more }}"><span>{{ __('পাসওয়ার্ড বদলান') }}</span><span class="transition group-open:rotate-180" aria-hidden="true">▾</span></summary>
            <form method="POST" action="{{ Url::to('/account/password') }}" class="border-t-2 border-rule p-4">@csrf
                <x-field name="current_password" type="password" :label="__('এখনকার পাসওয়ার্ড')" autocomplete="current-password" required />
                <x-field name="password" type="password" :label="__('নতুন পাসওয়ার্ড')" autocomplete="new-password" required :hint="__('অন্তত :count অক্ষর, অক্ষর আর সংখ্যা দুই-ই।', ['count' => \App\Support\Bn::num('10')])" />
                <x-field name="password_confirmation" type="password" :label="__('নতুন পাসওয়ার্ড আবার')" autocomplete="new-password" required />
                <button class="h-12 rounded-full bg-red px-8 font-bold text-white">{{ __('বদলান') }}</button>
                <p class="mt-3 text-sm text-ink2">{{ __('এখনকার পাসওয়ার্ড মনে নেই? লগ আউট করে সাইন ইন পাতায় “পাসওয়ার্ড ভুলে গেছেন?” টিপুন। ই-মেলে নতুন পাসওয়ার্ডের লিংক যাবে।') }}</p>
            </form>
        </details>
        <details class="group mt-3 rounded-2xl border-2 border-navy bg-white" @if ($setup || session('recovery_codes') || $errors->has('code')) open @endif>
            <summary class="{{ $more }}"><span>{{ __('টু-স্টেপ ভেরিফিকেশন') }} {{ $twoFactorOn ? __('(চালু আছে)') : '' }}<span class="block text-sm font-medium text-ink2">{{ __('ইচ্ছে হলে। না করলেও কোনও অসুবিধা নেই।') }}</span></span><span class="transition group-open:rotate-180" aria-hidden="true">▾</span></summary>
            <div class="border-t-2 border-rule p-4">
                @if ($twoFactorOn)
                    <p>{{ __('সাইন ইন করার সময় পাসওয়ার্ডের পরে ফোনের অ্যাপের :digits অঙ্কের কোডও লাগে।', ['digits' => \App\Support\Bn::num('6')]) }}</p>
                    @if (session('recovery_codes'))
                        <div class="mt-4 rounded-2xl border-2 border-red bg-peach-light p-4">
                            <strong>{{ __('এই কোডগুলো এখনই লিখে রাখুন') }}</strong>
                            <p class="text-sm">{{ __('ফোন হারালে এর একটি দিয়ে সাইন ইন করা যাবে (প্রতিটি একবার)। এগুলো আর দেখানো হবে না।') }}</p>
                            <ul class="mt-2 grid grid-cols-1 gap-1 font-mono text-sm sm:grid-cols-2">@foreach (session('recovery_codes') as $code)<li>{{ $code }}</li>@endforeach</ul>
                        </div>
                    @endif
                    @unless ($user->hasRole(\App\Enums\Role::Editor))
                        <form method="POST" action="{{ Url::to('/account/2fa/disable') }}" class="mt-4">@csrf
                            <x-field name="current_password" type="password" :label="__('বন্ধ করতে পাসওয়ার্ড দিন')" autocomplete="current-password" required />
                            <button class="h-11 rounded-full border-2 border-navy px-5 font-bold">{{ __('বন্ধ করুন') }}</button>
                        </form>
                    @endunless
                @elseif ($setup)
                    <ol class="list-decimal space-y-2 pl-5">
                        <li>{{ __('ফোনে একটি কোডের অ্যাপ খুলুন (Google Authenticator, Microsoft Authenticator বা 2FAS)।') }}</li>
                        <li>{{ __('এই ছবিটি (QR কোড) অ্যাপ দিয়ে স্ক্যান করুন।') }}</li>
                    </ol>
                    @if ($setup['qr'])<img src="{{ $setup['qr'] }}" alt="{{ __('QR কোড') }}" class="mt-3 h-44 w-44 rounded-xl border-2 border-navy bg-white p-2">@endif
                    <p class="mt-2 text-sm text-ink2">{{ __('স্ক্যান না হলে হাতে লিখুন:') }} <code class="break-all rounded bg-peach-light px-1 font-mono">{{ $setup['secret'] }}</code></p>
                    <form method="POST" action="{{ Url::to('/account/2fa/confirm') }}" class="mt-4">@csrf
                        <x-field name="code" :label="__(':step. অ্যাপে দেখানো :digits অঙ্কের কোড', ['step' => \App\Support\Bn::num('3'), 'digits' => \App\Support\Bn::num('6')])" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required />
                        <button class="h-12 rounded-full bg-red px-6 font-bold text-white">{{ __('চালু করুন') }}</button>
                    </form>
                @else
                    <p>{{ __('কেউ আপনার পাসওয়ার্ড জেনে গেলেও আপনার ফোনের কোড ছাড়া সাইন ইন করতে পারবে না। এর জন্য ফোনে একটা কোডের অ্যাপ লাগে।') }}</p>
                    <form method="POST" action="{{ Url::to('/account/2fa/start') }}" class="mt-3">@csrf<button class="h-12 rounded-full border-2 border-navy px-6 font-bold">{{ __('চালু করতে চাই') }}</button></form>
                @endif
            </div>
        </details>

        <h2 class="mt-8 font-display text-2xl font-extrabold">{{ __('সাহায্য') }}</h2>
        <div class="mt-3 flex flex-wrap items-center gap-2">
            <a href="{{ Url::to('/contact-us') }}" class="flex h-12 items-center rounded-full border-2 border-navy bg-white px-5 font-bold hover:bg-peach-light">{{ __('আমাদের লিখুন') }}</a>
            <span class="text-ink2">{{ __('বা ওপরের “প্রশ্ন আছে?” টিপুন।') }}</span>
        </div>

        <form method="POST" action="{{ Url::to('/logout') }}" class="mt-10">@csrf
            <button class="h-14 w-full rounded-full bg-navy text-lg font-bold text-white">{{ __('লগ আউট') }}</button>
            <p class="mt-2 text-center text-ink2">{{ __('এই ফোনে অ্যাকাউন্ট থেকে বেরিয়ে যাবেন। পরে আবার ই-মেল আর পাসওয়ার্ড দিয়ে সাইন ইন করতে পারবেন।') }}</p>
        </form>
        </div>
    </x-account-shell>
</x-layouts.app>
