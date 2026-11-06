<x-layouts.app :seo="$seo">
    <section class="mx-auto max-w-[1100px] px-5 pt-6">
        <div class="relative h-40 overflow-hidden rounded-3xl border-2 border-navy bg-sky md:h-56">
            <div class="halftone absolute inset-0" aria-hidden="true"></div>
            <x-fan class="absolute bottom-0 right-[6%] w-[min(520px,70%)]" stroke="#13212C" />
        </div>
        <div class="-mt-14 flex flex-wrap items-end gap-6 px-2">
            <span class="relative flex h-28 w-28 items-center justify-center rounded-full border-[5px] border-paper bg-gradient-to-br from-peach to-peach-deep text-5xl font-extrabold outline outline-2 outline-navy md:h-36 md:w-36" aria-hidden="true">{{ $author->initial() }}</span>
            <div class="flex-1 pb-2">
                <h1 class="font-display text-5xl font-extrabold leading-none md:text-6xl" style="font-stretch: 78%">{{ $author->publicName() }}</h1>
                <div class="mt-1 text-ink2">
                    @if ($author->is_desk) পুরনো সংখ্যার যে লেখাগুলোর লেখকের নাম হারিয়ে গেছে
                    @elseif ($author->is_guest) অতিথি লেখক
                    @else হাতপাখার লেখক @endif
                    · {{ \App\Support\Bn::num($posts->total()) }}টি লেখা
                </div>
            </div>
        </div>
        @if ($author->bio)<p class="mt-6 max-w-[720px] rounded-2xl border-2 border-dashed border-navy bg-white p-5 font-serif text-lg leading-relaxed">{{ $author->bio }}</p>@endif
        @if ($author->is_guest && ! $author->is_desk && ! $author->claimed_at)
            <div class="mt-6 max-w-[720px] rounded-2xl bg-peach-light p-5"><div class="text-lg font-extrabold">এই প্রোফাইল কি আপনার?</div><p class="mt-1 text-ink2">দাবি করলে ছবি, পরিচিতি যোগ করতে আর নতুন লেখা পাঠাতে পারবেন। (শীঘ্রই)</p></div>
        @endif
        <div class="mt-10 grid grid-cols-2 gap-x-3 gap-y-6 sm:gap-6 lg:grid-cols-3">
            @foreach ($posts as $p)
                <a href="{{ $p->url() }}" class="block"><x-title-card :post="$p" />@if ($p->hasCardImage())<span class="mt-2 block font-serif text-base font-bold leading-snug sm:text-lg">{{ $p->title }}</span>@endif<span class="mt-1 block text-sm font-semibold text-ink2">{{ $p->section?->name_bn }} · {{ \App\Support\Bn::date($p->published_at) }}</span></a>
            @endforeach
        </div>
        <div class="mt-10">{{ $posts->links() }}</div>
    </section>
</x-layouts.app>
