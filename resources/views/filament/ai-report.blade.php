{{--
  AI report for editors only (advice, never a decision). All values escaped.
  Inline styles: /desk only ships Filament's own CSS, so utility classes don't exist here.
  $review: AiReview|null · $blind: hide provider/model (blind test)
--}}
@php
    use App\Support\Bn;
    // An old or cut-short report may lack fields: fill them in, so the dialog always opens (D191).
    $r = $review?->report ? rescue(fn () => \App\Ai\ReviewSchema::normalise((array) $review->report), (array) $review->report, false) : null;
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

        {{-- D208: text that is not part of the work, and the AI's own doubts: these keep a piece from going by itself --}}
        @if (! empty($r['stray_text']) || ! empty($r['doubts']))
            <div style="margin-top:.75rem;border:2px solid #CB1B32;border-radius:.75rem;padding:.75rem 1rem">
                <p style="font-weight:700;margin:0 0 .35rem;color:#CB1B32">{{ __('প্রকাশের আগে আপনি দেখে নিন') }}</p>
                @foreach ((array) ($r['stray_text'] ?? []) as $x)
                    <p style="margin:.2rem 0"><strong>{{ __('লেখার অংশ নয় বলে মনে হচ্ছে:') }}</strong> “{{ $x['text'] ?? '' }}” @if (! empty($x['why']))<span style="opacity:.8">({{ $x['why'] }})</span>@endif</p>
                @endforeach
                @foreach ((array) ($r['doubts'] ?? []) as $d)
                    <p style="margin:.2rem 0"><strong>{{ __('এআই নিশ্চিত নয়:') }}</strong> {{ $d }}</p>
                @endforeach
                <p style="margin:.35rem 0 0;opacity:.8;font-size:.9rem">{{ __('এআই নিজে কিছু সরায়নি। সরাতে হলে লেখাটি সম্পাদনা করে সরান, তারপর মনোনীত করুন।') }}</p>
            </div>
        @endif
        @if ($review->post && $review->post->status === 'pending' && \App\Support\Handover::on('publish'))
            @php $autoWhy = rescue(fn () => \App\Support\Handover::hold($review->post), null, false); @endphp
            <p style="margin:.6rem 0 0;font-size:.92rem"><strong>{{ __('নিজে থেকে প্রকাশ:') }}</strong>
                @if ($review->post->auto_publish_at) {{ __('হবে') }}, {{ Bn::num($review->post->auto_publish_at->copy()->timezone('Asia/Kolkata')->format('d/m, H:i')) }} {{ __('-এর পরে, আপনি না থামালে।') }}
                @elseif ($autoWhy) {{ __('হবে না।') }} {{ $autoWhy }}
                @else {{ __('শর্ত মিলেছে; একটু পরে সময় বসবে।') }} @endif</p>
        @endif

        @if (! empty($r['theme_fit']))
            {{-- D204: a suggestion only; the editor sets the issue on the piece --}}
            <p style="margin:.6rem 0 0"><strong>{{ __('ফিরে আসা সংখ্যার বিষয়ের সঙ্গে:') }}</strong>
                <x-filament::badge :color="['yes' => 'success', 'partly' => 'warning', 'no' => 'gray'][$r['theme_fit']] ?? 'gray'" style="display:inline-flex">{{ ['yes' => __('মেলে'), 'partly' => __('কিছুটা মেলে'), 'no' => __('মেলে না')][$r['theme_fit']] ?? $r['theme_fit'] }}</x-filament::badge>
                @if (! empty($r['theme_reason'])) <span style="opacity:.8">{{ $r['theme_reason'] }}</span>@endif
                <span style="opacity:.7">· {{ $review->post?->issue?->slug === \App\Support\WriterInvites::ISSUE_SLUG ? __('লেখাটি এই সংখ্যার জন্য চিহ্নিত') : __('লেখাটি এই সংখ্যার জন্য চিহ্নিত নয়') }}</span></p>
        @endif

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
