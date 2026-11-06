{{--
  AI report for editors only (advice, never a decision). All values escaped.
  Inline styles: /desk only ships Filament's own CSS, so utility classes don't exist here.
  $review: AiReview|null · $blind: hide provider/model (blind test)
--}}
@php
    use App\Support\Bn;
    $r = $review?->report;
    $blind = $blind ?? false;
    $sectionName = fn ($slug) => \App\Models\Section::where('slug', $slug)->value('name_bn') ?? $slug;
    $box = 'border:1px solid rgba(127,127,127,.25);border-radius:.75rem;padding:.75rem 1rem;margin-top:.75rem';
    $h = 'font-weight:700;margin:0 0 .35rem';
@endphp
<div style="font-size:.95rem;line-height:1.75">
    @if (! $review)
        <p>{{ __('এখনও কোনও এআই রিপোর্ট নেই।') }}</p>
    @elseif ($review->status !== 'done')
        <p><strong>{{ __('রিপোর্ট হয়নি:') }}</strong> {{ $review->error }}</p>
    @else
        <div style="display:flex;flex-wrap:wrap;gap:.5rem;align-items:center">
            <x-filament::badge size="lg" :color="$r['quality_score'] >= 7 ? 'success' : ($r['quality_score'] >= 5 ? 'warning' : 'gray')">
                {{ __('মান') }} {{ Bn::num((string) $r['quality_score']) }} {{ __('/ ১০') }}
            </x-filament::badge>
            <x-filament::badge :color="($r['fits_magazine'] ?? false) ? 'success' : 'danger'">
                {{ ($r['fits_magazine'] ?? false) ? __('পত্রিকার উপযোগী') : __('পত্রিকার উপযোগী নয়') }}
            </x-filament::badge>
            @if (! empty($r['genre']))<span style="opacity:.75">{{ $r['genre'] }}</span>@endif
        </div>

        @if (! empty($r['decision']))
            @php($d = ['publish' => [__('প্রস্তাব: প্রকাশ করা যায়'), 'success'], 'changes' => [__('প্রস্তাব: লেখককে বদল চাওয়া'), 'warning'], 'decline' => [__('প্রস্তাব: এবার নয়'), 'gray']][$r['decision']] ?? null)
            @if ($d)<div style="margin-top:.6rem"><x-filament::badge :color="$d[1]">{{ $d[0] }}</x-filament::badge></div>@endif
        @endif
        <p style="margin:.75rem 0 0">{{ $r['summary'] ?? '' }}</p>
        @if (! empty($r['note_to_writer']))
            <div style="{{ $box }}"><p style="{{ $h }}">{{ __('লেখককে খসড়া বার্তা') }}</p>{{ $r['note_to_writer'] }}</div>
        @endif

        @if (! empty($r['policy_flags']))
            <div style="{{ $box }};border-color:#B0162B"><p style="{{ $h }};color:#B0162B">{{ __('সাবধান') }}</p>{{ implode(', ', $r['policy_flags']) }}</div>
        @endif

        @if (! empty($r['strengths']))
            <div style="{{ $box }}"><p style="{{ $h }}">{{ __('ভালো দিক') }}</p>
                <ul style="margin:0;padding-inline-start:1.2rem;list-style:disc">@foreach ($r['strengths'] as $s)<li>{{ $s }}</li>@endforeach</ul></div>
        @endif

        @if (! empty($r['suggestions']))
            <div style="{{ $box }}"><p style="{{ $h }}">{{ __('লেখকের জন্য পরামর্শ') }}</p>
                <ol style="margin:0;padding-inline-start:1.2rem;list-style:decimal">@foreach ($r['suggestions'] as $s)<li>{{ $s }}</li>@endforeach</ol></div>
        @endif

        @if (! empty($r['edits']))
            <details style="{{ $box }}"><summary style="cursor:pointer;font-weight:700">{{ __('সংশোধন (') }}{{ Bn::num(count($r['edits'])) }}{{ __('টি)') }}</summary>
                <ul style="margin:.4rem 0 0;padding-inline-start:1.2rem;list-style:disc">@foreach ($r['edits'] as $e)<li>{{ $e['original'] ?? '' }} → <strong>{{ $e['replacement'] ?? '' }}</strong> <span style="opacity:.65">· {{ __(\App\Ai\CopyEdit::LABELS[$e['kind'] ?? 'word'] ?? '') }}</span></li>@endforeach</ul></details>
        @endif
        @if (! empty($r['excerpt']))
            <p style="margin:.75rem 0 0"><strong>{{ __('এক লাইনের পরিচিতি:') }}</strong> {{ $r['excerpt'] }}</p>
        @endif
        @if (! empty($r['spelling']))
            <details style="{{ $box }}"><summary style="cursor:pointer;font-weight:700">{{ __('বানান (') }}{{ Bn::num(count($r['spelling'])) }}{{ __('টি)') }}</summary>
                <ul style="margin:.4rem 0 0;padding-inline-start:1.2rem;list-style:disc">@foreach ($r['spelling'] as $sp)<li>{{ $sp['original'] ?? '' }} → <strong>{{ $sp['suggestion'] ?? '' }}</strong></li>@endforeach</ul></details>
        @endif

        <details style="{{ $box }}"><summary style="cursor:pointer;font-weight:700">{{ __('বিভাগ, ট্যাগ, গুগলের জন্য') }}</summary>
            <p style="margin:.4rem 0 0"><strong>{{ __('বিভাগ:') }}</strong> {{ $sectionName($r['suggested_section'] ?? '') }}</p>
            <p style="margin:0"><strong>{{ __('ট্যাগ:') }}</strong> {{ implode(', ', $r['tags'] ?? []) }}</p>
            <p style="margin:0"><strong>{{ __('শিরোনাম:') }}</strong> {{ $r['seo_title'] ?? '' }}</p>
            <p style="margin:0"><strong>{{ __('বিবরণ:') }}</strong> {{ $r['seo_description'] ?? '' }}</p>
            @if (! empty($r['fit_reason']))<p style="margin:.4rem 0 0;opacity:.75">{{ $r['fit_reason'] }}</p>@endif
        </details>

        @unless ($blind)
            <p style="margin:.75rem 0 0;font-size:.8rem;opacity:.6">{{ $review->provider === 'anthropic' ? 'Claude' : 'OpenAI' }} · {{ Bn::num((string) $review->created_at?->diffForHumans()) }}</p>
        @endunless
    @endif
</div>
