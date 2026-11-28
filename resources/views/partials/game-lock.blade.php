{{-- D243 (owner: "without account they can try the easy ones; for full access they need an account"): shown to a
     guest in place of a game that needs an account. $what: the game's name; $easy: [label, url] of the open one, or null. --}}
@php($next = rawurlencode('/'.trim(request()->path(), '/').'/'))
<div class="mt-5 rounded-3xl border-2 border-navy bg-peach-light p-5 shadow-[4px_4px_0_#13212C]">
    <p class="font-hand text-2xl text-red">এটা খেলতে অ্যাকাউন্ট লাগে</p>
    <h2 class="mt-1 font-display text-2xl font-extrabold leading-tight">{{ $what }} খেলুন অ্যাকাউন্ট খুলে</h2>
    <ul class="mt-3 space-y-1 text-lg">
        <li>✓ সব খেলা খোলা, রোজ ৩০০ পয়েন্ট পর্যন্ত</li>
        <li>✓ সেরাদের তালিকায় নাম, মাসের সেরা আর বছরের সেরার খেতাব</li>
        <li>✓ ব্যাজ আর টানা দিনের হিসেব</li>
    </ul>
    <p class="mt-3 text-lg">অ্যাকাউন্ট খুলতে লাগে শুধু নাম, ই-মেল আর একটা পাসওয়ার্ড। লাগে এক মিনিট, টাকা লাগে না।</p>
    <div class="mt-4 grid gap-2">
        <a href="{{ \App\Support\Url::to('/register') }}?next={{ $next }}" class="flex h-14 items-center justify-center rounded-full bg-red px-6 text-lg font-bold text-white shadow-[3px_3px_0_#13212C]">বিনামূল্যে অ্যাকাউন্ট খুলুন</a>
        <a href="{{ \App\Support\Url::to('/login') }}?next={{ $next }}" class="flex h-12 items-center justify-center rounded-full border-2 border-navy bg-white px-6 font-bold">আগে থেকে আছে? সাইন ইন</a>
    </div>
    @if (! empty($easy))
        <p class="mt-5 border-t-2 border-dashed border-navy pt-4 text-lg font-bold">এখন অ্যাকাউন্ট খুলতে না চাইলে:</p>
        <a href="{{ $easy[1] }}" class="mt-2 flex h-14 items-center justify-center rounded-full border-2 border-navy bg-sky-tint px-6 text-lg font-bold">{{ $easy[0] }} খেলুন →</a>
    @endif
</div>
