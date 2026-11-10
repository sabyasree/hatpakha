{{-- Reading list and followed writers (owner + UX review): compact rows, 20 at a time, remove with one tap. --}}
@php use App\Support\Bn; use App\Support\Url; @endphp
<x-layouts.app :seo="$seo">
    <x-account-shell tab="reading" :user="$user" :author="$author" :counts="$counts" :two-factor-on="$twoFactorOn" :cert-count="$certCount">
        <h2 class="font-display text-2xl font-extrabold">পরে পড়ব <span class="text-ink2">({{ Bn::num((string) $saved->total()) }})</span></h2>
        @if ($saved->isEmpty())
            <p class="mt-2 text-ink2">যে লেখা পরে পড়তে চান, লেখার নিচে "পরে পড়ব" চাপলে এখানে জমা থাকবে।</p>
        @else
            <ul class="mt-3 divide-y divide-rule border-y border-rule" data-list>
                @foreach ($saved as $p)
                    <li class="flex items-center gap-2" data-row>
                        <a href="{{ $p->url() }}" class="min-w-0 flex-1 py-3">
                            <span class="block font-serif text-lg font-bold leading-snug">{{ $p->title }}</span>
                            <span class="text-sm text-ink2">{{ $p->authors->first()?->publicName() }} · {{ $p->section?->name_bn }}</span>
                        </a>
                        <form method="POST" action="{{ route('save', $p) }}" data-undoable="তালিকা থেকে সরানো হল">@csrf
                            <button class="flex h-11 w-11 items-center justify-center rounded-full text-xl text-ink2 hover:bg-peach-light hover:text-red" aria-label="তালিকা থেকে সরান: {{ $p->title }}" title="তালিকা থেকে সরান">✕</button></form>
                    </li>
                @endforeach
            </ul>
            @if ($saved->hasMorePages())
                <a href="{{ $saved->nextPageUrl() }}" data-more-rows class="mt-4 flex h-12 w-full items-center justify-center rounded-full border-2 border-navy bg-white font-bold hover:bg-peach-light">
                    আরও দেখুন ({{ Bn::num((string) ($saved->total() - $saved->currentPage() * $saved->perPage())) }}টি বাকি)</a>
            @endif
        @endif

        <h2 class="mt-10 font-display text-2xl font-extrabold">যাঁদের অনুসরণ করছেন <span class="text-ink2">({{ Bn::num((string) $following->count()) }})</span></h2>
        @if ($following->isEmpty())
            <p class="mt-2 text-ink2">পছন্দের লেখকের পাতায় "অনুসরণ করুন" চাপলে তাঁর নতুন লেখা প্রকাশ হলেই ই-মেলে জানাব।</p>
        @else
            <ul class="mt-3 grid gap-2 sm:grid-cols-2">
                @foreach ($following as $f)
                    <li data-row @if ($loop->index >= 8 && ! request('following')) hidden data-more-follow @endif class="flex items-center gap-2 rounded-2xl border-2 border-navy bg-white p-2 pl-3">
                        <a href="{{ $f->url() }}" class="flex min-h-11 min-w-0 flex-1 items-center gap-3">
                            @if ($f->avatar_path)<img src="{{ $f->avatar_path }}" alt="" class="h-10 w-10 flex-none rounded-full object-cover">
                            @else<span class="flex h-10 w-10 flex-none items-center justify-center rounded-full bg-peach-light font-extrabold text-red-dark" aria-hidden="true">{{ $f->initial() }}</span>@endif
                            <span class="truncate font-bold">{{ $f->publicName() }}</span>
                        </a>
                        <form method="POST" action="{{ route('follow', $f) }}" data-undoable="অনুসরণ বন্ধ হল">@csrf
                            <button class="flex h-10 items-center rounded-full border-2 border-navy px-3 text-sm font-bold hover:bg-peach-light" aria-label="অনুসরণ বন্ধ: {{ $f->publicName() }}">অনুসরণ বন্ধ</button></form>
                    </li>
                @endforeach
            </ul>
            @if ($following->count() > 8 && ! request('following'))
                <a href="?following=all" data-show-follow class="mt-3 inline-flex min-h-11 items-center font-bold text-sky-deep underline">সব দেখুন ({{ Bn::num((string) $following->count()) }}) →</a>
            @endif
        @endif
    </x-account-shell>
</x-layouts.app>
