<x-layouts.app :seo="$seo">
    <section class="mx-auto max-w-[680px] px-5 pb-16 pt-14 text-center">
        <h1 class="font-display text-4xl font-extrabold">লেখা পাঠান</h1>
        <p class="mt-4 font-serif text-lg text-ink2">লেখা পাঠানোর নতুন ফর্মটি নতুন হাতপাখার সঙ্গেই চালু হবে। লেখা জমা নেওয়া হয় শুধু এই ফর্মে, ইমেলে নয়।</p>
        <a href="{{ \App\Support\Url::to('/submissions') }}" class="mt-8 inline-flex h-12 items-center rounded-full bg-navy px-6 font-bold text-paper">লেখা পাঠানোর নিয়ম পড়ুন</a>
    </section>
</x-layouts.app>
