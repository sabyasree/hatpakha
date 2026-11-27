{{-- D321: a certificate for a finished unit: the child's name is typed or taken from this phone, then printed; nothing is sent --}}
<x-layouts.learn :seo="$seo">
    <div data-learn-cert class="mx-auto max-w-[760px] p-4">
        <div class="print:hidden">
            <label for="cert-name" class="block font-display text-xl font-extrabold">নাম</label>
            <input id="cert-name" data-cert-input maxlength="30" class="mt-1 block min-h-12 w-full rounded-xl border-2 border-navy bg-white px-3 text-xl">
            <div class="mt-3 flex flex-wrap gap-2">
                <button type="button" data-cert-print class="min-h-12 rounded-full bg-red px-5 font-bold text-white">ছাপুন</button>
                <a href="{{ \App\Support\LearnCourse::url('start') }}" class="flex min-h-12 items-center rounded-full border-2 border-navy px-5 font-bold">ম্যাপে ফিরি</a>
            </div>
        </div>
        <section class="mt-6 rounded-3xl border-[6px] border-double border-red bg-paper p-8 text-center print:mt-0">
            <p class="font-hand text-5xl text-red">হাতপাখা</p>
            <p class="mt-2 font-display text-xl font-extrabold">বাংলা শিখি</p>
            <h1 class="mt-6 font-display text-4xl font-extrabold">শংসাপত্র</h1>
            <p class="mt-6 font-serif text-xl">এই শংসাপত্র দেওয়া হল</p>
            <p data-cert-name class="mx-auto mt-2 min-h-14 max-w-md border-b-2 border-navy font-display text-4xl font-extrabold">&nbsp;</p>
            <p class="mt-4 font-serif text-xl">@if ($u) ধাপ {{ \App\Support\Bn::num((string) $u['n']) }}: <b>{{ $u['title'] }}</b> শেষ করার জন্য। @else বাংলা শিখির একটি ধাপ শেষ করার জন্য। @endif</p>
            <p class="mt-6 text-5xl" aria-hidden="true">{{ $u['sticker'][0] ?? '⭐' }}</p>
            <p class="mt-6 text-base">{{ \App\Support\Bn::date(now()) }} · hatpakha.com</p>
        </section>
    </div>
</x-layouts.learn>
