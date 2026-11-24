{{-- A declined piece in the writer's own view (owner, 2026-11-24; research §B1): the editor's letter as it was sent
     (the copy kept by D161), then "এরপর কী করবেন". A kind, visible no. --}}
@php
    use App\Support\Bn;
    use App\Support\Url;
    $letter = \App\Support\WriterJourney::declineLetter($p);
    $from = \App\Support\WriterJourney::resubmitFrom($p);
    $kolom = \App\Models\ReaderPrompt::current();
    $others = \App\Http\Controllers\SubmissionController::sections()->where('id', '!=', $p->section_id)->take(6);
    $card = 'flex min-h-12 items-center justify-between gap-3 rounded-xl border-2 border-navy bg-white px-4 py-2 font-bold hover:bg-peach-light';
@endphp
<section class="mt-6 rounded-2xl border-2 border-navy bg-white p-5" aria-labelledby="letter-h" data-decline-letter>
    <h2 id="letter-h" class="font-display text-2xl font-extrabold">{{ __('সম্পাদকের চিঠি') }}</h2>
    @if ($letter)
        <p class="mt-1 text-sm text-ink2">{{ __('ই-মেলে পাঠানো হয়েছিল :date', ['date' => Bn::date($letter['sent_at'])]) }}</p>
        <div class="mt-4 space-y-3 font-serif text-lg leading-relaxed">
            @foreach ($letter['paras'] as $para)
                @if ($para['kind'] === 'h')
                    <p class="font-display text-xl font-extrabold leading-snug">{{ $para['text'] }}</p>
                @elseif ($para['kind'] === 'note')
                    <p class="whitespace-pre-line rounded-r-xl border-l-4 border-red bg-peach-light p-3">{{ $para['text'] }}</p>
                @else
                    <p>{{ $para['text'] }}</p>
                @endif
            @endforeach
        </div>
    @elseif ($p->review_note)
        @if ($p->reviewed_at)<p class="mt-1 text-sm text-ink2">{{ Bn::date($p->reviewed_at) }}</p>@endif
        <p class="mt-4 whitespace-pre-line rounded-r-xl border-l-4 border-red bg-peach-light p-3 font-serif text-lg leading-relaxed"><strong>{{ __('সম্পাদকের কথা:') }}</strong> {{ $p->review_note }}</p>
    @else
        <p class="mt-3 text-lg">{{ __('লেখাটি এবার নেওয়া গেল না। এই সিদ্ধান্ত একটি লেখা নিয়ে, আপনার লেখালেখি নিয়ে নয়।') }}</p>
    @endif
</section>

<section class="mt-6 rounded-2xl border-2 border-navy bg-sky-tint p-5" aria-labelledby="next-h" data-what-next>
    <h2 id="next-h" class="font-display text-2xl font-extrabold">{{ __('এরপর কী করবেন') }}</h2>
    <p class="mt-1 text-lg">{{ __('একটা “না” মানে শেষ নয়। এর মধ্যে যে কোনও একটা বেছে নিন:') }}</p>
    <ul class="mt-4 space-y-3">
        <li class="rounded-xl bg-white p-4">
            <p class="font-bold">{{ __('লেখাটি নতুন করে সাজিয়ে আবার পাঠান') }}</p>
            @if ($from && $from->isFuture())
                <p class="mt-1 text-ink2">{{ __('চিঠির কথাগুলো ভেবে, বদলে নতুন লেখা হিসেবে পাঠাতে পারেন :date থেকে। একটু সময় নিলে লেখাটিকে নতুন চোখে দেখা যায়।', ['date' => Bn::date($from)]) }}</p>
            @else
                <p class="mt-1 text-ink2">{{ __('চিঠির কথাগুলো ভেবে, বদলে এখন নতুন লেখা হিসেবে পাঠাতে পারেন।') }}</p>
                <a href="{{ Url::to('/submit') }}" class="mt-2 inline-flex min-h-11 items-center rounded-full bg-red px-5 font-bold text-white">{{ __('নতুন লেখা পাঠান') }}</a>
            @endif
        </li>
        @if ($kolom && $kolom->isOpen())
            <li><a href="{{ Url::to('/kolom') }}" class="{{ $card }}"><span>{{ __('এ সপ্তাহের পাঠকের কলম') }}<span class="block font-serif text-lg font-bold text-navy">“{{ $kolom->title }}”</span><span class="block text-sm font-medium text-ink2">{{ __('ছোট লেখা, এক মিনিটেই পাঠানো যায়') }}</span></span><span aria-hidden="true">›</span></a></li>
        @endif
        <li><a href="{{ Url::to('/lekhar-dak') }}" class="{{ $card }}"><span>{{ __('লেখার ডাক') }}<span class="block text-sm font-medium text-ink2">{{ __('সামনের সংখ্যা, প্রতিযোগিতা আর সম্পাদক যা খুঁজছেন') }}</span></span><span aria-hidden="true">›</span></a></li>
        @if ($others->isNotEmpty())
            <li class="rounded-xl bg-white p-4">
                <p class="font-bold">{{ __('অন্য বিভাগেও লিখে দেখুন') }}</p>
                <ul class="mt-2 flex flex-wrap gap-2">
                    @foreach ($others as $s)
                        <li><a href="{{ Url::to('/submit') }}?section={{ rawurlencode($s->slug) }}" class="inline-flex min-h-11 items-center rounded-full border-2 border-navy bg-white px-4 text-sm font-bold hover:bg-peach-light">{{ $s->name_bn }}</a></li>
                    @endforeach
                </ul>
            </li>
        @endif
    </ul>
</section>
