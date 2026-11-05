<x-layouts.app :seo="$seo">
    <section class="mx-auto max-w-[680px] px-5 py-14 text-center">
        <div class="font-hand text-3xl text-red">প্রকাশনা সনদ যাচাই</div>
        @if ($certificate->isValid())
            <div class="mt-6 rounded-3xl border-2 border-navy bg-white p-8 shadow-[6px_6px_0_#13212C]">
                <div class="text-sm font-bold text-[#1E6B35]">✓ এই সনদটি বৈধ</div>
                <div class="mt-3 font-display text-4xl font-extrabold">{{ $certificate->author_name_snapshot }}</div>
                <div class="mt-2 font-serif text-xl">“{{ $certificate->title_snapshot }}”</div>
                <div class="mt-2 text-ink2">{{ $certificate->issue_label }} · {{ \App\Support\Bn::date($certificate->issued_at) }}</div>
                <div class="mt-4 text-sm text-ink2">হাতপাখা · ISSN 2321-3035 · কোড {{ $certificate->code }}</div>
            </div>
        @else
            <div class="mt-6 rounded-3xl border-2 border-red bg-white p-8 text-red">এই সনদটি বাতিল করা হয়েছে।</div>
        @endif
    </section>
</x-layouts.app>
