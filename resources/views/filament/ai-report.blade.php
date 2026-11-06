{{-- AI report for editors only (advice, never a decision). All values escaped. --}}
@php($r = $review?->report)
<div class="space-y-4 text-sm leading-relaxed">
    @if (! $review)
        <p>এখনও কোনও এআই রিপোর্ট নেই।</p>
    @elseif ($review->status !== 'done')
        <p><strong>রিপোর্ট হয়নি:</strong> {{ $review->error }}</p>
    @else
        <div class="flex flex-wrap items-center gap-3">
            <span class="rounded-full bg-gray-100 px-3 py-1 text-base font-bold dark:bg-white/10">মান {{ $r['quality_score'] }}/১০</span>
            <span>{{ $r['genre'] ?? '' }}</span>
            <span>{{ ($r['fits_magazine'] ?? false) ? '✓ পত্রিকার উপযোগী' : '✗ পত্রিকার উপযোগী নয়' }}</span>
        </div>
        <p>{{ $r['summary'] ?? '' }}</p>
        @if (! empty($r['policy_flags']))
            <p class="font-bold text-danger-600">⚠ নীতি: {{ implode(', ', $r['policy_flags']) }}</p>
        @endif
        <div><strong>ভালো দিক</strong><ul class="ms-5 list-disc">@foreach ($r['strengths'] ?? [] as $s)<li>{{ $s }}</li>@endforeach</ul></div>
        <div><strong>লেখকের জন্য পরামর্শ</strong> <span class="text-gray-500">(চাইলে "বদল চাই" বার্তায় নিজের ভাষায় দিন)</span><ol class="ms-5 list-decimal">@foreach ($r['suggestions'] ?? [] as $s)<li>{{ $s }}</li>@endforeach</ol></div>
        @if (! empty($r['spelling']))
            <div><strong>বানান</strong><ul class="ms-5 list-disc">@foreach ($r['spelling'] as $sp)<li>{{ $sp['original'] ?? '' }} → {{ $sp['suggestion'] ?? '' }}</li>@endforeach</ul></div>
        @endif
        <div><strong>বিভাগ:</strong> {{ $r['suggested_section'] ?? '' }} · <strong>ট্যাগ:</strong> {{ implode(', ', $r['tags'] ?? []) }}</div>
        <div><strong>SEO:</strong> {{ $r['seo_title'] ?? '' }} — {{ $r['seo_description'] ?? '' }}</div>
        <p class="text-gray-500">{{ $r['fit_reason'] ?? '' }}</p>
        <p class="text-xs text-gray-500">{{ $review->provider }} · {{ $review->model }} · {{ $review->created_at?->diffForHumans() }}</p>
    @endif
</div>
