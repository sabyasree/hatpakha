@props(['author'])
{{-- The writer box under a piece (D64): photo, name, honour, a little bio, follow, all pieces. --}}
@php
    $a = $author;
    $me = auth()->user();
    $following = $me && \Illuminate\Support\Facades\DB::table('follows')->where(['user_id' => $me->id, 'author_id' => $a->id])->exists();
    $canFollow = ! $a->is_desk && (! $me || $a->user_id !== $me->id);
    $since = \App\Models\Post::published()->whereHas('authors', fn ($q) => $q->whereKey($a->id))->min('posts.published_at');
@endphp
<section class="mt-10 overflow-hidden rounded-3xl border-2 border-navy bg-white" aria-label="লেখক">
    <div class="h-16 bg-sky {{ $a->cover_path ? '' : 'halftone' }}" @if ($a->cover_path) style="background:url('{{ $a->cover_path }}') center/cover" @endif></div>
    <div class="px-5 pb-5">
        <div class="-mt-9 flex items-end justify-between gap-3">
            <a href="{{ $a->url() }}" class="flex-none" aria-label="{{ $a->publicName() }}">
                @if ($a->avatar_path)
                    <img src="{{ $a->avatar_path }}" alt="" loading="lazy" class="h-[4.5rem] w-[4.5rem] rounded-full border-4 border-white object-cover outline outline-2 outline-navy">
                @else
                    <span class="flex h-[4.5rem] w-[4.5rem] items-center justify-center rounded-full border-4 border-white bg-gradient-to-br from-peach to-peach-deep text-3xl font-extrabold outline outline-2 outline-navy" aria-hidden="true">{{ $a->initial() }}</span>
                @endif
            </a>
            @if ($canFollow)
                <form method="POST" action="{{ route('follow', $a) }}" data-toggle="follow">
                    @csrf
                    @auth
                        <button class="flex h-10 items-center gap-1 rounded-full px-4 text-sm font-bold {{ $following ? 'border-2 border-navy' : 'bg-red text-white' }}" aria-pressed="{{ $following ? 'true' : 'false' }}"
                            data-on="✓ অনুসরণ করছেন" data-off="+ অনুসরণ করুন">{{ $following ? '✓ অনুসরণ করছেন' : '+ অনুসরণ করুন' }}</button>
                    @else
                        <a href="{{ \App\Support\Url::to('/login') }}?next={{ rawurlencode(parse_url($a->url(), PHP_URL_PATH)) }}" class="flex h-10 items-center rounded-full bg-red px-4 text-sm font-bold text-white">+ অনুসরণ করুন</a>
                    @endauth
                </form>
            @endif
        </div>
        <div class="mt-3">
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ $a->url() }}" class="font-display text-2xl font-extrabold leading-tight hover:text-red">{{ $a->publicName() }}</a>
                @if ($a->honour_label)<span class="rounded-full bg-peach-light px-2.5 py-0.5 text-xs font-extrabold text-red-dark">★ {{ $a->honour_label }}</span>@endif
            </div>
            <p class="mt-0.5 text-sm text-ink2">
                @unless ($a->is_desk){{ $a->is_guest ? 'অতিথি লেখক' : 'হাতপাখার লেখক' }} · @endunless
                @if ($a->city){{ $a->city }} · @endif
                {{ \App\Support\Bn::num((string) $a->published_count) }}টি লেখা
                @if ($since) · {{ \App\Support\Bn::year((int) \Illuminate\Support\Carbon::parse($since)->year) }} থেকে@endif
                @if ($a->followers_count) · {{ \App\Support\Bn::num((string) $a->followers_count) }} জন অনুসরণ করেন@endif
            </p>
            @if ($a->bio)<p class="mt-2 line-clamp-3 font-serif leading-relaxed">{{ $a->bio }}</p>@endif
            <a href="{{ $a->url() }}" class="mt-3 inline-flex h-10 items-center font-bold text-sky-night underline">{{ $a->publicName() }}-এর সব লেখা →</a>
        </div>
    </div>
</section>
