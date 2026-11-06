{{-- Writer's page (D59): cover, photo, name, what and since when they write, bio, links, then the writing. --}}
@php
    use App\Support\Bn;
    $cover = $author->cover_path;
    $avatar = $author->avatar_path;
    $links = array_filter((array) $author->links);
    $linkNames = ['website' => 'ওয়েবসাইট', 'facebook' => 'ফেসবুক', 'instagram' => 'ইনস্টাগ্রাম'];
@endphp
<x-layouts.app :seo="$seo">
    <x-desk-edit :url="\App\Filament\Resources\Authors\AuthorResource::getUrl('edit', ['record' => $author], panel: 'desk')" label="লেখক সম্পাদনা" />
    <section class="mx-auto max-w-[1100px] px-5 pt-6">
        {{-- Cover: the writer's own picture, or our fan pattern --}}
        <div class="relative h-32 overflow-hidden rounded-3xl border-2 border-navy bg-sky sm:h-44 md:h-60">
            @if ($cover)
                <picture>
                    @if ($w = \App\Support\ProfileImage::webp($cover))<source srcset="{{ $w }}" type="image/webp">@endif
                    <img src="{{ $cover }}" alt="" class="h-full w-full object-cover" fetchpriority="high">
                </picture>
            @else
                <div class="halftone absolute inset-0" aria-hidden="true"></div>
                <x-fan class="absolute bottom-0 right-[6%] w-[min(420px,60%)]" stroke="#13212C" />
            @endif
        </div>

        <div class="relative px-1 sm:px-4">
            <div class="-mt-12 sm:-mt-16">
                @if ($avatar)
                    <img src="{{ $avatar }}" alt="{{ $author->publicName() }}" class="h-24 w-24 rounded-full border-[5px] border-paper object-cover outline outline-2 outline-navy sm:h-32 sm:w-32">
                @else
                    <span class="flex h-24 w-24 items-center justify-center rounded-full border-[5px] border-paper bg-gradient-to-br from-peach to-peach-deep text-4xl font-extrabold outline outline-2 outline-navy sm:h-32 sm:w-32 sm:text-5xl" aria-hidden="true">{{ $author->initial() }}</span>
                @endif
            </div>

            <div class="mt-3 flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0 flex-1">
                    <h1 class="break-words font-display text-4xl font-extrabold leading-tight sm:text-5xl md:text-6xl" style="font-stretch: 80%">{{ $author->publicName() }}</h1>
                    <p class="mt-2 text-ink2">
                        @if ($author->is_desk) পুরনো সংখ্যার যে লেখাগুলোর লেখকের নাম হারিয়ে গেছে
                        @elseif ($author->is_guest) অতিথি লেখক
                        @else হাতপাখার লেখক @endif
                        @if ($author->city) · {{ $author->city }}@endif
                        · {{ Bn::num((string) $posts->total()) }}টি লেখা
                        @if ($since) · {{ Bn::year((int) \Illuminate\Support\Carbon::parse($since)->year) }} থেকে@endif
                    </p>
                    @if ($sections->isNotEmpty())
                        <ul class="mt-3 flex flex-wrap gap-2" aria-label="যে বিভাগে লেখেন">
                            @foreach ($sections as $s)
                                <li><a href="{{ \App\Support\Url::to('/category/'.$s->slug) }}" class="inline-flex h-9 items-center rounded-full border-2 border-navy bg-white px-3 text-sm font-bold hover:bg-peach-light">{{ $s->name }} <span class="ml-1 text-ink2">{{ Bn::num((string) $s->n) }}</span></a></li>
                            @endforeach
                        </ul>
                    @endif
                </div>
                <div class="flex flex-wrap gap-2">
                    @if ($isOwner)
                        <a href="{{ \App\Support\Url::to('/account') }}#profile" class="flex h-11 items-center rounded-full bg-red px-5 font-bold text-white">প্রোফাইল সাজান</a>
                    @endif
                    <a href="https://wa.me/?text={{ rawurlencode($author->publicName().'-এর লেখা, হাতপাখায়: '.$author->url()) }}" rel="noopener" target="_blank" class="flex h-11 items-center rounded-full border-2 border-navy px-5 font-bold">শেয়ার</a>
                </div>
            </div>

            @if ($author->bio || $links)
                <div class="mt-6 grid gap-4 md:grid-cols-[1fr_auto]">
                    @if ($author->bio)
                        <p class="max-w-[70ch] whitespace-pre-line font-serif text-lg leading-relaxed">{{ $author->bio }}</p>
                    @endif
                    @if ($links)
                        <ul class="flex flex-wrap gap-2 md:flex-col" aria-label="লেখকের লিংক">
                            @foreach ($links as $k => $u)
                                @if (isset($linkNames[$k]) && str_starts_with($u, 'https://'))
                                    <li><a href="{{ $u }}" rel="nofollow noopener ugc me" target="_blank" class="inline-flex h-10 items-center rounded-full bg-peach-light px-4 font-bold hover:bg-peach">{{ $linkNames[$k] }} ↗</a></li>
                                @endif
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endif

            @if ($author->is_guest && ! $author->is_desk && ! $author->claimed_at)
                <div class="mt-6 max-w-[720px] rounded-2xl bg-peach-light p-5"><div class="text-lg font-extrabold">এই প্রোফাইল কি আপনার?</div>
                    <p class="mt-1 text-ink2">ছবি আর পরিচিতি যোগ করতে <a href="{{ \App\Support\Url::to('/contact-us') }}" class="font-bold underline">আমাদের জানান</a>; যাচাই করে প্রোফাইলটি আপনার অ্যাকাউন্টে জুড়ে দেওয়া হবে।</p></div>
            @endif
        </div>

        <h2 class="mt-12 border-t-2 border-navy pt-6 font-display text-3xl font-extrabold">লেখা</h2>
        <div class="mt-6 grid grid-cols-2 gap-x-3 gap-y-6 sm:gap-6 lg:grid-cols-3">
            @foreach ($posts as $p)
                <a href="{{ $p->url() }}" class="block"><x-title-card :post="$p" />@if ($p->hasCardImage())<span class="mt-2 block font-serif text-base font-bold leading-snug sm:text-lg">{{ $p->title }}</span>@endif<span class="mt-1 block text-sm font-semibold text-ink2">{{ $p->section?->name_bn }} · {{ Bn::date($p->published_at) }}</span></a>
            @endforeach
        </div>
        <div class="mt-10">{{ $posts->links() }}</div>
    </section>
</x-layouts.app>
