{{-- Contest entries and the contest manager's shortlist (D53). Inline styles: /desk has no utility CSS. --}}
<div style="display:grid;gap:1rem;margin-top:1.5rem">
    @if ($shortlist->isNotEmpty())
        <x-filament::section icon="heroicon-o-sparkles" collapsible persist-collapsed collapse-id="hp-contest-entries-1">
            <x-slot name="heading">{{ __('এআই-এর বাছাই তালিকা (শুধু পরামর্শ)') }}</x-slot>
            <ol style="margin:0;padding-inline-start:1.4rem;list-style:decimal;line-height:1.9">
                @foreach ($shortlist as $s)
                    <li><a href="{{ \App\Filament\Resources\Posts\PostResource::getUrl('edit', ['record' => $s['post']]) }}" style="text-decoration:underline;font-weight:600">{{ $s['post']->title }}</a>
                        <span style="opacity:.75">— {{ $s['why'] }}</span></li>
                @endforeach
            </ol>
        </x-filament::section>
    @endif
    <x-filament::section collapsible persist-collapsed collapse-id="hp-contest-entries-2">
        <x-slot name="heading">{{ __('জমা পড়া লেখা') }} ({{ \App\Support\Bn::num((string) $entries->count()) }})</x-slot>
        @forelse ($entries as $p)
            <div style="padding:.35rem 0;border-bottom:1px solid rgba(127,127,127,.15)">
                <a href="{{ \App\Filament\Resources\Posts\PostResource::getUrl('edit', ['record' => $p]) }}" style="text-decoration:underline">{{ $p->title }}</a>
                <span style="opacity:.75">· {{ $p->authors->first()?->publicName() }}</span>
            </div>
        @empty
            <div style="opacity:.75">{{ __('এখনও কোনো লেখা জমা পড়েনি।') }}</div>
        @endforelse
    </x-filament::section>
</div>
