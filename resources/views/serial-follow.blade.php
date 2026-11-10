{{-- D108: follow a serial for an e-mail when a new episode comes out. Guests are sent to sign in and come back. --}}
@auth
    <form method="POST" action="{{ route('serial.follow', $serial) }}">@csrf
        <button class="inline-flex h-12 items-center rounded-full {{ $following ? 'border-2 border-navy bg-white' : 'bg-red text-white' }} px-6 font-bold" aria-pressed="{{ $following ? 'true' : 'false' }}">
            {{ $following ? '✓ নতুন পর্বের খবর পাচ্ছেন' : 'নতুন পর্ব এলে জানান' }}</button>
    </form>
@else
    <a href="{{ \App\Support\Url::to('/login') }}?next={{ rawurlencode(parse_url($serial->url(), PHP_URL_PATH)) }}" class="inline-flex h-12 items-center rounded-full bg-red px-6 font-bold text-white">নতুন পর্ব এলে জানান</a>
@endauth
