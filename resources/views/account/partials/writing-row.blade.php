{{-- One piece in "আমার লেখা": title, form and date, a status chip, and (for published pieces) a ⋯ menu. --}}
@php
    use App\Support\Bn;
    use App\Support\Url;
    $S = \App\Http\Controllers\AccountController::STATUS;
    [$label] = $S[$p->status] ?? [$p->status];
    $cert = isset($certificates) ? $certificates->first(fn ($c) => $c->post_id === $p->id && $c->kind === 'publication') : null;
    $chip = match ($p->status) {
        'published', 'scheduled' => 'bg-sky-tint text-sky-night',
        'changes', 'confirm' => 'bg-red text-white',
        default => 'bg-peach-light text-red-dark',
    };
    $editable = in_array($p->status, ['draft', 'changes', 'confirm'], true);
@endphp
<li class="relative flex items-center gap-2 px-4 py-3" data-row>
    <a href="{{ $p->status === 'published' ? $p->url() : Url::to('/submit/'.$p->id) }}" class="min-w-0 flex-1 py-1">
        <span class="block font-serif text-lg font-bold leading-snug">{{ $p->title ?: __('শিরোনাম ছাড়া খসড়া') }}</span>
        <span class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-ink2">
            <span class="rounded-full px-2 py-0.5 text-xs font-bold {{ $chip }}">{{ __($label) }}</span>
            {{ $p->section?->name_bn }} · {{ Bn::date($p->published_at ?? $p->submitted_at ?? $p->updated_at) }}
            @if ($p->audio_status === 'approved')<span title="{{ __('লেখকের গলায় অডিও আছে') }}" aria-label="{{ __('অডিও আছে') }}">🎙</span>@endif
            @if ($cert)<span title="{{ __('সার্টিফিকেট আছে') }}" class="text-red"><svg class="inline h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" role="img" aria-label="{{ __('সার্টিফিকেট আছে') }}"><circle cx="12" cy="9" r="6"/><path d="m8.5 14-1.5 7 5-3 5 3-1.5-7"/></svg></span>@endif
        </span>
    </a>
    @if ($editable)
        <a href="{{ Url::to('/submit/'.$p->id) }}" class="flex h-11 flex-none items-center rounded-full {{ $p->status === 'draft' ? 'border-2 border-navy' : 'bg-red text-white' }} px-4 text-sm font-bold">{{ __(['changes' => 'ঠিক করুন', 'confirm' => 'সম্মতি দিন', 'draft' => 'লিখতে থাকুন'][$p->status]) }}</a>
    @elseif (in_array($p->status, ['published', 'scheduled'], true))
        {{-- ⋯ menu: everything extra for this piece, out of the list's way --}}
        <details class="relative flex-none" data-row-menu>
            <summary class="flex h-11 w-11 cursor-pointer list-none items-center justify-center rounded-full hover:bg-peach-light [&::-webkit-details-marker]:hidden" aria-label="{{ __('আরও: :title', ['title' => $p->title]) }}">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="5" cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/></svg>
            </summary>
            {{-- Phones: a bottom sheet over everything (UX review); wider screens: a small popover --}}
            <button type="button" data-close-menu class="fixed inset-0 z-50 bg-navy/40 sm:hidden" aria-label="{{ __('মেনু বন্ধ করুন') }}"></button>
            <div class="fixed inset-x-0 bottom-0 z-50 max-h-[80vh] overflow-y-auto rounded-t-3xl border-t-2 border-navy bg-paper p-3 pb-[calc(1rem+env(safe-area-inset-bottom))] shadow-[0_-4px_0_#13212C] sm:absolute sm:inset-x-auto sm:bottom-auto sm:right-0 sm:top-12 sm:z-20 sm:max-h-none sm:w-80 sm:rounded-2xl sm:border-2 sm:p-2 sm:pb-2 sm:shadow-[4px_4px_0_#13212C]">
                <div class="mb-1 flex items-center justify-between px-3 sm:hidden"><strong class="truncate font-serif">{{ $p->title }}</strong><button type="button" data-close-menu class="flex h-11 items-center rounded-full px-3 font-bold text-ink2">{{ __('✕ বন্ধ') }}</button></div>
                @if ($p->status === 'published')
                    <a href="{{ $p->url() }}" class="flex min-h-12 items-center rounded-xl px-3 font-bold hover:bg-peach-light">{{ __('লেখাটি দেখুন') }}</a>
                    <a href="https://wa.me/?text={{ rawurlencode($p->title.' — '.$p->url()) }}" target="_blank" rel="noopener" class="flex min-h-11 items-center rounded-xl px-3 font-bold hover:bg-peach-light">{{ __('শেয়ার করুন') }}</a>
                    @if ($cert)
                        <a href="{{ $cert->verifyUrl() }}" class="flex min-h-11 items-center rounded-xl px-3 font-bold hover:bg-peach-light">{{ __('সার্টিফিকেট দেখুন') }}</a>
                    @elseif (in_array($p->id, $certRequested ?? [], true))
                        <span class="flex min-h-11 items-center px-3 text-ink2">{{ __('সার্টিফিকেট চাওয়া হয়েছে') }}</span>
                    @else
                        <form method="POST" action="{{ Url::to('/account/certificate/'.$p->id) }}">@csrf
                            <button class="flex min-h-11 w-full items-center rounded-xl px-3 text-left font-bold hover:bg-peach-light">{{ __('সার্টিফিকেট চাই') }}</button></form>
                    @endif
                @endif
                @if (in_array($p->status, ['pending', 'scheduled', 'confirm', 'published'], true))
                    @include('account.partials.change-request', ['p' => $p])
                @endif
                {{-- D67: the writer's own reading --}}
                <div class="mt-1 border-t border-rule px-3 pt-2 text-sm">
                    <div class="font-bold">{{ __('নিজের গলায় অডিও') }}
                        @if ($p->audio_status === 'approved')<span class="font-normal text-sky-night">· {{ __('লেখায় যোগ হয়েছে') }}</span>
                        @elseif ($p->audio_status === 'pending')<span class="font-normal text-ink2">· {{ __('সম্পাদক দেখছেন') }}</span>@endif</div>
                    @php $audioLocked = $p->status === 'published' && $p->audio_status === 'approved'; @endphp
                    @if ($p->audio_path)
                        <audio controls preload="none" src="{{ $p->audio_path }}" class="mt-2 w-full"></audio>
                        @unless ($audioLocked)
                            <form method="POST" action="{{ Url::to('/account/audio/'.$p->id) }}" class="mt-1">@csrf<input type="hidden" name="remove" value="1">
                                <button class="min-h-11 font-bold text-red underline">{{ __('অডিও সরান') }}</button></form>
                        @endunless
                    @endif
                    @if ($audioLocked)
                        <p class="mt-1 text-xs text-ink2">{{ __('প্রকাশিত অডিও বদলাতে "বদল চাই" টিপে সম্পাদককে লিখুন।') }}</p>
                    @else
                    <form method="POST" action="{{ Url::to('/account/audio/'.$p->id) }}" enctype="multipart/form-data" class="mt-1">@csrf
                        <label class="flex min-h-11 cursor-pointer items-center justify-center rounded-full border-2 border-navy px-4 font-bold">
                            {{ $p->audio_path ? __('নতুন অডিও দিন') : __('অডিও যোগ করুন') }}
                            <input type="file" name="audio" accept="audio/mpeg,audio/mp4,audio/x-m4a,audio/aac,audio/ogg,.mp3,.m4a,.ogg" class="sr-only" data-auto-submit>
                        </label>
                    </form>
                    <p class="mt-1 text-xs text-ink2">{{ __('ফোনে রেকর্ড করা MP3/M4A, ২৫ MB পর্যন্ত।') }}</p>
                    @endif
                </div>
            </div>
        </details>
    @endif
</li>
