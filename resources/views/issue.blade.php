<x-layouts.app :seo="$seo">
    <x-desk-edit :url="\App\Filament\Resources\Issues\IssueResource::getUrl('edit', ['record' => $issue], panel: 'desk')" label="সংখ্যা সম্পাদনা" />
    <section class="mx-auto max-w-[1100px] px-5 py-12">
        <div class="font-hand text-2xl text-red">সংখ্যা</div>
        <h1 class="font-display text-6xl font-extrabold">{{ $issue->title }}</h1>
        @if ($issue->editor_note)<p class="mt-4 max-w-[720px] font-serif text-lg">{{ $issue->editor_note }}</p>@endif
        <ol class="mt-8 divide-y divide-rule">
            @foreach ($posts as $i => $p)
                <li class="flex gap-4 py-3"><span class="w-10 text-ink2">{{ \App\Support\Bn::num($i + 1) }}</span><a href="{{ $p->url() }}" class="flex-1 font-serif text-xl font-bold">{{ $p->title }}</a><span class="text-sm text-ink2">{{ $p->authors->first()?->publicName() }}</span></li>
            @endforeach
        </ol>
    </section>
</x-layouts.app>
