{{-- D166 (owner, UX-reviewed and tested 2026-11-13): "আরও" in the phone's bottom bar opens this sheet, "সব কিছু": the
     account first (each row says what is inside it; লগ-আউট right after), this month's issue, the quick links, every
     section in the open, then the rest. Closes with ✕, the dim area, the phone's back button, Esc, or "আরও" again. --}}
@php
    use App\Support\Url;
    $me = auth()->user();
    $acct = $me ? \App\Support\AccountMenu::for($me) : null;
    $issue = \App\Models\Issue::latestLive();
    $path = '/'.trim(request()->path(), '/');
    $icon = fn (string $d) => '<svg class="h-6 w-6 flex-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$d.'</svg>';
    $I = [
        'pen' => '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="M13.5 6.5l4 4"/>',
        'book' => '<path d="M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2z"/><path d="M4 19V5"/>',
        'person' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c1-4 4.5-6 8-6s7 2 8 6"/>',
        'key' => '<circle cx="8" cy="15" r="4"/><path d="M11 12l9-9M17 6l3 3M15 8l2 2"/>',
        'out' => '<path d="M15 4h4v16h-4M10 8l-4 4 4 4M6 12h10"/>',
        'issue' => '<rect x="5" y="3" width="14" height="18" rx="1.5"/><path d="M8 7h8M8 11h8M8 15h5"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/>',
        'archive' => '<rect x="3" y="4" width="18" height="5" rx="1"/><path d="M5 9v10h14V9M10 13h4"/>',
        'help' => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .8-1 1.5V14M12 17h.01"/>',
        'stack' => '<path d="M12 3l9 5-9 5-9-5z"/><path d="M3 13l9 5 9-5"/>',
        'phone' => '<rect x="7" y="2" width="10" height="20" rx="2"/><path d="M11 18h2"/>',
    ];
    $row = 'flex min-h-14 items-center gap-3 border-b border-rule px-4 py-2 text-lg font-bold hover:bg-peach-light';
    $accountRows = $me ? array_values(array_filter([
        $acct['writer'] ? ['/account', 'pen', __('আমার লেখা'), __('পাঠানো লেখা, সম্পাদকের নোট, ড্রাফট')] : null,
        ['/account/reading', 'book', __('লাইব্রেরি'), __('সেভ করা লেখা, পড়া চালিয়ে যান, ফলো করছেন')],
        ['/account/profile', 'person', __('প্রোফাইল'), $acct['writer'] ? __('ছবি, নাম, নিজের কথা') : __('ছবি, নাম')],
        ['/account/settings', 'key', __('সেটিংস'), __('ই-মেল, পাসওয়ার্ড, সাপ্তাহিক ই-মেল, ফোনে নোটিফিকেশন')],
        $acct['reviewer'] ? ['/account/review', 'book', __('রিভিউ করার লেখা'), $acct['toReview'] ? __(':countটি লেখা পড়া বাকি', ['count' => \App\Support\Bn::num((string) $acct['toReview'])]) : __('সম্পাদকরা যা পড়তে দিয়েছেন')] : null,   // D168
    ])) : [];
@endphp
<div data-more-sheet hidden class="md:hidden">
    <button type="button" data-more-close class="fixed inset-0 z-30 block h-full w-full cursor-default bg-navy/55" aria-label="{{ __('মেনু বন্ধ করুন') }}" tabindex="-1"></button>
    <section id="more-sheet" role="dialog" aria-modal="true" aria-labelledby="more-sheet-h"
        class="fixed inset-x-0 bottom-[calc(4rem+env(safe-area-inset-bottom))] z-[35] flex max-h-[calc(100dvh-9rem-env(safe-area-inset-bottom))] flex-col rounded-t-3xl border-t-2 border-navy bg-paper">
        <div class="flex flex-none items-center justify-between border-b-2 border-rule px-4 py-3">
            <h2 id="more-sheet-h" class="font-display text-xl font-extrabold">{{ __('মেনু') }}</h2>
            <button type="button" data-more-close class="flex h-11 items-center gap-1 rounded-full border-2 border-navy bg-white px-4 font-bold">{{ __('✕ বন্ধ') }}</button>
        </div>
        <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain pb-4">
            {{-- Owner, 2026-11-14 ("পাঠকের কলম and games are not in the menu"): the two things to do every day come first,
                 above the account, for everyone. --}}
            <div class="grid grid-cols-2 gap-2 px-3 pt-3">
                <a href="{{ Url::to('/khela') }}" class="flex min-h-16 flex-col justify-center rounded-2xl border-2 border-navy bg-peach-light px-3 py-2 font-bold hover:bg-peach">
                    <span class="text-lg leading-tight">{{ __('খেলা') }}</span><span class="text-sm font-medium text-ink2">{{ __('রোজের ধাঁধা ও শব্দের খেলা') }}</span></a>
                <a href="{{ Url::to('/kolom') }}" class="flex min-h-16 flex-col justify-center rounded-2xl border-2 border-navy bg-sky-tint px-3 py-2 font-bold hover:bg-sky-light">
                    <span class="text-lg leading-tight">{{ __('পাঠকের কলম') }}</span><span class="text-sm font-medium text-ink2">{{ __('এ সপ্তাহে আপনিও লিখুন') }}</span></a>
            </div>
            @if ($me)
                <div class="flex items-center gap-3 px-4 py-3">
                    @if ($acct['avatar'])<img src="{{ $acct['avatar'] }}" alt="" class="h-12 w-12 flex-none rounded-full border-2 border-navy object-cover">
                    @else<span class="flex h-12 w-12 flex-none items-center justify-center rounded-full border-2 border-navy bg-peach text-xl font-extrabold" aria-hidden="true">{{ $acct['initial'] }}</span>@endif
                    <span class="text-xl font-extrabold">{{ __('নমস্কার, :name', ['name' => $acct['first']]) }}</span>
                </div>
                @foreach ($accountRows as [$href, $ic, $label, $sub])
                    @php($here = $path === $href)
                    <a href="{{ Url::to($href) }}" class="{{ $row }}" @if ($here) aria-current="page" @endif>
                        {!! $icon($I[$ic]) !!}
                        <span class="min-w-0 flex-1">{{ $label }}<span class="block text-sm font-medium text-ink2">{{ $sub }}</span></span>
                        @if ($here)<span class="flex-none rounded-full bg-navy px-2.5 py-0.5 text-sm text-white">{{ __('এখানে আছেন') }}</span>
                        @elseif ($ic === 'pen' && $acct['act'] > 0)<span class="flex-none text-sm text-red">● {{ __(':countটি কাজ আছে', ['count' => \App\Support\Bn::num((string) $acct['act'])]) }} ›</span>
                        @else<span class="flex-none text-ink2" aria-hidden="true">›</span>@endif
                    </a>
                    @if ($ic === 'pen')
                        <a href="{{ Url::to('/submit') }}" class="{{ $row }} text-red"><span class="flex h-6 w-6 flex-none items-center justify-center rounded-full bg-red text-lg text-white" aria-hidden="true">+</span><span class="flex-1">{{ __('নতুন লেখা পাঠান') }}</span><span aria-hidden="true">›</span></a>
                    @endif
                @endforeach
                {{-- D169: the menus and account in বাংলা or English --}}
                <div class="flex min-h-14 items-center gap-3 border-b border-rule px-4 py-2"><span class="flex-1 text-lg font-bold">{{ __('ভাষা') }} <span class="text-ink2">· Language</span></span>@include('partials.language-switch')</div>
                <form method="POST" action="{{ Url::to('/logout') }}">@csrf
                    <button class="{{ $row }} w-full text-left">{!! $icon($I['out']) !!}<span class="flex-1">{{ __('লগ আউট') }}<span class="block text-sm font-medium text-ink2">{{ __('পরে আবার ই-মেল আর পাসওয়ার্ড দিয়ে সাইন ইন করবেন') }}</span></span></button>
                </form>
            @else
                <div class="px-4 pb-2 pt-4">
                    <p>{{ __('অ্যাকাউন্ট থাকলে লেখা সেভ করে পরে পড়তে পারবেন, আর কোথায় থেমেছিলেন তা মনে থাকবে।') }}</p>
                    <a href="{{ Url::to('/login') }}" class="mt-3 flex h-12 items-center justify-center rounded-full bg-red font-bold text-white shadow-[3px_3px_0_#13212C]">{{ __('সাইন ইন') }}</a>
                    <p class="mt-2 text-center text-ink2">{{ __('নতুন?') }} <a href="{{ Url::to('/register') }}" class="font-bold text-sky-night underline">{{ __('সাইন আপ') }}</a> {{ __('(বিনা পয়সায়)') }}</p>
                </div>
            @endif

            @if ($issue)
                <a href="{{ $issue->url() }}" class="mx-4 mt-3 flex min-h-14 items-center gap-3 rounded-2xl bg-red px-4 text-lg font-bold text-white">{!! $icon($I['issue']) !!}<span class="flex-1">{{ __('এই মাসের সংখ্যা: :title', ['title' => $issue->title]) }}</span><span aria-hidden="true">›</span></a>
            @endif
            <div class="mt-2">
                <a href="{{ Url::to('/search') }}" class="{{ $row }}">{!! $icon($I['search']) !!}<span class="flex-1">{{ __('সার্চ') }}</span><span class="text-ink2" aria-hidden="true">›</span></a>
                <a href="{{ Url::to('/archive') }}" class="{{ $row }}">{!! $icon($I['archive']) !!}<span class="flex-1">{{ __('পুরনো লেখা') }}<span class="block text-sm font-medium text-ink2">{{ __(':year থেকে, বছর ধরে (আর্কাইভ)', ['year' => \App\Support\Bn::num(2012)]) }}</span></span><span class="text-ink2" aria-hidden="true">›</span></a>
                @if ($issue)<a href="{{ Url::to('/sonkha') }}" class="{{ $row }}">{!! $icon($I['issue']) !!}<span class="flex-1">{{ __('সব সংখ্যা') }}</span><span class="text-ink2" aria-hidden="true">›</span></a>@endif
                <a href="{{ Url::to('/contact-us') }}" class="{{ $row }}">{!! $icon($I['help']) !!}<span class="flex-1">{{ __('সাহায্য ও যোগাযোগ') }}</span><span class="text-ink2" aria-hidden="true">›</span></a>
            </div>
            @foreach ($menu['groups'] as $g)
                <p class="px-4 pb-1 pt-4 text-sm font-extrabold text-ink2">{{ __($g['label']) }}</p>
                <ul class="grid grid-cols-2 gap-1.5 px-3">
                    @foreach ($g['links'] as [$name, $href, $lang])
                        <li><a href="{{ $href }}" @if ($lang === 'en') lang="en" @endif class="flex min-h-12 items-center rounded-xl border border-rule bg-white px-3 font-bold leading-tight hover:bg-peach-light">{{ $name }}</a></li>
                    @endforeach
                </ul>
            @endforeach
            <p class="px-4 pb-1 pt-4 text-sm font-extrabold text-ink2">{{ __('আরও') }}</p>
            @if (\App\Models\Collection::live())
                <a href="{{ Url::to('/sonkolon') }}" class="{{ $row }}">{!! $icon($I['stack']) !!}<span class="flex-1">{{ __('বিষয় ধরে বাছাই করা লেখা') }}<span class="block text-sm font-medium text-ink2">{{ __('সংকলন') }}</span></span><span class="text-ink2" aria-hidden="true">›</span></a>
            @endif
            <a href="{{ Url::to('/app') }}" data-app-link class="{{ $row }} border-b-0">{!! $icon($I['phone']) !!}<span class="flex-1">{{ __('হাতপাখা ফোনে রাখুন') }}<span class="block text-sm font-medium text-ink2">{{ __('অ্যাপের মতো, হোম স্ক্রিন থেকে খুলুন') }}</span></span><span class="text-ink2" aria-hidden="true">›</span></a>
        </div>
    </section>
</div>
