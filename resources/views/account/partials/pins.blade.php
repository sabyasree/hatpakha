{{-- 2026-11-24: the writer's portfolio. Pin up to three published pieces as "নির্বাচিত লেখা" (shown first on the writer
     page), and the printable লেখক-পরিচিতি. Works without JavaScript; the server keeps only the writer's own live pieces. --}}
@php
    $mine = \App\Models\Post::published()->whereHas('authors', fn ($q) => $q->whereKey($author->id))->with('section')->latest('published_at')->limit(60)->get(['posts.id', 'posts.title', 'posts.published_at', 'posts.section_id']);
    $chosen = array_map('intval', (array) old('pins', (array) $author->pinned_post_ids));
@endphp
@if ($mine->isNotEmpty())
    <section id="pins" class="mt-10 max-w-xl scroll-mt-24" aria-labelledby="pins-h">
        <h2 id="pins-h" class="font-display text-2xl font-extrabold">{{ __('নির্বাচিত লেখা') }}</h2>
        <p class="mt-1 text-ink2">{{ __('নিজের সবচেয়ে প্রিয় তিনটি লেখা বেছে নিন। লেখক-পাতায় এগুলো সবার আগে দেখা যাবে।') }}</p>
        @error('pins')<p class="mt-2 font-bold text-red" role="alert">{{ $message }}</p>@enderror
        <form method="POST" action="{{ \App\Support\Url::to('/account/pins') }}" class="mt-3">
            @csrf
            <fieldset class="max-h-[28rem] divide-y divide-rule overflow-y-auto rounded-xl border-2 border-navy bg-white">
                <legend class="sr-only">{{ __('তিনটি পর্যন্ত লেখা বাছুন') }}</legend>
                @foreach ($mine as $p)
                    <label class="flex min-h-14 cursor-pointer items-center gap-3 px-4 py-2">
                        <input type="checkbox" name="pins[]" value="{{ $p->id }}" @checked(in_array($p->id, $chosen, true)) class="accent-red">
                        <span class="min-w-0"><span class="block font-serif text-lg font-bold leading-snug">{{ $p->title }}</span>
                            <span class="block text-sm text-ink2">{{ $p->section?->name_bn }} · {{ \App\Support\Bn::date($p->published_at) }}</span></span>
                    </label>
                @endforeach
            </fieldset>
            <p class="mt-2 text-sm text-ink2">{{ __('তিনটির বেশি বাছবেন না। কিছুই না বাছলে লেখক-পাতায় শুধু নতুন লেখা আগে থাকে।') }}</p>
            <button class="mt-3 h-14 w-full rounded-full border-2 border-navy bg-white text-lg font-bold hover:bg-peach-light">{{ __('বাছাই সেভ করুন') }}</button>
        </form>
        <a href="{{ \App\Support\Url::to('/lekhok-porichiti/'.rawurlencode($author->slug)) }}" class="mt-6 flex min-h-14 items-center justify-between gap-3 rounded-xl border-2 border-navy bg-peach-light px-4 py-2 font-bold hover:bg-peach">
            <span>{{ __('লেখক-পরিচিতি (PDF)') }}<span class="block text-sm font-medium text-ink2">{{ __('আপনার সব প্রকাশিত লেখা, শংসাপত্র আর সম্মান এক পাতায়। ছেপে বা PDF করে রাখুন।') }}</span></span><span aria-hidden="true">›</span></a>
    </section>
@endif
