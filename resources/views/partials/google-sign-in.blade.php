{{-- D235: one tap in place of name, password and the verification e-mail. Shown only when the Google key is set.
     $new: on the sign-up page the words say "open an account" (UX review: "ঢুকুন" alone reads as "for members"). --}}
@if (\App\Auth\GoogleSignIn::ready())
    <a href="{{ \App\Support\Url::to('/login/google') }}" rel="nofollow" class="flex min-h-14 w-full items-center justify-center gap-3 rounded-full border-2 border-navy bg-white px-5 text-lg font-bold shadow-[3px_3px_0_#13212C] hover:bg-peach-light">
        <span aria-hidden="true" class="flex h-8 w-8 items-center justify-center rounded-full bg-navy font-display text-lg font-extrabold text-white">G</span>
        <span>{{ ($new ?? false) ? __('Google দিয়ে অ্যাকাউন্ট খুলুন') : __('Google দিয়ে ঢুকুন') }}</span></a>
    <p class="mt-3 text-center font-semibold">{{ __('সবচেয়ে সহজ উপায়: পাসওয়ার্ড লাগে না, ই-মেল যাচাইও করতে হয় না।') }}</p>
    <p class="mt-1 text-center text-sm text-ink2">{{ __('এভাবে ঢুকলে আপনি মেনে নিচ্ছেন:') }} <a href="{{ \App\Support\Url::to('/terms-conditions') }}" class="inline-block py-2 underline">{{ __('শর্তাবলি') }}</a> · <a href="{{ \App\Support\Url::to('/privacy-policy') }}" class="inline-block py-2 underline">{{ __('প্রাইভেসি পলিসি') }}</a></p>
    <div class="my-5 flex items-center gap-3 text-sm font-bold text-ink2"><span class="h-px flex-1 bg-rule" aria-hidden="true"></span>{{ ($new ?? false) ? __('অথবা নিজে ফর্ম ভরুন') : __('অথবা ই-মেল আর পাসওয়ার্ড দিয়ে') }}<span class="h-px flex-1 bg-rule" aria-hidden="true"></span></div>
@endif
