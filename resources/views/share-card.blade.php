{{-- D252: a piece's picture card, for the writer (from the "your piece is live" e-mail) or anyone: no sign-in, nothing
     private on it (title, writer, section, link). The card is drawn on the phone; nothing is uploaded. --}}
<x-layouts.app :seo="$seo">
    <section class="mx-auto max-w-[560px] px-5 pb-12 pt-8">
        <p class="font-hand text-2xl text-red">লেখার ছবি</p>
        <h1 class="font-display text-3xl font-extrabold leading-tight">“{{ $post->title }}”</h1>
        <p class="mt-2 text-lg">ছবিটি ফোনে নামিয়ে ফেসবুকে বা হোয়াটসঅ্যাপ স্ট্যাটাসে দিন, সঙ্গে লেখার লিংকটিও দিন।</p>
        <button type="button" class="mt-5 flex h-14 w-full items-center justify-center rounded-full bg-red px-6 text-lg font-bold text-white shadow-[3px_3px_0_#13212C]" data-share-card data-open-now
            data-title="{{ $post->title }}" data-by="{{ $by }}" data-section="{{ $post->section?->name_bn }}" data-url="{{ $post->url() }}">ছবিটি দেখুন</button>
        <a href="https://wa.me/?text={{ rawurlencode('হাতপাখায় “'.$post->title.'” পড়ুন: '.$post->url()) }}" target="_blank" rel="noopener" class="mt-3 flex h-12 w-full items-center justify-center rounded-full border-2 border-navy bg-white px-6 font-bold">হোয়াটসঅ্যাপে লিংক পাঠান</a>
        <a href="{{ $post->url() }}" class="mt-3 flex min-h-11 items-center justify-center font-bold text-sky-night underline">লেখাটি পড়ুন →</a>
        @include('partials.share-card-dialog')
    </section>
    <script src="{{ asset('js/hp-share-card.js') }}?v=3" defer></script>
</x-layouts.app>
