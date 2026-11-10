{{-- Bengali page links (mobile UX review): big pills, "পাতা ২ / ১৮". --}}
@if ($paginator->hasPages())
    <nav role="navigation" aria-label="পাতা" class="flex flex-wrap items-center justify-between gap-3">
        @if ($paginator->onFirstPage())
            <span class="flex h-11 items-center rounded-full border-2 border-rule px-5 font-bold text-ink2 opacity-60" aria-disabled="true">← আগের পাতা</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="flex h-11 items-center rounded-full border-2 border-navy bg-white px-5 font-bold hover:bg-peach-light">← আগের পাতা</a>
        @endif
        <span class="text-sm font-bold text-ink2">পাতা {{ \App\Support\Bn::num((string) $paginator->currentPage()) }}@if (method_exists($paginator, 'lastPage')) / {{ \App\Support\Bn::num((string) $paginator->lastPage()) }}@endif</span>
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="flex h-11 items-center rounded-full bg-navy px-5 font-bold text-white">পরের পাতা →</a>
        @else
            <span class="flex h-11 items-center rounded-full border-2 border-rule px-5 font-bold text-ink2 opacity-60" aria-disabled="true">পরের পাতা →</span>
        @endif
    </nav>
@endif
