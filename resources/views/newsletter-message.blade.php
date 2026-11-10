<x-layouts.app :seo="$seo">
    <section class="mx-auto max-w-[640px] px-5 py-16 text-center">
        <h1 class="font-display text-4xl font-extrabold">{{ $title }}</h1>
        <p class="mt-4 text-lg text-ink2">{{ $text }}</p>
        <a href="{{ url('/') }}/" class="mt-8 inline-flex h-12 items-center rounded-full bg-red px-6 font-bold text-white">হাতপাখা খুলুন</a>
    </section>
</x-layouts.app>
