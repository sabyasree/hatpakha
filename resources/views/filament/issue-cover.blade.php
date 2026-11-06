{{-- Issue cover (D51): the designer's artwork with our masthead; download as a picture for Facebook/print. --}}
@php
    $img = $issue->cover_path ?: $issue->proposed_cover_path;
    $card = ['title' => $issue->title, 'section' => 'বাংলা সাহিত্যের ওয়েব পত্রিকা', 'image' => $img ? asset(ltrim($img, '/')) : null];
@endphp
<div style="display:flex;gap:1rem;flex-wrap:wrap;align-items:flex-end">
    <div style="width:270px;height:338px;overflow:hidden;border-radius:.6rem">
        <div style="transform:scale(.25);transform-origin:top left">@include('filament.partials.social-card', ['card' => $card, 'layout' => 'issue'])</div>
    </div>
    <div style="display:grid;gap:.5rem;max-width:22rem">
        @if (! $issue->cover_path)
            <span style="font-size:.875rem;opacity:.75">{{ __('গ্রাফিক ডিজাইনারের প্রস্তাব। সংখ্যা অনুমোদন করলে এটাই প্রচ্ছদ হবে।') }}</span>
        @endif
        <x-filament::button size="sm" color="gray" icon="heroicon-o-arrow-down-tray" x-data
            x-on:click="window.hpCard.download($el.closest('div').parentElement.querySelector('.hp-card'), 'hatpakha-{{ $issue->slug }}-cover.png')">{{ __('প্রচ্ছদ নামান') }}</x-filament::button>
    </div>
</div>
<script src="{{ asset('js/hp-card.js') }}?v=1"></script>
