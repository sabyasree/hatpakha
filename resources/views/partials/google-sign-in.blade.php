{{-- D235: one tap in place of name, password and the verification e-mail. Shown only when the Google key is set. --}}
@if (\App\Auth\GoogleSignIn::ready())
    <a href="{{ \App\Support\Url::to('/login/google') }}" rel="nofollow" class="flex min-h-14 w-full items-center justify-center gap-3 rounded-full border-2 border-navy bg-white px-5 text-lg font-bold shadow-[3px_3px_0_#13212C] hover:bg-peach-light">
        <span aria-hidden="true" class="flex h-8 w-8 items-center justify-center rounded-full bg-navy font-display text-lg font-extrabold text-white">G</span>
        <span>{{ __('Google দিয়ে ঢুকুন') }}</span></a>
    <p class="mt-2 text-center text-sm text-ink2">{{ __('পাসওয়ার্ড লাগে না, ই-মেল যাচাইও করতে হয় না। ঢুকলে আপনি') }} <a href="{{ \App\Support\Url::to('/terms-conditions') }}" class="underline">{{ __('শর্তাবলি') }}</a> {{ __('আর') }} <a href="{{ \App\Support\Url::to('/privacy-policy') }}" class="underline">{{ __('প্রাইভেসি পলিসি') }}</a> {{ __('মেনে নিচ্ছেন।') }}</p>
    <div class="my-5 flex items-center gap-3 text-sm font-bold text-ink2" aria-hidden="true"><span class="h-px flex-1 bg-rule"></span>{{ __('অথবা') }}<span class="h-px flex-1 bg-rule"></span></div>
@endif
