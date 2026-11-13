{{--
  D168: a human reviewer's feedback beside the AI's report. All values escaped.
  Inline styles: /desk only ships Filament's own CSS. $review: PieceReview · $ai: AiReview|null
--}}
@php
    use App\Models\PieceReview;
    use App\Support\Bn;
    $box = 'border:1px solid rgba(127,127,127,.25);border-radius:.75rem;padding:.75rem 1rem;margin-top:.75rem';
    $h = 'font-weight:700;margin:0 0 .35rem';
    $color = ['good' => 'success', 'changes' => 'warning', 'not_yet' => 'danger'][$review->verdict] ?? 'gray';
@endphp
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(18rem,1fr));gap:1.25rem;font-size:.95rem;line-height:1.75">
    <section>
        <p style="font-size:1.05rem;{{ $h }}">{{ __('যাচাইকারীর মত') }}</p>
        <div style="display:flex;flex-wrap:wrap;gap:.5rem;align-items:center">
            @if ($review->verdict)<x-filament::badge size="lg" :color="$color">{{ __(PieceReview::VERDICTS[$review->verdict] ?? $review->verdict) }}</x-filament::badge>@endif
            @if ($review->rating)<x-filament::badge color="gray">{{ __('সব মিলিয়ে') }} {{ Bn::num((string) $review->rating) }} {{ __('/ ৫') }}</x-filament::badge>@endif
        </div>
        @if ($review->request_note)
            <p style="margin:.6rem 0 0;opacity:.7">{{ __('ডেস্কের অনুরোধ ছিল:') }} {{ $review->request_note }}</p>
        @endif
        @if ($review->liked)
            <div style="{{ $box }}"><p style="{{ $h }}">{{ __('কেমন লাগল') }}</p><div style="white-space:pre-line">{{ $review->liked }}</div></div>
        @endif
        @if ($review->improve)
            <div style="{{ $box }}"><p style="{{ $h }}">{{ __('কী বদলালে আরও ভালো হয়') }}</p><div style="white-space:pre-line">{{ $review->improve }}</div></div>
        @endif
        @if ($review->after_ai)
            <div style="{{ $box }}"><p style="{{ $h }}">{{ __('এআই-এর রিপোর্ট পড়ে যা বললেন') }}</p><div style="white-space:pre-line">{{ $review->after_ai }}</div></div>
        @endif
        <p style="margin:.75rem 0 0;font-size:.8rem;opacity:.6">{{ __('যাচাইকারী আগে নিজের মত দিয়েছেন, তারপর এআই-এর রিপোর্ট দেখেছেন।') }}</p>
    </section>
    <section>
        <p style="font-size:1.05rem;{{ $h }}">{{ __('এআই-এর রিপোর্ট') }}</p>
        @include('filament.ai-report', ['review' => $ai])
    </section>
</div>
