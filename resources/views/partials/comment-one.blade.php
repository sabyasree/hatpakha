{{-- D252: one comment or reply: who, the writer's or the magazine's mark, when, what. Plain text, always escaped.
     $c, $isWriter, $isDesk, $small (a reply). --}}
<div class="flex gap-3">
    <span class="flex {{ $small ? 'h-8 w-8 text-sm' : 'h-10 w-10' }} flex-none items-center justify-center rounded-full font-extrabold {{ $isWriter ? 'bg-red text-white' : ($isDesk ? 'bg-navy text-white' : 'bg-peach-light text-red-dark') }}" aria-hidden="true">{{ mb_substr(trim((string) $c->author_name) ?: '?', 0, 1) }}</span>
    <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-baseline gap-x-2">
            <span class="font-bold">{{ $c->author_name }}</span>
            @if ($isWriter)<span class="rounded-full bg-red px-2 py-0.5 text-xs font-bold text-white">লেখক</span>@endif
            @if ($isDesk)<span class="rounded-full bg-navy px-2 py-0.5 text-xs font-bold text-white">হাতপাখা</span>@endif
            <span class="text-sm text-ink2">{{ \App\Support\Bn::date($c->created_at) }}</span>
        </div>
        <p class="mt-1 whitespace-pre-line break-words leading-relaxed">{{ $c->body }}</p>
    </div>
</div>
