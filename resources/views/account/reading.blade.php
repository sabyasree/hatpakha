{{-- D166 "আমার পড়া" (now "লাইব্রেরি", D169) (a reader's first page; owner + UX review + tests with older users): where they stopped, what is new
     from those they follow, saved pieces, who they follow, then liked and recently read. Removing has "ফিরিয়ে আনুন". --}}
@php
    use App\Support\Bn;
    use App\Support\Url;
    $thumb = function ($p) {
        if (! $p->featured_image_path) {
            return null;
        }
        $v = \App\Support\Images::variants($p->featured_image_path);

        return $v ? reset($v) : asset(ltrim($p->featured_image_path, '/'));
    };
    $more = 'flex min-h-14 items-center justify-between gap-3 rounded-xl border-2 border-navy bg-white px-4 text-lg font-bold [&::-webkit-details-marker]:hidden';
@endphp
<x-layouts.app :seo="$seo">
    <x-account-shell tab="reading" :user="$user" :author="$author" :counts="$counts" :two-factor-on="$twoFactorOn" :cert-count="$certCount">
        {{-- D237: the reading habit, said kindly; never a scolding when a day is missed --}}
        @if ($readStreak && $readStreak['best'] > 0)
            <div class="mb-6 flex items-center gap-4 rounded-2xl border-2 border-navy bg-peach-light p-4" role="status">
                <span aria-hidden="true" class="flex h-14 w-14 flex-none items-center justify-center rounded-full bg-red font-display text-2xl font-extrabold text-white">{{ \App\Support\Bn::num((string) max(1, $readStreak['now'])) }}</span>
                <span>
                    @if ($readStreak['now'] >= 2)
                        <strong class="block text-lg leading-snug">{{ __('টানা :n দিন পড়ছেন', ['n' => \App\Support\Bn::num((string) $readStreak['now'])]) }}</strong>
                        <span class="text-ink2">{{ $readStreak['today'] ? __('আজকের পড়া হয়ে গেছে। কাল আবার আসুন।') : __('আজ একটা লেখা পড়লেই টানা দিন গোনা চলবে।') }}</span>
                    @elseif ($readStreak['now'] === 1)
                        <strong class="block text-lg leading-snug">{{ $readStreak['today'] ? __('আজ পড়েছেন') : __('কাল পড়েছিলেন') }}</strong>
                        <span class="text-ink2">{{ $readStreak['today'] ? __('কাল আবার এলে টানা দু’দিন হবে।') : __('আজ একটা লেখা পড়লে টানা দু’দিন হবে।') }}</span>
                    @else
                        <strong class="block text-lg leading-snug">{{ __('আজ একটা লেখা পড়ে নতুন করে শুরু করুন') }}</strong>
                    @endif
                    @if ($readStreak['best'] > $readStreak['now'])<span class="block text-sm text-ink2">{{ __('আপনার সেরা: টানা :n দিন', ['n' => \App\Support\Bn::num((string) $readStreak['best'])]) }}</span>@endif
                </span>
            </div>
        @endif
        @if ($unfinished->isNotEmpty())
            <h2 class="font-display text-2xl font-extrabold">{{ __('পড়া চালিয়ে যান') }}</h2>
            <p class="text-ink2">{{ __('লেখার নামে টিপুন, যেখানে থেমেছিলেন সেখান থেকেই খুলবে।') }}</p>
            <ul class="mt-3 divide-y divide-rule rounded-2xl border-2 border-navy bg-white px-4">
                @foreach ($unfinished as $p)
                    <li><a href="{{ $p->url() }}#resume-{{ $p->progress }}" class="flex items-center gap-3 py-3">
                        @if ($src = $thumb($p))<img src="{{ $src }}" alt="" loading="lazy" class="h-16 w-16 flex-none rounded-xl border-2 border-navy object-cover">@endif
                        <span class="min-w-0 flex-1"><span class="block font-serif text-lg font-bold leading-snug">{{ $p->title }}</span>
                            <span class="block text-sm text-ink2">{{ $p->authors->first()?->publicName() }} · {{ $p->section?->name_bn }}</span>
                            <span class="mt-1.5 block h-2 overflow-hidden rounded-full bg-peach-light" aria-hidden="true"><span class="block h-full rounded-full bg-red" style="width: {{ $p->progress }}%"></span></span>
                            <span class="text-sm text-ink2">{{ __(':percent% পড়েছেন', ['percent' => Bn::num((string) $p->progress)]) }}</span></span>
                    </a></li>
                @endforeach
            </ul>
        @endif

        @if ($fresh->isNotEmpty())
            <h2 class="mt-8 font-display text-2xl font-extrabold">{{ __('আপনার জন্য নতুন লেখা') }}</h2>
            <p class="text-ink2">{{ __('যাঁদের লেখা পড়েন, তাঁদের নতুন লেখা।') }}</p>
            <ul class="mt-3 divide-y divide-rule rounded-2xl border-2 border-navy bg-white px-4">
                @foreach ($fresh as $p)
                    <li><a href="{{ $p->url() }}" class="flex items-center gap-3 py-3">
                        <span class="min-w-0 flex-1"><span class="mr-1 rounded-full bg-red px-2 text-sm font-bold text-white">{{ __('নতুন') }}</span> <span class="font-serif text-lg font-bold leading-snug">{{ $p->title }}</span>
                            <span class="block text-sm text-ink2">{{ $p->serial ? __('ধারাবাহিক: :title', ['title' => $p->serial->title]) : $p->authors->first()?->publicName() }} · {{ $p->section?->name_bn }}</span></span>
                        <span aria-hidden="true">›</span>
                    </a></li>
                @endforeach
            </ul>
        @endif

        <h2 class="{{ $unfinished->isNotEmpty() || $fresh->isNotEmpty() ? 'mt-8' : '' }} font-display text-2xl font-extrabold">{{ __('সেভ করা লেখা') }} <span class="text-ink2">({{ Bn::num((string) $saved->total()) }})</span></h2>
        @if ($saved->isEmpty())
            <div class="mt-2 rounded-2xl border-2 border-dashed border-peach-deep p-4">
                <p>{{ __('পড়তে পড়তে লেখার নিচে “পরে পড়ব” টিপলে লেখাটি এখানে সেভ থাকবে।') }}</p>
                <a href="{{ url('/') }}/" class="mt-3 inline-flex h-12 items-center rounded-full bg-navy px-6 font-bold text-white">{{ __('পড়া শুরু করুন') }}</a>
            </div>
        @else
            <p class="text-sm text-ink2">{{ __('ফোনে হাতপাখা রাখা থাকলে এই লেখাগুলো ইন্টারনেট ছাড়াও পড়া যায়।') }}</p>
            <ul class="mt-3 divide-y divide-rule rounded-2xl border-2 border-navy bg-white px-4" data-list data-saved-list>
                @foreach ($saved as $p)
                    <li class="flex items-center gap-3 py-3" data-row>
                        @if ($src = $thumb($p))<img src="{{ $src }}" alt="" loading="lazy" class="h-16 w-16 flex-none rounded-xl border-2 border-navy object-cover">@endif
                        <a href="{{ $p->url() }}" class="min-w-0 flex-1">
                            <span class="block font-serif text-lg font-bold leading-snug">{{ $p->title }}</span>
                            <span class="text-sm text-ink2">{{ $p->authors->first()?->publicName() }} · {{ $p->section?->name_bn }}</span>
                        </a>
                        <form method="POST" action="{{ route('save', $p) }}" data-undoable="{{ __('“:title” সরানো হল', ['title' => $p->title]) }}">@csrf
                            <button class="flex h-11 flex-none items-center rounded-full border-2 border-navy bg-white px-4 text-[15px] font-bold" aria-label="{{ __('সেভ করা লেখা থেকে সরান: :title', ['title' => $p->title]) }}">{{ __('সরান') }}</button></form>
                    </li>
                @endforeach
            </ul>
            @if ($saved->hasMorePages())
                <a href="{{ $saved->nextPageUrl() }}" data-more-rows class="mt-3 flex h-12 w-full items-center justify-center rounded-full border-2 border-navy bg-white font-bold hover:bg-peach-light">
                    {{ __('আরও দেখুন (:countটি বাকি)', ['count' => Bn::num((string) ($saved->total() - $saved->currentPage() * $saved->perPage()))]) }}</a>
            @endif
        @endif

        {{-- C6: lines kept with "রাখুন" while reading (only this reader sees them) --}}
        <h2 id="lines" class="mt-8 scroll-mt-24 font-display text-2xl font-extrabold">{{ __('রাখা লাইন') }} <span class="text-ink2">({{ Bn::num((string) $lines->count()) }})</span></h2>
        @if ($lines->isEmpty())
            <p class="mt-1 text-ink2">{{ __('লেখা পড়তে পড়তে কোনো লাইন বেছে নিয়ে “রাখুন” টিপলে লাইনটি এখানে থাকবে।') }}</p>
        @else
            <ul class="mt-3 space-y-3" data-lines>
                @foreach ($lines as $l)
                    <li class="rounded-2xl border-2 border-navy bg-white p-4">
                        <blockquote class="whitespace-pre-line border-l-4 border-red pl-3 font-serif text-lg leading-relaxed">{{ $l->text }}</blockquote>
                        <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
                            <a href="{{ $l->post->url() }}" class="inline-flex min-h-11 min-w-0 items-center font-bold text-sky-night underline">{{ $l->post->title }}@if ($l->post->authors->first()) · {{ $l->post->authors->first()->publicName() }}@endif</a>
                            <form method="POST" action="{{ route('lines.destroy', $l->id) }}">@csrf
                                <button class="flex h-11 flex-none items-center rounded-full border-2 border-navy bg-white px-4 text-[15px] font-bold" aria-label="{{ __('লাইনটি সরান: :title', ['title' => $l->post->title]) }}">{{ __('সরান') }}</button></form>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif

        <h2 class="mt-8 font-display text-2xl font-extrabold">{{ __('ফলো করছেন') }}</h2>
        @if ($following->isEmpty() && $serials->isEmpty())
            <p class="mt-1 text-ink2">{{ __('পছন্দের লেখকের পাতায় বা ধারাবাহিকে “ফলো করুন” টিপলে তাঁদের নতুন লেখার খবর এখানে আর ই-মেলে পাবেন।') }}</p>
        @else
            <ul class="mt-3 flex flex-wrap gap-2">
                @foreach ($following as $f)
                    <li data-row class="flex items-center gap-1 rounded-full border-2 border-navy bg-white py-1 pl-1 pr-1">
                        <a href="{{ $f->url() }}" class="flex min-h-11 items-center gap-2 pr-2 font-bold">
                            @if ($f->avatar_path)<img src="{{ $f->avatar_path }}" alt="" class="h-9 w-9 rounded-full object-cover">
                            @else<span class="flex h-9 w-9 items-center justify-center rounded-full bg-peach text-sm font-extrabold" aria-hidden="true">{{ $f->initial() }}</span>@endif
                            {{ $f->publicName() }}</a>
                        <form method="POST" action="{{ route('follow', $f) }}" data-undoable="{{ __(':name-কে আর ফলো করছেন না', ['name' => $f->publicName()]) }}">@csrf
                            <button class="flex h-11 w-11 items-center justify-center rounded-full text-lg text-ink2 hover:bg-peach-light" aria-label="{{ __('ফলো বন্ধ করুন: :name', ['name' => $f->publicName()]) }}" title="{{ __('ফলো বন্ধ করুন') }}">✕</button></form>
                    </li>
                @endforeach
                @foreach ($serials as $se)
                    <li><a href="{{ $se->url() }}" class="flex min-h-12 items-center gap-2 rounded-full border-2 border-navy bg-white py-1 pl-1 pr-4 font-bold">
                        <span class="flex h-9 w-9 items-center justify-center rounded-full bg-sky-tint text-sm font-extrabold text-sky-night" aria-hidden="true">{{ __('ধা') }}</span>{{ $se->title }} <span class="text-sm font-medium text-ink2">{{ __('(ধারাবাহিক)') }}</span></a></li>
                @endforeach
            </ul>
        @endif

        <div class="mt-8 space-y-3">
            <details class="group">
                <summary class="{{ $more }}"><span>{{ __('লাইক করা লেখা') }} <span class="text-ink2">({{ Bn::num((string) $liked->count()) }})</span></span><span class="transition group-open:rotate-90" aria-hidden="true">›</span></summary>
                @if ($liked->isEmpty())
                    <p class="mt-2 px-1 text-ink2">{{ __('লেখার নিচে “পড়ে কেমন লাগল?”-এ একটি বেছে নিলে লেখাটি এখানে জমা থাকে।') }}</p>
                @else
                    <ul class="mt-2 divide-y divide-rule px-1">@foreach ($liked as $p)<li><a href="{{ $p->url() }}" class="block py-2.5"><span class="font-serif text-lg font-bold">{{ $p->title }}</span> <span class="text-sm text-ink2">· {{ $p->authors->first()?->publicName() }}</span></a></li>@endforeach</ul>
                @endif
            </details>
            <details class="group">
                <summary class="{{ $more }}"><span>{{ __('সম্প্রতি পড়া') }}</span><span class="transition group-open:rotate-90" aria-hidden="true">›</span></summary>
                @if (! $user->reading_history)
                    <p class="mt-2 px-1 text-ink2">{{ __('“পড়ার হিস্টরি রাখুন” বন্ধ আছে।') }} {{ __('চালু করতে যান:') }} <a href="{{ Url::to('/account/settings') }}#reading" class="font-bold text-sky-night underline">{{ __('সেটিংস') }}</a></p>
                @elseif ($recent->isEmpty())
                    <p class="mt-2 px-1 text-ink2">{{ __('এখনও কিছু পড়েননি। যা পড়বেন, এখানে থাকবে (শুধু আপনি দেখতে পান)।') }}</p>
                @else
                    <ul class="mt-2 divide-y divide-rule px-1">@foreach ($recent as $p)<li><a href="{{ $p->url() }}" class="block py-2.5"><span class="font-serif text-lg font-bold">{{ $p->title }}</span> <span class="text-sm text-ink2">· {{ __(':percent% পড়েছেন', ['percent' => Bn::num((string) $p->progress)]) }}</span></a></li>@endforeach</ul>
                @endif
            </details>
        </div>

        @unless (\App\Support\AccountMenu::for($user)['writer'])
            {{-- D166 (owner): for readers, writing is one quiet line --}}
            <p class="mt-8 rounded-2xl border-2 border-dashed border-peach-deep p-4 text-center">{{ __('আপনিও লিখতে পারেন।') }} <a href="{{ Url::to('/submissions') }}" class="font-bold text-sky-night underline">{{ __('কীভাবে লেখা পাঠাবেন →') }}</a></p>
        @endunless
    </x-account-shell>
</x-layouts.app>
