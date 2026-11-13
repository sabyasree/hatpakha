@props(['tab', 'user', 'author', 'counts', 'twoFactorOn', 'certCount' => 0])
{{-- The account's frame (D166, owner + UX review + tests with older users): a greeting, then 3 big labelled tabs for a
     reader (আমার পড়া · আমার পাতা · ই-মেল ও পাসওয়ার্ড) or 4 for a writer (আমার লেখা first). Plain words, no jargon. --}}
@php
    use App\Support\Url;
    request()->attributes->set('hp.compact-footer', true);
    $acct = \App\Support\AccountMenu::for($user);
    $active = ['home' => 'lekha', 'writings' => 'lekha', 'stats' => 'lekha', 'reading' => 'pora', 'profile' => 'pata', 'settings' => 'aro'][$tab] ?? $tab;
    $svg = fn (string $d) => '<svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$d.'</svg>';
    $tabs = array_filter([
        'lekha' => $acct['writer'] ? ['আমার লেখা', Url::to('/account'), '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="M13.5 6.5l4 4"/>'] : null,
        'pora' => ['আমার পড়া', Url::to('/account/reading'), '<path d="M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2z"/><path d="M4 19V5"/><path d="M12 8.5c-1-1.6-3.6-1.2-3.6.9 0 1.7 2.2 3 3.6 4.1 1.4-1.1 3.6-2.4 3.6-4.1 0-2.1-2.6-2.5-3.6-.9z"/>'],
        'pata' => ['আমার পাতা', Url::to('/account/profile'), '<circle cx="12" cy="8" r="4"/><path d="M4 21c1-4 4.5-6 8-6s7 2 8 6"/>'],
        'aro' => ['ই-মেল ও পাসওয়ার্ড', Url::to('/account/settings'), '<circle cx="8" cy="15" r="4"/><path d="M11 12l9-9M17 6l3 3M15 8l2 2"/>'],
    ]);
@endphp
<section class="mx-auto max-w-[1000px] px-5 pt-5">
    <div class="flex items-start justify-between gap-3">
        <h1 class="font-display text-3xl font-extrabold leading-tight">নমস্কার, {{ $acct['first'] }}</h1>
        @include('partials.chat-button')
    </div>
    @if ($acct['writer'])
        @php($published = (int) ($counts['published'] ?? 0))
        @php($review = (int) ($counts['pending'] ?? 0) + (int) ($counts['scheduled'] ?? 0))
        <p class="mt-1 text-ink2">{{ \App\Support\Bn::num((string) $published) }}টি লেখা প্রকাশিত@if ($review) · {{ \App\Support\Bn::num((string) $review) }}টি সম্পাদকের কাছে@endif</p>
    @else
        <p class="mt-1 text-ink2">যা পড়ছেন, যা পরে পড়বেন, যাঁদের পড়েন: সব এক জায়গায়।</p>
    @endif
</section>

<nav aria-label="আমার হাতপাখা" class="mt-4 border-y-2 border-navy bg-paper">
    <div class="mx-auto grid max-w-[1000px] {{ count($tabs) === 4 ? 'grid-cols-4' : 'grid-cols-3' }}">
        @foreach ($tabs as $key => [$label, $href, $icon])
            <a href="{{ $href }}" @if ($active === $key) aria-current="page" @endif
                class="flex min-h-16 flex-col items-center justify-center gap-0.5 border-b-4 px-1 py-1.5 text-center text-[15px] font-bold leading-tight {{ $active === $key ? 'border-red bg-peach-light text-red' : 'border-transparent hover:text-red' }}">{!! $svg($icon) !!}<span>{{ $label }}</span></a>
        @endforeach
    </div>
</nav>

<div class="mx-auto max-w-[1000px] px-5 pb-8 pt-5">
    @if (session('submitted'))
        <div role="status" class="mb-5 rounded-3xl border-2 border-navy bg-white p-5 shadow-[4px_4px_0_#13212C]">
            <div class="font-hand text-2xl text-red">জমা পড়েছে!</div>
            <div class="mt-1 font-serif text-xl font-bold">“{{ session('submitted') }}”</div>
            <p class="mt-2 text-ink2">সম্পাদকমণ্ডলী পড়ে দেখে ই-মেলে জানাবেন। ধন্যবাদ!</p>
            <div class="mt-4 flex flex-wrap gap-2">
                <a href="{{ Url::to('/account') }}" class="flex h-11 items-center rounded-full bg-navy px-5 font-bold text-white">আমার লেখা দেখুন</a>
                <a href="{{ Url::to('/submit') }}" class="flex h-11 items-center rounded-full border-2 border-navy px-5 font-bold">আরেকটা লিখুন</a>
            </div>
        </div>
    @elseif (session('status'))
        <div role="status" class="mb-5 flex items-start gap-2 rounded-2xl bg-navy p-4 font-bold text-white"><span aria-hidden="true">✓</span><span>{{ session('status') }}</span></div>
    @endif
    @if ($errors->any())
        <div role="alert" class="mb-5 rounded-xl border-2 border-red bg-peach-light p-4 font-semibold text-red-dark">@foreach ($errors->all() as $e)<p>{{ $e }}</p>@endforeach</div>
    @endif
    {{ $slot }}
</div>
@include('partials.chat')
