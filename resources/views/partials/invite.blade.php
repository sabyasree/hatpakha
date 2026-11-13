{{-- D184: "হাতপাখার বন্ধু": the member's invite link, with this week's reading and playing as ready words to share. --}}
@php
    $week = \App\Support\Invites::week($user);
    $link = \App\Support\Invites::url($user);
    $friends = \App\Support\Invites::count($user);
@endphp
<h2 id="invite" class="mt-10 scroll-mt-24 font-display text-2xl font-extrabold">{{ __('বন্ধুকে হাতপাখায় আনুন') }}</h2>
<p class="mt-1 text-ink2">{{ __('আপনার লিংক দিয়ে কোনো বন্ধু এসে সদস্য হলে, আর একটি লেখা পড়লে বা খেলা খেললে, আপনি "বন্ধু" ব্যাজ পাবেন: ১ জনে ব্রোঞ্জ, ৫ জনে রুপো, ১৫ জনে সোনা।') }}</p>
<div class="mt-3 max-w-xl rounded-2xl border-2 border-navy bg-white p-4">
    <p class="font-bold">{{ __('এ পর্যন্ত এসেছেন') }}: {{ \App\Support\Bn::num((string) $friends) }} {{ __('জন') }}</p>
    <p class="mt-2 rounded-xl bg-peach-light p-3 leading-relaxed">{{ $week['text'] }}</p>
    <div class="mt-3 flex flex-wrap gap-2">
        <a href="https://wa.me/?text={{ rawurlencode($week['text']) }}" target="_blank" rel="noopener" class="flex h-12 items-center rounded-full bg-red px-5 font-bold text-white shadow-[3px_3px_0_#13212C]">{{ __('হোয়াটসঅ্যাপে পাঠান') }}</a>
        <button type="button" data-copy="{{ $week['text'] }}" data-copied="{{ __('✓ কপি হয়েছে') }}" class="flex h-12 items-center rounded-full border-2 border-navy bg-white px-5 font-bold">{{ __('লেখাটি কপি করুন') }}</button>
        <button type="button" data-copy="{{ $link }}" data-copied="{{ __('✓ কপি হয়েছে') }}" class="flex h-12 items-center rounded-full border-2 border-navy bg-white px-5 font-bold">{{ __('শুধু লিংক কপি') }}</button>
    </div>
</div>
