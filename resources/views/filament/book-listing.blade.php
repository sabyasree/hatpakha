{{-- Google Play Books listing pack for an issue (D68). Inline styles: /desk has no utility CSS. --}}
@php
    $contributors = \App\Support\IssueBook::contributors($pieces);
    $img = $issue->cover_path ?: $issue->proposed_cover_path;
    $card = ['title' => $issue->title, 'by' => count($contributors) ? implode(', ', array_slice($contributors, 0, 4)).(count($contributors) > 4 ? ' ও আরও অনেকে' : '') : '', 'image' => $img ? asset(ltrim($img, '/')) : null];
    $row = fn ($k, $v) => '<tr><td style="padding:.3rem .6rem .3rem 0;opacity:.7;vertical-align:top;white-space:nowrap">'.e($k).'</td><td style="padding:.3rem 0">'.e($v).'</td></tr>';
@endphp
<div style="display:grid;gap:1rem;font-size:.92rem;line-height:1.6">
    <div style="padding:.75rem 1rem;border-radius:.75rem;border:1px solid #E39A6A;background:rgba(239,192,141,.15)">
        <strong>{{ __('কোথায় প্রকাশ করবেন') }}:</strong>
        {{ __('Amazon KDP এখনও বাংলা বই নেয় না (ই-বুক বা ছাপা কোনোটিই; Amazon-এর নিজের ভাষার তালিকা, নভেম্বর ২০২৬)। Google Play Books সব ভাষা নেয়, EPUB বা PDF দুটোই চলে: play.google.com/books/publish। ছাপা বইয়ের জন্য বাংলা নেয় এমন প্রিন্ট-অন-ডিমান্ড পরিষেবায় এই PDF দিতে পারবেন।') }}
    </div>
    <table style="border-collapse:collapse">
        {!! $row(__('শিরোনাম'), 'হাতপাখা · '.$issue->title) !!}
        {!! $row(__('উপশিরোনাম'), 'বাংলা সাহিত্যের ওয়েব পত্রিকা, '.\App\Support\Bn::year((int) substr($issue->slug, 0, 4))) !!}
        {!! $row(__('লেখক/সম্পাদক'), 'সম্পাদনা: হাতপাখা সম্পাদকমণ্ডলী; লেখক: '.(implode(', ', $contributors) ?: '—')) !!}
        {!! $row(__('ভাষা'), 'Bengali (bn)') !!}
        {!! $row(__('প্রকাশক'), 'হাতপাখা (hatpakha.com)') !!}
        {!! $row(__('বিষয় (BISAC)'), 'LIT000000 Literary Collections / General; POE000000 Poetry / General; FIC019000 Fiction / Literary') !!}
        {!! $row(__('পরিচয় নম্বর'), __('ISBN না থাকলে Google নিজের নম্বর দেয়; পত্রিকার ISSN 2321-3035 বিবরণে লিখতে পারেন।')) !!}
        {!! $row(__('লেখা'), \App\Support\Bn::num((string) $pieces->count()).__('টি লেখা')) !!}
    </table>
    <div>
        <strong>{{ __('প্রচ্ছদ (১৬০০×২৫৬০)') }}</strong>
        <div style="display:flex;gap:1rem;align-items:flex-end;flex-wrap:wrap;margin-top:.4rem">
            <div style="width:160px;height:256px;overflow:hidden;border-radius:.4rem"><div style="transform:scale(.1);transform-origin:top left">@include('filament.partials.social-card', ['card' => $card, 'layout' => 'book'])</div></div>
            <x-filament::button size="sm" color="gray" icon="heroicon-o-arrow-down-tray" x-data
                x-on:click="window.hpCard.download($el.parentElement.querySelector('.hp-card'), 'hatpakha-{{ $issue->slug }}-book-cover.png')">{{ __('প্রচ্ছদ নামান') }}</x-filament::button>
        </div>
    </div>
    <div>
        <strong>{{ __('পাঠানোর আগে মিলিয়ে নিন') }}</strong>
        <ol style="margin:.3rem 0 0;padding-inline-start:1.3rem;list-style:decimal">
            <li>{{ __('লেখকদের অনুমতি: লেখার স্বত্ব লেখকের। বিক্রির জন্য বই করলে প্রত্যেক লেখকের লিখিত সম্মতি নিন (ই-মেলে "হ্যাঁ" যথেষ্ট), আর আয় কীভাবে ভাগ হবে তা জানান। বিনামূল্যে দিলেও জানিয়ে রাখুন।') }}</li>
            <li>{{ __('প্রচ্ছদের ছবি এআই-এ আঁকা হলে দোকানের প্রশ্নে তা জানান।') }}</li>
            <li>{{ __('EPUB নামিয়ে একবার ফোনের বই-পড়ার অ্যাপে খুলে দেখে নিন।') }}</li>
            <li>{{ __('দাম: বিনামূল্যে দিলে পাঠক বাড়ে; দাম রাখলে কম রাখুন।') }}</li>
        </ol>
    </div>
</div>
<script src="{{ asset('js/hp-card.js') }}?v=1"></script>
