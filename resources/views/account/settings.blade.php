{{-- Settings (owner + UX review): security and letters, one card open at a time; the profile has its own tab (owner). --}}
@php
    use App\Support\Bn;
    use App\Support\Url;
    $avatar = $author?->avatar_path;
@endphp
<x-layouts.app :seo="$seo">
    <x-account-shell tab="settings" :user="$user" :author="$author" :counts="$counts" :two-factor-on="$twoFactorOn" :cert-count="$certCount">
        <div class="space-y-4">
        <details id="security" class="group scroll-mt-20 rounded-3xl border-2 border-navy bg-white" data-settings-card>
            <summary class="flex min-h-16 cursor-pointer list-none items-center gap-3 px-5 py-3 [&::-webkit-details-marker]:hidden">
                <span class="min-w-0 flex-1"><span class="block text-xl font-extrabold">নিরাপত্তা</span><span class="text-sm text-ink2">দুই-ধাপের যাচাই {{ $twoFactorOn ? '(চালু)' : '(বন্ধ)' }} · পাসওয়ার্ড</span></span>
                <span class="text-2xl transition group-open:rotate-180" aria-hidden="true">▾</span>
            </summary>
            <div class="border-t-2 border-rule p-3 sm:p-5">
            <div class="grid gap-5 md:grid-cols-2">
                <div class="sm:rounded-3xl sm:border-2 sm:border-navy bg-white sm:p-5">
                    <h3 class="text-xl font-extrabold">দুই-ধাপের যাচাই</h3>
                    @if ($twoFactorOn)
                        <p class="mt-2"><span class="rounded-full bg-sky-tint px-3 py-1 font-bold text-sky-night">✓ চালু আছে</span></p>
                        <p class="mt-3 text-ink2">লগ-ইনের সময় পাসওয়ার্ডের পরে অ্যাপের কোডও লাগবে।</p>
                        @if (session('recovery_codes'))
                            <div class="mt-4 rounded-2xl border-2 border-red bg-peach-light p-4">
                                <strong>উদ্ধার-কোডগুলো এখনই লিখে রাখুন</strong>
                                <p class="text-sm">ফোন হারালে এর একটি দিয়ে ঢোকা যাবে (প্রতিটি একবার)। এগুলো আর দেখানো হবে না।</p>
                                <ul class="mt-2 grid grid-cols-1 gap-1 font-mono text-sm sm:grid-cols-2">@foreach (session('recovery_codes') as $code)<li>{{ $code }}</li>@endforeach</ul>
                            </div>
                        @endif
                        @unless ($user->hasRole(\App\Enums\Role::Editor))
                            <details class="mt-4"><summary class="cursor-pointer text-sm font-bold text-ink2">বন্ধ করতে চান?</summary>
                                <form method="POST" action="{{ Url::to('/account/2fa/disable') }}" class="mt-3">@csrf
                                    <x-field name="current_password" type="password" label="পাসওয়ার্ড দিয়ে নিশ্চিত করুন" autocomplete="current-password" required />
                                    <button class="h-11 rounded-full border-2 border-red px-5 font-bold text-red">বন্ধ করুন</button>
                                </form>
                            </details>
                        @endunless
                    @elseif ($setup)
                        <ol class="mt-3 list-decimal space-y-2 pl-5">
                            <li>ফোনে একটি অথেনটিকেটর অ্যাপ খুলুন (Google Authenticator, Microsoft Authenticator বা 2FAS)।</li>
                            <li>এই QR কোডটি স্ক্যান করুন।</li>
                        </ol>
                        @if ($setup['qr'])<img src="{{ $setup['qr'] }}" alt="QR কোড" class="mt-3 h-44 w-44 rounded-xl border-2 border-navy bg-white p-2">@endif
                        <p class="mt-2 text-sm text-ink2">স্ক্যান না হলে হাতে লিখুন: <code class="break-all rounded bg-peach-light px-1 font-mono">{{ $setup['secret'] }}</code></p>
                        <form method="POST" action="{{ Url::to('/account/2fa/confirm') }}" class="mt-4">@csrf
                            <x-field name="code" label="৩. অ্যাপে দেখানো ৬ অঙ্কের কোড" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required />
                            <button class="h-11 rounded-full bg-red px-6 font-bold text-white">চালু করুন</button>
                        </form>
                    @else
                        <p class="mt-2 text-ink2">ঐচ্ছিক, তবে আমরা জোর দিয়ে বলি। কেউ আপনার পাসওয়ার্ড জেনে গেলেও আপনার ফোনের কোড ছাড়া ঢুকতে পারবে না।</p>
                        <form method="POST" action="{{ Url::to('/account/2fa/start') }}" class="mt-4">@csrf
                            <button class="h-11 rounded-full bg-red px-6 font-bold text-white">চালু করুন</button>
                        </form>
                    @endif
                </div>
                <div class="sm:rounded-3xl sm:border-2 sm:border-navy bg-white sm:p-5">
                    <h3 class="text-xl font-extrabold">পাসওয়ার্ড বদলান</h3>
                    <form method="POST" action="{{ Url::to('/account/password') }}" class="mt-3">@csrf
                        <x-field name="current_password" type="password" label="এখনকার পাসওয়ার্ড" autocomplete="current-password" required />
                        <x-field name="password" type="password" label="নতুন পাসওয়ার্ড" autocomplete="new-password" required hint="অন্তত ১০ অক্ষর, অক্ষর আর সংখ্যা দুই-ই।" />
                        <x-field name="password_confirmation" type="password" label="নতুন পাসওয়ার্ড আবার" autocomplete="new-password" required />
                        <button class="h-11 rounded-full border-2 border-navy px-6 font-bold">বদলান</button>
                    </form>
                    <p class="mt-3 text-sm text-ink2">বদলালে অন্য সব ফোন/কম্পিউটার থেকে লগ-আউট হয়ে যাবে।</p>
                </div>
            </div>
            </div>
        </details>
        <details id="letters" class="group scroll-mt-20 rounded-3xl border-2 border-navy bg-white" data-settings-card>
            <summary class="flex min-h-16 cursor-pointer list-none items-center gap-3 px-5 py-3 [&::-webkit-details-marker]:hidden">
                <span class="min-w-0 flex-1"><span class="block text-xl font-extrabold">চিঠি</span><span class="text-sm text-ink2">হাতপাখার সাপ্তাহিক চিঠি {{ $user->mail_opt_out ? '(বন্ধ)' : '(চালু)' }}</span></span>
                <span class="text-2xl transition group-open:rotate-180" aria-hidden="true">▾</span>
            </summary>
            <div class="border-t-2 border-rule p-3 sm:p-5">
            <form method="POST" action="{{ Url::to('/account/newsletter') }}" class="flex flex-wrap items-center justify-between gap-4 rounded-3xl border-2 border-navy bg-white p-5">
                @csrf
                <div class="min-w-0">
                    <strong class="block text-lg">হাতপাখার চিঠি</strong>
                    <span class="text-ink2">সপ্তাহে একটি: নতুন লেখার খবর; মাঝে মাঝে লেখকদের জন্য পরের সংখ্যার বিষয় আর প্রতিযোগিতার খবর।</span>
                    <span class="mt-1 block text-sm font-bold {{ $user->mail_opt_out ? 'text-red' : 'text-sky-night' }}">এখন: {{ $user->mail_opt_out ? 'বন্ধ' : 'চালু' }}</span>
                </div>
                <input type="hidden" name="newsletter" value="{{ $user->mail_opt_out ? 1 : 0 }}">
                <button class="h-11 flex-none rounded-full {{ $user->mail_opt_out ? 'bg-red text-white' : 'border-2 border-navy' }} px-6 font-bold">{{ $user->mail_opt_out ? 'চালু করুন' : 'বন্ধ করুন' }}</button>
            </form>
            <p class="mt-3 text-sm text-ink2">নিজের লেখার খবর (মনোনয়ন, বদলের অনুরোধ) চিঠি বন্ধ থাকলেও আসবে।</p>
            </div>
        </details>
        @if (\App\Support\WebPush::publicKey())
        {{-- D127: phone notifications for followed serials and writers. The button's state is this browser's; the script fills it in. --}}
        <details id="phone" class="group scroll-mt-20 rounded-3xl border-2 border-navy bg-white" data-settings-card>
            <summary class="flex min-h-16 cursor-pointer list-none items-center gap-3 px-5 py-3 [&::-webkit-details-marker]:hidden">
                <span class="min-w-0 flex-1"><span class="block text-xl font-extrabold">ফোনে খবর</span><span class="text-sm text-ink2">নতুন পর্ব আর প্রিয় লেখকের নতুন লেখা</span></span>
                <span class="text-2xl transition group-open:rotate-180" aria-hidden="true">▾</span>
            </summary>
            <div class="border-t-2 border-rule p-3 sm:p-5">
                <div class="rounded-3xl border-2 border-navy bg-white p-5">
                    <p>যে ধারাবাহিক বা লেখককে অনুসরণ করছেন, নতুন লেখা এলেই এই ফোনে (বা কম্পিউটারে) একটি ছোট খবর আসবে। ছুঁয়ে দিলেই লেখাটি খুলবে।</p>
                    <p class="mt-3 font-bold" data-push-status role="status"></p>
                    <div class="mt-4 flex flex-col gap-3 sm:items-start">
                        <x-push-button topic="account" off="🔔 অনুসরণ করা লেখার খবর এই ফোনে চালু করুন" on="✓ অনুসরণ করা লেখার খবর চালু (বন্ধ করতে ছুঁয়ে দিন)" />
                        <x-push-button topic="daily" off="🔔 রোজ সন্ধ্যায় সব নতুন লেখার খবরও চাই" on="✓ রোজকার খবরও চালু (বন্ধ করতে ছুঁয়ে দিন)" />
                        <button type="button" class="h-11 rounded-full border-2 border-navy px-6 font-bold" data-push-test hidden>একটা পরীক্ষা পাঠান</button>
                    </div>
                    @php($pushSections = \App\Models\Section::where('is_active', true)->where('slug', '!=', \App\Models\Section::KIDS)->orderBy('sort')->get(['id', 'name_bn']))
            <p class="mt-5 font-bold">শুধু কিছু বিভাগের নতুন লেখা চান? যেটি চান ছুঁয়ে দিন (প্রতিটি নতুন লেখা প্রকাশ হলেই খবর):</p>
            <div class="mt-2 flex flex-wrap gap-2">
                @foreach ($pushSections as $ps)
                    <x-push-button :topic="'section:'.$ps->id" :off="$ps->name_bn" :on="'✓ '.$ps->name_bn" :small="true" />
                @endforeach
            </div>
                    <p class="mt-3 text-sm" data-push-unsupported hidden>এই ব্রাউজারে ফোনে খবর পাঠানো যায় না। Chrome, Firefox বা Edge-এ খুলে দেখুন।</p>
                    <p class="mt-3 text-sm text-ink2" data-push-ios hidden>আইফোনে: আগে Safari-তে <strong>শেয়ার</strong> (□↑) → <strong>Add to Home Screen</strong>। তারপর হোম স্ক্রিনের হাতপাখা আইকন থেকে খুলে এখানে এসে চালু করুন। <a href="{{ Url::to('/app') }}#iphone" class="underline">ছবিসহ দেখুন</a></p>
                    <p class="mt-3 text-sm text-ink2">প্রতিটি ফোন বা কম্পিউটারে আলাদা করে চালু করতে হয়। কাকে অনুসরণ করছেন, তা দেখুন <a href="{{ Url::to('/account/reading') }}" class="underline">পড়ার তালিকায়</a>।</p>
                </div>
            </div>
        </details>
        @endif
        </div>
        <form method="POST" action="{{ Url::to('/logout') }}" class="mt-10 border-t-2 border-rule pt-6 text-center">@csrf<button class="min-h-11 font-bold text-red underline">লগ-আউট</button></form>
    </x-account-shell>
</x-layouts.app>
