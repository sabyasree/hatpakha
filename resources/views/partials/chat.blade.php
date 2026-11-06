{{-- Relationship manager chat (D52): only on the submission pages. Plain text in and out (textContent). --}}
@if (config('ai.enabled') && ! \App\Ai\Team::paused('relations'))
<div id="hp-chat" data-url="{{ route('chat') }}" data-token="{{ csrf_token() }}" class="fixed bottom-20 right-4 z-50 flex flex-col items-end gap-2 md:bottom-4">
    <div id="hp-chat-box" hidden class="flex h-[min(26rem,calc(100dvh-10rem))] w-[min(22rem,calc(100vw-2rem))] flex-col overflow-hidden rounded-2xl border border-rule bg-paper shadow-xl">
        <div class="flex items-center justify-between bg-navy px-4 py-3 text-white">
            <strong class="font-display">হাতপাখা সহায়ক</strong>
            <button type="button" id="hp-chat-close" class="text-xl leading-none" aria-label="বন্ধ করুন">×</button>
        </div>
        <div id="hp-chat-log" class="flex-1 space-y-2 overflow-y-auto p-3 text-[15px] leading-relaxed" aria-live="polite">
            <p class="rounded-xl bg-sky-tint p-2.5 text-sky-night">নমস্কার! লেখা পাঠানোর নিয়ম, নিবন্ধন বা জমা দেওয়া নিয়ে প্রশ্ন করুন।@auth আপনার নিজের লেখার খবরও জানাতে পারি।@endauth</p>
        </div>
        <form id="hp-chat-form" class="flex gap-2 border-t border-rule p-2">
            <label for="hp-chat-q" class="sr-only">প্রশ্ন</label>
            <input id="hp-chat-q" maxlength="{{ \App\Ai\ChatBot::MAX_CHARS }}" autocomplete="off" required class="min-w-0 flex-1 rounded-lg border border-rule bg-white px-3 py-2" placeholder="প্রশ্ন লিখুন…">
            <button class="rounded-lg bg-red px-3 py-2 font-semibold text-white">পাঠান</button>
        </form>
        <p class="px-3 pb-2 text-xs text-ink2">এআই সহায়ক; ভুল হতে পারে। কোনো কিছু বদলাতে বা প্রকাশ করতে পারে না।</p>
    </div>
    <button type="button" id="hp-chat-open" class="rounded-full bg-red px-5 py-3 font-semibold text-white shadow-lg">প্রশ্ন আছে?</button>
</div>
<script src="{{ asset('js/hp-chat.js') }}?v=2" defer></script>
@endif
