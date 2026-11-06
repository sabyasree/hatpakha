{{-- Settings (owner + UX review): three cards, one open at a time; the old #profile / #security / #letters links open the right one. --}}
@php
    use App\Support\Bn;
    use App\Support\Url;
    $avatar = $author?->avatar_path;
@endphp
<x-layouts.app :seo="$seo">
    <x-account-shell tab="settings" :user="$user" :author="$author" :counts="$counts" :two-factor-on="$twoFactorOn" :cert-count="$certCount">
        <div class="space-y-4">
        <details id="profile" class="group scroll-mt-20 rounded-3xl border-2 border-navy bg-white" data-settings-card>
            <summary class="flex min-h-16 cursor-pointer list-none items-center gap-3 px-5 py-3 [&::-webkit-details-marker]:hidden">
                <span class="min-w-0 flex-1"><span class="block text-xl font-extrabold">লেখক-প্রোফাইল</span><span class="text-sm text-ink2">ছবি, প্রচ্ছদ, নাম, নিজের কথা: আপনার লেখক-পাতায় যা দেখা যায়</span></span>
                <span class="text-2xl transition group-open:rotate-180" aria-hidden="true">▾</span>
            </summary>
            <div class="border-t-2 border-rule p-4 sm:p-5">
            <form method="POST" action="{{ Url::to('/account/profile') }}" enctype="multipart/form-data" class="overflow-hidden rounded-2xl border-2 border-rule bg-white">
                @csrf
                {{-- Live preview of cover + photo --}}
                <div class="relative h-36 bg-sky md:h-48">
                    <img data-preview="cover" @if ($author?->cover_path) src="{{ $author->cover_path }}" @endif alt="" class="{{ $author?->cover_path ? '' : 'hidden' }} h-full w-full object-cover">
                    @unless ($author?->cover_path)<div class="halftone absolute inset-0" aria-hidden="true"></div>@endunless
                    <label class="absolute right-3 top-3 flex h-10 cursor-pointer items-center rounded-full bg-white/95 px-4 text-sm font-bold shadow">
                        প্রচ্ছদ বদলান<input type="file" name="cover" accept="image/jpeg,image/png,image/webp" data-preview-input="cover" class="sr-only"></label>
                </div>
                <div class="px-5 pb-5">
                    <div class="-mt-12 flex items-end gap-4">
                        <div class="relative">
                            <img data-preview="avatar" @if ($avatar) src="{{ $avatar }}" @endif alt="" class="{{ $avatar ? '' : 'hidden' }} h-24 w-24 rounded-full border-4 border-white object-cover outline outline-2 outline-navy">
                            @unless ($avatar)<span data-preview-fallback="avatar" class="flex h-24 w-24 items-center justify-center rounded-full border-4 border-white bg-gradient-to-br from-peach to-peach-deep text-4xl font-extrabold outline outline-2 outline-navy" aria-hidden="true">{{ mb_substr($user->name, 0, 1) }}</span>@endunless
                        </div>
                        <label class="mb-1 flex h-10 cursor-pointer items-center rounded-full border-2 border-navy px-4 text-sm font-bold">
                            ছবি দিন<input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" data-preview-input="avatar" class="sr-only"></label>
                    </div>
                    <p class="mt-2 text-xs text-ink2">ছবি: বর্গাকার ভালো (অন্তত ২৪০×২৪০)। প্রচ্ছদ: চওড়া ছবি (৩:১, অন্তত ৭৫০ পিক্সেল)। JPG/PNG/WebP, ৮ MB পর্যন্ত।
                        @if ($avatar)<label class="ml-2 inline-flex items-center gap-1"><input type="checkbox" name="remove_avatar" value="1"> ছবি সরান</label>@endif
                        @if ($author?->cover_path)<label class="ml-2 inline-flex items-center gap-1"><input type="checkbox" name="remove_cover" value="1"> প্রচ্ছদ সরান</label>@endif
                    </p>

                    <div class="mt-5 grid gap-x-5 sm:grid-cols-2">
                        <x-field name="name" label="ছাপার নাম" :value="$user->name" maxlength="80" required hint="লেখার সঙ্গে এই নাম ছাপা হয়।" />
                        <x-field name="name_bn" label="বাংলায় নাম (ঐচ্ছিক)" :value="$author?->display_name_bn" maxlength="80" hint="থাকলে লেখক-পাতায় এটাই দেখাবে।" />
                        <x-field name="city" label="শহর (ঐচ্ছিক)" :value="$author?->city" maxlength="60" />
                        <x-field name="website" type="url" label="ওয়েবসাইট (ঐচ্ছিক)" :value="$author?->links['website'] ?? null" placeholder="https://" />
                        <x-field name="facebook" type="url" label="ফেসবুক (ঐচ্ছিক)" :value="$author?->links['facebook'] ?? null" placeholder="https://facebook.com/…" />
                        <x-field name="instagram" type="url" label="ইনস্টাগ্রাম (ঐচ্ছিক)" :value="$author?->links['instagram'] ?? null" placeholder="https://instagram.com/…" />
                    </div>
                    <label class="block">
                        <span class="mb-1 block font-bold">নিজের কথা (ঐচ্ছিক)</span>
                        <textarea name="bio" rows="4" maxlength="1000" class="block w-full rounded-xl border-2 border-navy bg-white p-3 text-lg" placeholder="দু-চার লাইনে নিজের কথা: কী লেখেন, কোথা থেকে…">{{ old('bio', $author?->bio) }}</textarea>
                        <span class="mt-1 block text-sm text-ink2">১০০০ অক্ষর পর্যন্ত। সাধারণ লেখা; লিংক উপরের ঘরে দিন।</span>
                    </label>
                    <button class="mt-5 h-12 rounded-full bg-red px-8 font-bold text-white">প্রোফাইল রাখুন</button>
                </div>
            </form>
            </div>
        </details>
        <details id="security" class="group scroll-mt-20 rounded-3xl border-2 border-navy bg-white" data-settings-card>
            <summary class="flex min-h-16 cursor-pointer list-none items-center gap-3 px-5 py-3 [&::-webkit-details-marker]:hidden">
                <span class="min-w-0 flex-1"><span class="block text-xl font-extrabold">নিরাপত্তা</span><span class="text-sm text-ink2">দুই-ধাপের যাচাই {{ $twoFactorOn ? '(চালু)' : '(বন্ধ)' }} · পাসওয়ার্ড</span></span>
                <span class="text-2xl transition group-open:rotate-180" aria-hidden="true">▾</span>
            </summary>
            <div class="border-t-2 border-rule p-4 sm:p-5">
            <div class="grid gap-5 md:grid-cols-2">
                <div class="rounded-3xl border-2 border-navy bg-white p-5">
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
                <div class="rounded-3xl border-2 border-navy bg-white p-5">
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
            <div class="border-t-2 border-rule p-4 sm:p-5">
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
        </div>
        <form method="POST" action="{{ Url::to('/logout') }}" class="mt-10 border-t-2 border-rule pt-6 text-center">@csrf<button class="min-h-11 font-bold text-red underline">লগ-আউট</button></form>
    </x-account-shell>
</x-layouts.app>
