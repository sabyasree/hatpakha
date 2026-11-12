{{-- An issue's page (owner, 2026-11-12): like a magazine on a stand. The cover, "পত্রিকা খুলুন" (the page-turning
     magazine), the editor's word, the contents by section, and the e-book once the owner has approved it. --}}
@php
    use App\Support\Bn;
    $editorial = $issue->liveEditorial();   // its own section, first (owner, 2026-11-12)
    $bySection = $posts->reject(fn ($p) => $editorial && $p->id === $editorial->id)->groupBy(fn ($p) => $p->section?->name_bn ?? 'অন্যান্য');
    $writers = $posts->flatMap(fn ($p) => $p->authors->where('is_desk', false))->unique('id');
@endphp
<x-layouts.app :seo="$seo">
    <x-desk-edit :url="\App\Filament\Resources\Issues\IssueResource::getUrl('edit', ['record' => $issue], panel: 'desk')" label="সংখ্যা সম্পাদনা" />
    <section class="bg-navy text-paper">
        <div class="mx-auto grid max-w-[1100px] items-center gap-8 px-5 py-10 md:grid-cols-[minmax(0,320px)_1fr] md:py-14">
            {{-- The cover, like a magazine on a stand --}}
            <a href="{{ \App\Support\Url::to('/sonkha/'.$issue->slug.'/boi') }}" class="group relative mx-auto block aspect-[1/1.41] w-56 overflow-hidden rounded-md shadow-[10px_10px_0_#CB1B32] transition md:w-full md:group-hover:-translate-y-1" aria-label="পত্রিকা খুলুন: {{ $issue->title }}">
                @if ($issue->cover_path)
                    <img src="{{ $issue->cover_path }}" alt="" class="absolute inset-0 h-full w-full object-cover">
                    <span class="absolute inset-0 bg-gradient-to-b from-navy/60 via-transparent to-navy/80"></span>
                @else
                    <span class="absolute -bottom-[15%] -right-[20%] aspect-square w-[90%] rounded-full" style="background: repeating-conic-gradient(#CB1B32 0 7deg, #F08D90 7deg 14deg)"></span>
                @endif
                <span class="absolute inset-x-4 top-4 font-hand text-5xl leading-none text-white">হাতপাখা</span>
                <span class="absolute inset-x-4 bottom-4 font-display text-xl font-extrabold leading-tight text-white">{{ $issue->title }}</span>
            </a>
            <div>
                <p class="font-hand text-2xl text-peach">সংখ্যা</p>
                <h1 class="font-display text-5xl font-extrabold leading-tight md:text-6xl">{{ $issue->title }}</h1>
                <p class="mt-3 text-peach-light">{{ Bn::num((string) $posts->count()) }}টি লেখা · {{ Bn::num((string) $writers->count()) }} জন লেখক @if ($issue->published_at)· {{ Bn::date($issue->published_at) }}@endif</p>
                <div class="mt-6 flex flex-wrap gap-3">
                    @if ($posts->isNotEmpty())
                        <a href="{{ \App\Support\Url::to('/sonkha/'.$issue->slug.'/boi') }}" class="inline-flex h-14 items-center gap-2 rounded-full bg-red px-7 text-lg font-bold text-white shadow-[4px_4px_0_#FFFBF4]">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 5h6a2 2 0 0 1 2 2v12a2 2 0 0 0-2-2H4z"/><path d="M20 5h-6a2 2 0 0 0-2 2v12a2 2 0 0 1 2-2h6z"/></svg>
                            পত্রিকা খুলুন</a>
                    @endif
                </div>
                <p class="mt-3 text-sm text-peach-light">পাতা উল্টে পড়ুন, পুরো পর্দায়; বা নিচে থেকে যে-কোনো লেখা খুলুন।</p>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-[1100px] px-5 py-12">
        @if ($editorial || $issue->editor_note)
            <div class="max-w-[760px] rounded-3xl border-2 border-navy bg-peach-light p-6">
                <p class="text-sm font-extrabold text-red">সম্পাদকীয়</p>
                @if ($editorial)
                    <h2 class="mt-1 font-display text-3xl font-extrabold"><a href="{{ $editorial->url() }}" class="hover:text-red">{{ $editorial->title }}</a></h2>
                    <p class="mt-1 text-sm text-ink2">{{ $editorial->authors->map->publicName()->implode(', ') }}</p>
                    @if ($editorial->excerpt)<p class="mt-2 font-serif text-lg text-ink2">{{ $editorial->excerpt }}</p>@endif
                    <a href="{{ $editorial->url() }}" class="mt-2 inline-block font-bold text-sky-deep underline">পুরোটা পড়ুন →</a>
                @else
                    @foreach (preg_split('/\n{2,}/', trim($issue->editor_note)) as $para)<p class="mt-3 font-serif text-lg">{{ trim($para) }}</p>@endforeach
                @endif
            </div>
        @endif

        <h2 class="mt-12 font-display text-3xl font-extrabold">এই সংখ্যায়</h2>
        <div class="mt-4 grid gap-8 md:grid-cols-2">
            @if ($editorial)
                <div>
                    <h3 class="border-b-2 border-navy pb-1 text-sm font-extrabold text-red">সম্পাদকীয়</h3>
                    <ol class="divide-y divide-rule"><li><a href="{{ $editorial->url() }}" class="block py-3 hover:text-red"><span class="block font-serif text-xl font-bold leading-snug">{{ $editorial->title }}</span><span class="mt-0.5 block text-sm text-ink2">{{ $editorial->authors->map->publicName()->implode(', ') }}</span></a></li></ol>
                </div>
            @endif
            @foreach ($bySection as $sec => $list)
                <div>
                    <h3 class="border-b-2 border-navy pb-1 text-sm font-extrabold text-red">{{ $sec }}</h3>
                    <ol class="divide-y divide-rule">
                        @foreach ($list as $p)
                            <li><a href="{{ $p->url() }}" class="block py-3 hover:text-red"><span class="block font-serif text-xl font-bold leading-snug">{{ $p->title }}</span><span class="mt-0.5 block text-sm text-ink2">{{ $p->authors->map->publicName()->implode(', ') }}</span></a></li>
                        @endforeach
                    </ol>
                </div>
            @endforeach
        </div>
        @if ($posts->count() >= 4)<x-ad-slot spot="list" :height="250" />@endif
    </section>
</x-layouts.app>
