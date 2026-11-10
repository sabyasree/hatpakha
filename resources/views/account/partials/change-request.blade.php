{{-- D94: writers can't change a submitted or published piece; they ask the editor. --}}
<details class="{{ $class ?? '' }}" @if (session('change_post') === $p->id) open @endif>
    <summary class="flex min-h-11 cursor-pointer list-none items-center rounded-xl px-3 font-bold hover:bg-peach-light [&::-webkit-details-marker]:hidden">বদল চাই</summary>
    <form method="POST" action="{{ \App\Support\Url::to('/account/change/'.$p->id) }}" class="px-3 pb-2">@csrf
        <p class="text-sm text-ink2">{{ $p->status === 'published' ? 'প্রকাশিত' : 'জমা দেওয়া' }} লেখা আপনি নিজে বদলাতে পারবেন না। কোথায় কী বদলাতে চান লিখুন, সম্পাদক দেখে ঠিক করে দেবেন।</p>
        <label class="mt-2 block"><span class="sr-only">কী বদলাতে চান</span>
            <textarea name="what" rows="3" required minlength="5" maxlength="1500" class="block w-full rounded-xl border-2 border-navy bg-white px-3 py-2" placeholder="যেমন: তৃতীয় স্তবকের দ্বিতীয় লাইনে “…” হবে “…”"></textarea></label>
        @if (session('change_post') === $p->id)
            @foreach ($errors->getBag('change')->all() as $e)<p class="mt-1 font-semibold text-red">{{ $e }}</p>@endforeach
        @endif
        <button class="mt-2 h-11 rounded-full bg-navy px-5 font-bold text-white">সম্পাদককে পাঠান</button>
    </form>
</details>
