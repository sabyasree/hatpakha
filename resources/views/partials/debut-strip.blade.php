{{-- নতুন কলম (owner, 2026-11-24): the latest first pieces on Hatpakha (last 30 days, up to three). Hidden when there are none. --}}
@php
    $debuts = \App\Support\Debut::latest(3);
@endphp
@if ($debuts->isNotEmpty())
    <section class="mx-auto max-w-[1320px] px-5 pb-12" aria-labelledby="debut-h" data-debut-strip>
        <div class="rounded-3xl border-2 border-navy bg-peach-light p-5 sm:p-7">
            <p class="font-hand text-2xl text-red">নতুন কলম</p>
            <h2 id="debut-h" class="font-display text-3xl font-extrabold leading-tight sm:text-4xl">নতুন কলম: প্রথম লেখা</h2>
            <p class="mt-1 text-ink2">গত এক মাসে যাঁদের প্রথম লেখা হাতপাখায় বেরোল। পড়ে লেখককে দু-কথা লিখে আসুন।</p>
            <ul class="mt-4 grid gap-x-8 divide-y divide-rule border-y border-rule sm:grid-cols-3 sm:divide-y-0 sm:border-0">
                @foreach ($debuts as $p)
                    <li><x-post-row :post="$p" :meta="($p->authors->first()?->publicName() ?? '').($p->section ? ' · '.$p->section->name_bn : '')" /></li>
                @endforeach
            </ul>
        </div>
    </section>
@endif
