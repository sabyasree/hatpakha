{{-- Certificate check + the certificate itself (D12, D71): anyone with the code sees it; the writer downloads it here. --}}
@php
    $c = $certificate;
    $file = 'hatpakha-shongshapotro-'.$c->theme().'-'.$c->code;
    $share = 'হাতপাখার '.\App\Models\Certificate::THEMES[$c->theme()][0].': '.$c->verifyUrl();
    // 2026-11-25 (owner: writers don't share certificates; the page is never tagged). Facebook drops any text a site
    // passes, so the words are copied for the writer to paste, and tagging is a tip: only the person can tag the page.
    $mine = $c->author_id && auth()->user()?->author?->id === $c->author_id;
    $readUrl = $c->post && $c->post->isLive() ? $c->post->url() : $c->verifyUrl();
    $caption = $c->post ? 'হাতপাখায় প্রকাশিত আমার লেখা “'.$c->post->title.'”, আর পেলাম তার শংসাপত্র। পড়ুন: '.$readUrl.' #হাতপাখা #বাংলাসাহিত্য'
        : 'হাতপাখার '.\App\Models\Certificate::THEMES[$c->theme()][0].': '.$c->verifyUrl().' #হাতপাখা';
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
            <div id="share" data-cert-share @class(['mx-auto mt-6 max-w-[760px] rounded-3xl border-2 border-navy p-5 print:hidden', 'bg-sky-tint' => $mine, 'bg-paper' => ! $mine])>
                <h2 class="font-display text-2xl font-extrabold">{{ $mine ? 'শংসাপত্রটি ফেসবুকে দিন' : 'শেয়ার করুন' }}</h2>
                @if ($mine)
                    <ol class="mt-2 list-decimal space-y-1 pl-5 font-serif text-base">
                        <li>"ছবিসহ ফেসবুকে দিন" টিপুন: শংসাপত্রের ছবি তৈরি হবে, আর পোস্টের লেখা কপি হয়ে যাবে।</li>
                        <li>ফেসবুকে লেখার জায়গায় আঙুল রেখে <b>পেস্ট</b> করুন।</li>
                        <li><b>@হাতপাখা</b> (বা <b>@Hatpakha</b>) লিখে তালিকা থেকে আমাদের পেজটি বেছে নিন, তাহলে আমরাও আপনার পোস্ট দেখতে পাব।</li>
                    </ol>
                @endif
                <div class="mt-4 flex flex-wrap gap-3">
                    <button type="button" data-cert-fb="{{ $file }}.png" data-caption="{{ $caption }}" class="flex h-12 items-center rounded-full bg-red px-6 font-bold text-white">ছবিসহ ফেসবুকে দিন</button>
                    <button type="button" data-copy="{{ $caption }}" data-done="✓ কপি হয়েছে, এবার ফেসবুকে পেস্ট করুন" class="flex h-12 items-center rounded-full border-2 border-navy bg-white px-6 font-bold">পোস্টের লেখা কপি করুন</button>
                    <a href="https://wa.me/?text={{ rawurlencode($caption) }}" rel="noopener" target="_blank" class="flex h-12 items-center rounded-full border-2 border-navy bg-white px-6 font-bold">হোয়াটসঅ্যাপে পাঠান</a>
                    <button type="button" data-copy="{{ $c->verifyUrl() }}" data-done="✓ লিংক কপি হয়েছে" class="flex h-12 items-center rounded-full border-2 border-navy bg-white px-6 font-bold">লিংক কপি</button>
                </div>
                <p data-cert-msg role="status" class="mt-3 font-bold text-sky-night"></p>
                <div class="mt-4 flex flex-wrap gap-3 border-t border-rule pt-4">
                    <button type="button" data-cert-png="{{ $file }}.png" class="flex h-11 items-center rounded-full border-2 border-navy px-5 font-bold">ছবি নামান (PNG)</button>
                    <button type="button" data-cert-print class="flex h-11 items-center rounded-full border-2 border-navy px-5 font-bold">PDF / প্রিন্ট</button>
                </div>
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
    <script src="{{ asset('js/hp-cert.js') }}?v=2" defer></script>
</x-layouts.app>
