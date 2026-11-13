{{-- D177: under a finished game: where the result went. Guests are invited to sign in (they can still play). --}}
@auth
    <p class="mt-1 text-center text-sm font-bold text-sky-night" role="status" data-khela-board-msg></p>
    <a href="{{ \App\Support\Url::to('/khela/board') }}" class="mt-1 flex min-h-11 items-center justify-center font-bold text-sky-night underline">লিডারবোর্ড দেখুন →</a>
@else
    <p class="mt-2 rounded-2xl bg-white p-3 text-center text-sm"><a href="{{ \App\Support\Url::to('/login') }}?next={{ rawurlencode('/'.trim(request()->path(), '/').'/') }}" class="font-bold text-sky-night underline">সাইন ইন</a> করে খেললে লিডারবোর্ডে নাম ওঠে, ব্যাজ জমে। <a href="{{ \App\Support\Url::to('/khela/board') }}" class="font-bold text-sky-night underline">লিডারবোর্ড দেখুন</a></p>
@endauth
