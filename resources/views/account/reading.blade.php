{{-- D166 "আমার পড়া" (a reader's first page; owner + UX review + tests with older users): where they stopped, what is new
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
        @if ($unfinished->isNotEmpty())
            <h2 class="font-display text-2xl font-extrabold">যেখানে থেমেছিলেন</h2>
            <p class="text-ink2">লেখার নামে টিপুন, ঠিক সেখান থেকেই খুলবে।</p>
            <ul class="mt-3 divide-y divide-rule rounded-2xl border-2 border-navy bg-white px-4">
                @foreach ($unfinished as $p)
                    <li><a href="{{ $p->url() }}#resume-{{ $p->progress }}" class="flex items-center gap-3 py-3">
                        @if ($src = $thumb($p))<img src="{{ $src }}" alt="" loading="lazy" class="h-16 w-16 flex-none rounded-xl border-2 border-navy object-cover">@endif
                        <span class="min-w-0 flex-1"><span class="block font-serif text-lg font-bold leading-snug">{{ $p->title }}</span>
                            <span class="block text-sm text-ink2">{{ $p->authors->first()?->publicName() }} · {{ $p->section?->name_bn }}</span>
                            <span class="mt-1.5 block h-2 overflow-hidden rounded-full bg-peach-light" aria-hidden="true"><span class="block h-full rounded-full bg-red" style="width: {{ $p->progress }}%"></span></span>
                            <span class="text-sm text-ink2">{{ Bn::num((string) $p->progress) }}% পড়েছেন</span></span>
                    </a></li>
                @endforeach
            </ul>
        @endif

        @if ($fresh->isNotEmpty())
            <h2 class="mt-8 font-display text-2xl font-extrabold">আপনার জন্য নতুন</h2>
            <p class="text-ink2">যাঁদের লেখা পড়েন, তাঁদের নতুন লেখা।</p>
            <ul class="mt-3 divide-y divide-rule rounded-2xl border-2 border-navy bg-white px-4">
                @foreach ($fresh as $p)
                    <li><a href="{{ $p->url() }}" class="flex items-center gap-3 py-3">
                        <span class="min-w-0 flex-1"><span class="mr-1 rounded-full bg-red px-2 text-sm font-bold text-white">নতুন</span> <span class="font-serif text-lg font-bold leading-snug">{{ $p->title }}</span>
                            <span class="block text-sm text-ink2">{{ $p->serial ? 'ধারাবাহিক: '.$p->serial->title : $p->authors->first()?->publicName() }} · {{ $p->section?->name_bn }}</span></span>
                        <span aria-hidden="true">›</span>
                    </a></li>
                @endforeach
            </ul>
        @endif

        <h2 class="{{ $unfinished->isNotEmpty() || $fresh->isNotEmpty() ? 'mt-8' : '' }} font-display text-2xl font-extrabold">পরে পড়ব <span class="text-ink2">({{ Bn::num((string) $saved->total()) }})</span></h2>
        @if ($saved->isEmpty())
            <div class="mt-2 rounded-2xl border-2 border-dashed border-peach-deep p-4">
                <p>পড়তে পড়তে লেখার নিচে “পরে পড়ব” টিপলে লেখাটি এখানে জমা থাকবে।</p>
                <a href="{{ url('/') }}/" class="mt-3 inline-flex h-12 items-center rounded-full bg-navy px-6 font-bold text-white">পড়া শুরু করুন</a>
            </div>
        @else
            <p class="text-sm text-ink2">ফোনে হাতপাখা রাখা থাকলে এই লেখাগুলো ইন্টারনেট ছাড়াও পড়া যায়।</p>
            <ul class="mt-3 divide-y divide-rule rounded-2xl border-2 border-navy bg-white px-4" data-list data-saved-list>
                @foreach ($saved as $p)
                    <li class="flex items-center gap-3 py-3" data-row>
                        @if ($src = $thumb($p))<img src="{{ $src }}" alt="" loading="lazy" class="h-16 w-16 flex-none rounded-xl border-2 border-navy object-cover">@endif
                        <a href="{{ $p->url() }}" class="min-w-0 flex-1">
                            <span class="block font-serif text-lg font-bold leading-snug">{{ $p->title }}</span>
                            <span class="text-sm text-ink2">{{ $p->authors->first()?->publicName() }} · {{ $p->section?->name_bn }}</span>
                        </a>
                        <form method="POST" action="{{ route('save', $p) }}" data-undoable="“{{ $p->title }}” সরানো হল">@csrf
                            <button class="flex h-11 flex-none items-center rounded-full border-2 border-navy bg-white px-4 text-[15px] font-bold" aria-label="পরে পড়বর তালিকা থেকে সরান: {{ $p->title }}">সরান</button></form>
                    </li>
                @endforeach
            </ul>
            @if ($saved->hasMorePages())
                <a href="{{ $saved->nextPageUrl() }}" data-more-rows class="mt-3 flex h-12 w-full items-center justify-center rounded-full border-2 border-navy bg-white font-bold hover:bg-peach-light">
                    আরও দেখুন ({{ Bn::num((string) ($saved->total() - $saved->currentPage() * $saved->perPage())) }}টি বাকি)</a>
            @endif
        @endif

        <h2 class="mt-8 font-display text-2xl font-extrabold">যাঁদের অনুসরণ করেন</h2>
        @if ($following->isEmpty() && $serials->isEmpty())
            <p class="mt-1 text-ink2">পছন্দের লেখকের পাতায় বা ধারাবাহিকে “অনুসরণ করুন” টিপলে তাঁদের নতুন লেখা এখানে আর ই-মেলে জানাব।</p>
        @else
            <ul class="mt-3 flex flex-wrap gap-2">
                @foreach ($following as $f)
                    <li data-row class="flex items-center gap-1 rounded-full border-2 border-navy bg-white py-1 pl-1 pr-1">
                        <a href="{{ $f->url() }}" class="flex min-h-11 items-center gap-2 pr-2 font-bold">
                            @if ($f->avatar_path)<img src="{{ $f->avatar_path }}" alt="" class="h-9 w-9 rounded-full object-cover">
                            @else<span class="flex h-9 w-9 items-center justify-center rounded-full bg-peach text-sm font-extrabold" aria-hidden="true">{{ $f->initial() }}</span>@endif
                            {{ $f->publicName() }}</a>
                        <form method="POST" action="{{ route('follow', $f) }}" data-undoable="{{ $f->publicName() }}-কে আর অনুসরণ করছেন না">@csrf
                            <button class="flex h-11 w-11 items-center justify-center rounded-full text-lg text-ink2 hover:bg-peach-light" aria-label="অনুসরণ বন্ধ করুন: {{ $f->publicName() }}" title="অনুসরণ বন্ধ করুন">✕</button></form>
                    </li>
                @endforeach
                @foreach ($serials as $se)
                    <li><a href="{{ $se->url() }}" class="flex min-h-12 items-center gap-2 rounded-full border-2 border-navy bg-white py-1 pl-1 pr-4 font-bold">
                        <span class="flex h-9 w-9 items-center justify-center rounded-full bg-sky-tint text-sm font-extrabold text-sky-night" aria-hidden="true">ধা</span>{{ $se->title }} <span class="text-sm font-medium text-ink2">(ধারাবাহিক)</span></a></li>
                @endforeach
            </ul>
        @endif

        <div class="mt-8 space-y-3">
            <details class="group">
                <summary class="{{ $more }}"><span>ভালো লেগেছে <span class="text-ink2">({{ Bn::num((string) $liked->count()) }})</span></span><span class="transition group-open:rotate-90" aria-hidden="true">›</span></summary>
                @if ($liked->isEmpty())
                    <p class="mt-2 px-1 text-ink2">যে লেখা ভালো লাগে, লেখার নিচে “ভালো লেগেছে” টিপুন।</p>
                @else
                    <ul class="mt-2 divide-y divide-rule px-1">@foreach ($liked as $p)<li><a href="{{ $p->url() }}" class="block py-2.5"><span class="font-serif text-lg font-bold">{{ $p->title }}</span> <span class="text-sm text-ink2">· {{ $p->authors->first()?->publicName() }}</span></a></li>@endforeach</ul>
                @endif
            </details>
            <details class="group">
                <summary class="{{ $more }}"><span>সম্প্রতি পড়েছেন</span><span class="transition group-open:rotate-90" aria-hidden="true">›</span></summary>
                @if (! $user->reading_history)
                    <p class="mt-2 px-1 text-ink2">“কী পড়ছি মনে রাখুন” বন্ধ আছে। চালু করতে <a href="{{ Url::to('/account/settings') }}#reading" class="font-bold text-sky-night underline">ই-মেল ও পাসওয়ার্ড</a> পাতায় যান।</p>
                @elseif ($recent->isEmpty())
                    <p class="mt-2 px-1 text-ink2">এখনও কিছু পড়েননি। যা পড়বেন, এখানে থাকবে (শুধু আপনি দেখতে পান)।</p>
                @else
                    <ul class="mt-2 divide-y divide-rule px-1">@foreach ($recent as $p)<li><a href="{{ $p->url() }}" class="block py-2.5"><span class="font-serif text-lg font-bold">{{ $p->title }}</span> <span class="text-sm text-ink2">· {{ Bn::num((string) $p->progress) }}% পড়েছেন</span></a></li>@endforeach</ul>
                @endif
            </details>
        </div>

        @unless (\App\Support\AccountMenu::for($user)['writer'])
            {{-- D166 (owner): for readers, writing is one quiet line --}}
            <p class="mt-8 rounded-2xl border-2 border-dashed border-peach-deep p-4 text-center">আপনিও লিখতে পারেন। <a href="{{ Url::to('/submissions') }}" class="font-bold text-sky-night underline">কীভাবে লেখা পাঠাবেন →</a></p>
        @endunless
    </x-account-shell>
</x-layouts.app>
