{{--
  "What to do with this piece": the top of a waiting submission's page (owner, 2026-11-12: "as admin I am also confused").
  One look: what the AI thinks, the three decisions with what each one does, the AI's choice marked.
  Inline styles: /desk ships only Filament's CSS. All values escaped.
--}}
@php
    use App\Support\Bn;
    use App\Filament\Resources\Posts\Tables\PostsTable;
    $post = $getRecord();
    $review = $post->latestAiReview;
    $r = $review?->status === 'done' ? $review->report : null;
    $rec = PostsTable::recommended($post);
    $writer = $post->submittedBy;
    $box = 'border:1px solid rgba(127,127,127,.28);border-radius:.85rem;padding:.9rem 1rem';
    $choices = [
        'approve' => [__('মনোনীত করুন'), 'success', __('প্রকাশের দিন বেছে নেবেন; ছোট সংশোধনগুলো বসবে; লেখক ই-মেলে খবর পাবেন।')],
        'requestChanges' => [__('বদল চাই'), 'warning', __('লেখাটি লেখকের কাছে ফেরে, সঙ্গে আপনার বার্তা (এআই-এর খসড়া তৈরি থাকে)। লেখক ঠিক করে আবার জমা দিলে এখানে ফিরবে।')],
        'reject' => [__('এবার নয়'), 'danger', __('লেখাটি নেওয়া হবে না। লেখক একটি সদয় ই-মেল পাবেন।')],
    ];
@endphp
<div style="{{ $box }};font-size:.95rem;line-height:1.7">
    <p style="font-weight:700;font-size:1.05rem;margin:0">{{ __('এই লেখা নিয়ে কী করবেন') }}</p>

    <ol style="margin:.5rem 0 0;padding-inline-start:1.2rem;list-style:decimal">
        <li>
            {{ __('নিচে লেখাটি পড়ুন।') }}
            @if ($r)
                {{ __('এআই-এর মত:') }}
                <strong>{{ __('মান') }} {{ Bn::num((string) ($r['quality_score'] ?? '')) }}{{ __('/১০') }}</strong>.
                {{ $r['summary'] ?? '' }}
                <x-filament::link tag="button" wire:click="mountAction('aiReport')" size="sm">{{ __('পুরো রিপোর্ট') }}</x-filament::link>
            @elseif ($review && $review->status !== 'done')
                {{ __('এআই রিপোর্ট হয়নি:') }} {{ $review->error }}
                <x-filament::link tag="button" wire:click="mountAction('runAi')" size="sm">{{ __('আবার পড়ান') }}</x-filament::link>
            @else
                {{ __('এআই এখনও পড়েনি (জমা দেওয়ার কয়েক মিনিটের মধ্যে পড়ে)।') }}
                <x-filament::link tag="button" wire:click="mountAction('runAi')" size="sm">{{ __('এখনই পড়ান') }}</x-filament::link>
            @endif
        </li>
        <li>{{ __('নিজে কিছু বদলাতে চাইলে নিচের লেখায় বদলে আগে “সংরক্ষণ” চাপুন।') }}</li>
        <li>{{ __('তারপর একটি সিদ্ধান্ত দিন। কোনও বোতাম চাপার পরেও আগে বার্তাটি দেখাবে; “পাঠান” না চাপা পর্যন্ত লেখকের কাছে কিছু যায় না।') }}</li>
    </ol>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(13rem,1fr));gap:.75rem;margin-top:.85rem">
        @foreach (PostsTable::decisionOrder($post) as $name)
            @php([$label, $color, $what] = $choices[$name])
            <div style="{{ $box }};{{ $rec === $name ? 'border-width:2px;border-color:#CB1B32' : '' }}">
                @if ($rec === $name)
                    <div style="margin-bottom:.4rem"><x-filament::badge :color="$color" size="sm">{{ __('এআই-এর প্রস্তাব') }}</x-filament::badge></div>
                @endif
                <x-filament::button :color="$rec === $name ? $color : 'gray'" wire:click="mountAction('{{ $name }}')">{{ $label }}</x-filament::button>
                <p style="margin:.5rem 0 0;font-size:.85rem;opacity:.8">{{ $what }}</p>
            </div>
        @endforeach
    </div>

    <p style="margin:.75rem 0 0;font-size:.85rem;opacity:.75">
        @if ($writer)
            {{ __('লেখকের ই-মেল:') }} {{ $writer->email }}
        @else
            {{ __('এই লেখার লেখকের অ্যাকাউন্ট নেই (পুরনো ওয়ার্ডপ্রেস জমা), তাই কোনও ই-মেল যাবে না।') }}
        @endif
    </p>
</div>
