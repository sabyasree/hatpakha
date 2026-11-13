{{-- Certificate check + the certificate itself (D12, D71): anyone with the code sees it; the writer downloads it here. --}}
@php
    $c = $certificate;
    $file = 'hatpakha-shongshapotro-'.$c->theme().'-'.$c->code;
    $share = 'হাতপাখার '.\App\Models\Certificate::THEMES[$c->theme()][0].': '.$c->verifyUrl();
@endphp
<x-layouts.app :seo="$seo">
    <style>
        .hp-cert-frame { width: 100%; aspect-ratio: 1600 / 1131; position: relative; overflow: hidden; border-radius: 1rem; box-shadow: 6px 6px 0 #13212C; }
        .hp-cert-frame > .hp-cert-sheet { position: absolute; top: 0; left: 0; transform-origin: top left; }
        @media print {
            @page { size: A4 landscape; margin: 0; }
            html, body { height: 210mm; overflow: hidden; margin: 0; }
            body * { visibility: hidden; }
            .hp-cert-frame, .hp-cert-frame * { visibility: visible; }
            .hp-cert-frame { position: fixed; inset: 0; width: 297mm; height: 210mm; box-shadow: none; border-radius: 0; aspect-ratio: auto; }
            .hp-cert-frame > .hp-cert-sheet { transform: scale(0.7) !important; }
        }
    </style>
    <section class="mx-auto max-w-[1100px] px-5 py-10">
        @if (session('status'))
            {{-- D166: what to do next, right after the writer made it (screen only) --}}
            <div role="status" class="mb-6 flex items-start gap-2 rounded-2xl bg-navy p-4 font-bold text-white print:hidden"><span aria-hidden="true">✓</span><span>{{ session('status') }}</span></div>
        @endif
        <div class="text-center font-hand text-3xl text-red">শংসাপত্র যাচাই</div>
        @if ($c->isValid())
            <p class="mt-3 text-center font-bold text-sky-night">✓ এই শংসাপত্রটি বৈধ। হাতপাখা {{ \App\Support\Bn::date($c->issued_at) }} এটি দিয়েছে।</p>
            <div class="hp-cert-frame mt-6" data-cert>
                <x-certificate-sheet :certificate="$c" />
            </div>
            <div class="mt-6 flex flex-wrap justify-center gap-3">
                <button type="button" data-cert-png="{{ $file }}.png" class="flex h-12 items-center rounded-full bg-red px-6 font-bold text-white">ছবি নামান (PNG)</button>
                <button type="button" data-cert-print class="flex h-12 items-center rounded-full border-2 border-navy px-6 font-bold">PDF / প্রিন্ট</button>
                <a href="https://wa.me/?text={{ rawurlencode($share) }}" rel="noopener" target="_blank" class="flex h-12 items-center rounded-full border-2 border-navy px-6 font-bold">WhatsApp</a>
                <a href="https://www.facebook.com/sharer/sharer.php?u={{ rawurlencode($c->verifyUrl()) }}" rel="noopener" target="_blank" class="flex h-12 items-center rounded-full border-2 border-navy px-6 font-bold">Facebook</a>
            </div>
            <p class="mt-3 text-center text-sm text-ink2">PDF-এর জন্য "PDF / প্রিন্ট" টিপে "Save as PDF" বাছুন (A4, আড়াআড়ি)।</p>
            @if ($c->post && $c->post->isLive())
                <p class="mt-6 text-center"><a href="{{ $c->post->url() }}" class="font-bold text-sky-night underline">লেখাটি পড়ুন</a></p>
            @endif
        @else
            <div class="mx-auto mt-6 max-w-[680px] rounded-3xl border-2 border-red bg-white p-8 text-center text-red">এই শংসাপত্রটি বাতিল করা হয়েছে।</div>
        @endif
    </section>
    <script src="{{ asset('js/hp-card.js') }}?v=1" defer></script>
    <script src="{{ asset('js/hp-cert.js') }}?v=1" defer></script>
</x-layouts.app>
