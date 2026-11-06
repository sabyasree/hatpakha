@props(['tab', 'user', 'author', 'counts', 'twoFactorOn', 'certCount' => 0])
{{-- The account's frame (owner + UX review): a compact header, messages, and three real tabs. --}}
@php
    $avatar = $author?->avatar_path;
    $tabs = [
        'home' => ['সারসংক্ষেপ', \App\Support\Url::to('/account')],
        'writings' => ['লেখা', \App\Support\Url::to('/account/writings')],
        'profile' => ['প্রোফাইল', \App\Support\Url::to('/account/profile')],
        'reading' => ['পড়ার তালিকা', \App\Support\Url::to('/account/reading')],
        'settings' => ['সেটিংস', \App\Support\Url::to('/account/settings')],
    ];
@endphp
<div class="h-1 bg-gradient-to-r from-pink via-red to-peach" aria-hidden="true"></div>
<section class="mx-auto max-w-[1000px] px-5 pt-6">
    <div class="flex items-center gap-3">
        @if ($avatar)
            <img src="{{ $avatar }}" alt="" class="h-12 w-12 flex-none rounded-full border-2 border-navy object-cover">
        @else
            <span class="flex h-12 w-12 flex-none items-center justify-center rounded-full border-2 border-navy bg-gradient-to-br from-peach to-peach-deep text-xl font-extrabold" aria-hidden="true">{{ mb_substr($user->name, 0, 1) }}</span>
        @endif
        <div class="min-w-0 flex-1">
            <h1 class="truncate font-display text-2xl font-extrabold leading-tight">{{ $author?->publicName() ?: $user->name }}</h1>
            <div class="flex flex-wrap items-center gap-x-2 text-sm text-ink2">
                @if ($user->hasVerifiedEmail())<span class="font-bold text-sky-night">✓ যাচাই করা</span>@endif
                @if ($author && $author->published_count > 0)<a href="{{ $author->url() }}" class="font-bold text-sky-deep underline">আমার লেখক-পাতা</a>@endif
            </div>
        </div>
    </div>
    @if ($tab === 'home')
        <a href="{{ \App\Support\Url::to('/submit') }}" class="mt-4 flex h-12 w-full items-center justify-center rounded-full bg-red font-bold text-white shadow-[3px_3px_0_#13212C] sm:w-auto sm:px-8">+ নতুন লেখা পাঠান</a>
    @endif

    @if (session('submitted'))
        <div role="status" class="mt-5 rounded-3xl border-2 border-navy bg-white p-5 shadow-[4px_4px_0_#13212C]">
            <div class="font-hand text-2xl text-red">জমা পড়েছে!</div>
            <div class="mt-1 font-serif text-xl font-bold">“{{ session('submitted') }}”</div>
            <p class="mt-2 text-ink2">সম্পাদকমণ্ডলী পড়ে দেখে ই-মেলে জানাবেন। ধন্যবাদ!</p>
            <div class="mt-4 flex flex-wrap gap-2">
                <a href="{{ \App\Support\Url::to('/account/writings') }}?status=review" class="flex h-11 items-center rounded-full bg-navy px-5 font-bold text-white">আমার লেখা দেখুন</a>
                <a href="{{ \App\Support\Url::to('/submit') }}" class="flex h-11 items-center rounded-full border-2 border-navy px-5 font-bold">আরেকটা লিখুন</a>
            </div>
        </div>
    @elseif (session('status'))
        <div role="status" class="mt-5 rounded-xl border-2 border-sky bg-sky-tint p-4 font-semibold text-sky-night">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div role="alert" class="mt-5 rounded-xl border-2 border-red bg-peach-light p-4 font-semibold text-red-dark">@foreach ($errors->all() as $e)<p>{{ $e }}</p>@endforeach</div>
    @endif
</section>

<nav aria-label="অ্যাকাউন্ট" class="sticky top-0 z-30 mt-5 border-y-2 border-navy bg-paper/95 backdrop-blur">
    {{-- Phones: five equal tabs, all visible; wider screens: a row from the left --}}
    <div class="mx-auto grid max-w-[1000px] grid-cols-5 sm:flex sm:px-2">
        @foreach ($tabs as $key => [$label, $href])
            <a href="{{ $href }}" @if ($tab === $key) aria-current="page" @endif
                class="flex h-12 items-center justify-center border-b-4 px-0.5 text-center text-[13px] font-bold leading-tight sm:px-5 sm:text-base {{ $tab === $key ? 'border-red text-red' : 'border-transparent hover:text-red' }}">{{ $label }}</a>
        @endforeach
    </div>
</nav>

<div class="mx-auto max-w-[1000px] px-5 pb-20 pt-6">
    {{ $slot }}
</div>
