<x-layouts.app :seo="$seo">
    <section class="mx-auto max-w-[1320px] px-5 py-12">
        @if ($kicker)<div class="font-hand text-2xl text-red">{{ $kicker }}</div>@endif
        <h1 class="font-display text-6xl font-extrabold leading-none" style="font-stretch: 78%">{{ $title }}</h1>
        @if (($section ?? null)?->about)<p class="mt-4 max-w-[720px] font-serif text-lg leading-relaxed text-ink2">{{ $section->about }}</p>@endif
        @if ($section ?? null)
            {{-- D132: every new piece of this section, on the phone (no account needed) --}}
            <div class="mt-4"><x-push-button :topic="'section:'.$section->id" :off="'🔔 '.$section->name_bn.': নতুন লেখা এলে ফোনে জানান'" :on="'✓ '.$section->name_bn.': নতুন লেখা এলে ফোনে জানাব'" /></div>
        @endif
        @if (($section ?? null)?->slug === \App\Models\Section::KIDS)
            {{-- D101: by age, and the alphabet --}}
            <nav class="mt-6 flex flex-wrap items-center gap-2" aria-label="বয়স ধরে">
                <a href="{{ $section->url() }}" class="flex min-h-11 items-center rounded-full border-2 border-navy px-4 font-bold {{ ! ($age ?? null) ? 'bg-navy text-white' : 'hover:bg-peach-light' }}" @if (! ($age ?? null)) aria-current="page" @endif>সব বয়স</a>
                @foreach (\App\Ai\KidsTeacher::AGES as $band => $label)
                    <a href="{{ $section->url() }}?age={{ $band }}" class="flex min-h-11 items-center rounded-full border-2 border-navy px-4 font-bold {{ ($age ?? null) === $band ? 'bg-navy text-white' : 'hover:bg-peach-light' }}" @if (($age ?? null) === $band) aria-current="page" @endif>{{ $label }}</a>
                @endforeach
                <a href="{{ \App\Support\Url::to('/bornomala') }}" class="flex min-h-11 items-center rounded-full bg-sky px-4 font-bold text-navy hover:bg-sky-light">অ আ ক খ — বর্ণমালা →</a>
            </nav>
            @if (($age ?? null) && $posts->total() === 0)
                <p class="mt-6 font-serif text-lg text-ink2">এই বয়সের লেখা এখনও নেই। <a href="{{ $section->url() }}" class="font-bold text-sky-deep underline">সব বয়সের লেখা দেখুন</a></p>
            @endif
        @endif
        @if (($section ?? null) && $posts->total() === 0 && ! ($age ?? null))
            {{-- D96: a new section waiting for its first piece --}}
            <div class="mt-8 max-w-[720px] rounded-3xl border-2 border-dashed border-navy p-6 sm:p-8">
                <p class="font-hand text-3xl text-red">নতুন বিভাগ</p>
                <p class="mt-2 font-serif text-xl">এই বিভাগে প্রথম লেখাটি আপনার হোক।</p>
                <a href="{{ \App\Support\Url::to('/submit') }}?section={{ $section->slug }}" class="mt-5 inline-flex h-12 items-center rounded-full bg-red px-6 font-bold text-white shadow-[3px_3px_0_#13212C]">এই বিভাগে লেখা পাঠান</a>
            </div>
        @endif
        {{-- Phones: one readable list (mobile UX review); wider screens: the card grid. --}}
        <ul class="mt-6 divide-y divide-rule border-y border-rule sm:hidden">
            @foreach ($posts as $p)
                <li><x-post-row :post="$p" /></li>
                @if ($loop->iteration === 8 && $loop->remaining > 2)<li class="list-none"><x-ad-slot spot="list" :height="250" class="!my-4" /></li>@endif
            @endforeach
        </ul>
        <div class="mt-8 hidden gap-6 sm:grid sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($posts as $p)
                <a href="{{ $p->url() }}" class="block"><x-title-card :post="$p" />@if ($p->hasCardImage())<span class="mt-2 block font-serif text-base font-bold leading-snug sm:text-lg">{{ $p->title }}</span>@endif<span class="mt-1 block text-sm font-semibold text-ink2">{{ $p->authors->first()?->publicName() }} · {{ \App\Support\Bn::date($p->published_at) }}</span></a>
                @if ($loop->iteration === 8 && $loop->remaining > 2)<div class="col-span-full"><x-ad-slot spot="list" :height="250" class="!my-0" /></div>@endif
            @endforeach
        </div>
        @if ($posts->count() >= 6)<x-ad-slot spot="list" :height="250" />@endif
        <div class="mt-10">{{ $posts->links('pagination.hatpakha') }}</div>
    </section>
</x-layouts.app>
