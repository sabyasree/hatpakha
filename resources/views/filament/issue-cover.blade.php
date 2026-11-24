{{-- Issue cover (D51, D277): the cover exactly as readers will see it, with the designer's artwork (or its proposal);
     download as a picture for Facebook or print. The style is chosen below (প্রচ্ছদের ধরন). --}}
@php
    $c = \App\Support\IssueCover::data($issue, \App\Support\IssueBook::withSerials($issue, \App\Models\Post::with(['authors', 'section', 'serial'])
        ->where(fn ($q) => $q->where('issue_id', $issue->id)->orWhereIn('id', (array) ($issue->ai_plan['post_ids'] ?? [])))
        ->whereNotIn('status', ['rejected', 'archived'])->orderBy('published_at')->limit(40)->get(), true), true);
@endphp
<div style="display:flex;gap:1rem;flex-wrap:wrap;align-items:flex-end">
    <div class="hp-cover-card" style="width:300px">
        {{-- the styles travel with the card, so the downloaded picture looks the same --}}
        <style>{!! file_get_contents(public_path('css/issue-cover.css')) !!}</style>
        @include('partials.issue-cover', ['c' => $c])
    </div>
    <div style="display:grid;gap:.5rem;max-width:22rem">
        @if (! $issue->cover_path)
            <span style="font-size:.875rem;opacity:.75">{{ __('গ্রাফিক ডিজাইনারের প্রস্তাব। সংখ্যা অনুমোদন করলে এটাই প্রচ্ছদ হবে।') }}</span>
        @endif
        <span style="font-size:.875rem;opacity:.75">{{ __('প্রচ্ছদের ধরন') }}: {{ __(\App\Support\IssueCover::STYLES[$c['style']]) }}</span>
        <x-filament::button size="sm" color="gray" icon="heroicon-o-arrow-down-tray" x-data
            x-on:click="window.hpCard.download($el.closest('div').parentElement.querySelector('.hp-cover-card'), 'hatpakha-{{ $issue->slug }}-cover.png')">{{ __('প্রচ্ছদ নামান') }}</x-filament::button>
    </div>
</div>
<script src="{{ asset('js/hp-card.js') }}?v=2"></script>
