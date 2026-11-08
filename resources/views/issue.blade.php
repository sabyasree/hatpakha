<x-layouts.app :seo="$seo">
    <x-desk-edit :url="\App\Filament\Resources\Issues\IssueResource::getUrl('edit', ['record' => $issue], panel: 'desk')" label="সংখ্যা সম্পাদনা" />
    <section class="mx-auto max-w-[1100px] px-5 py-12">
        <div class="font-hand text-2xl text-red">সংখ্যা</div>
        <h1 class="font-display text-6xl font-extrabold">{{ $issue->title }}</h1>
        @if ($posts->isNotEmpty())
            <a href="{{ \App\Support\Url::to('/sonkha/'.$issue->slug.'/boi') }}" class="mt-4 inline-flex h-12 items-center gap-2 rounded-full bg-red px-6 font-bold text-white"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 5h6a2 2 0 0 1 2 2v12a2 2 0 0 0-2-2H4z"/><path d="M20 5h-6a2 2 0 0 0-2 2v12a2 2 0 0 1 2-2h6z"/></svg> বই হিসেবে পড়ুন</a>
        @endif
        @if ($issue->editor_note)<p class="mt-4 max-w-[720px] font-serif text-lg">{{ $issue->editor_note }}</p>@endif
        <ol class="mt-8 divide-y divide-rule">
            @foreach ($posts as $i => $p)
                <li class="flex gap-4 py-3"><span class="w-8 flex-none pt-1 text-ink2">{{ \App\Support\Bn::num($i + 1) }}</span><a href="{{ $p->url() }}" class="min-w-0 flex-1"><span class="block font-serif text-xl font-bold leading-snug">{{ $p->title }}</span><span class="mt-0.5 block text-sm text-ink2">{{ $p->authors->first()?->publicName() }}</span></a></li>
            @endforeach
        </ol>
        @if ($posts->count() >= 4)<x-ad-slot spot="list" :height="250" />@endif
    </section>
</x-layouts.app>
