{{-- Owner: the help button must never cover anything, so it sits in the page itself, not floating (D86). --}}
@if (config('ai.enabled') && ! \App\Ai\Team::paused('relations'))
<button type="button" data-chat-open aria-controls="hp-chat-box" aria-expanded="false" class="{{ $class ?? '' }} inline-flex min-h-11 flex-none items-center gap-1.5 min-w-11 justify-center rounded-full border-2 border-navy bg-white px-3 text-sm sm:px-4 font-bold hover:bg-sky-tint">
    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12z"/><path d="M9.6 9.5a2.5 2.5 0 1 1 3.4 2.3c-.6.3-1 .8-1 1.5M12 16.5h.01"/></svg>
    <span class="max-[379px]:sr-only">{{ __('প্রশ্ন আছে?') }}</span>
</button>
@endif
