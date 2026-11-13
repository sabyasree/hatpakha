{{-- D168: the pieces the desk asked this reviewer (যাচাইকারী) to read: to read first, then the ones already answered. --}}
@php
    use App\Support\Bn;
    use App\Support\Url;
    $card = 'flex items-center gap-3 rounded-2xl border-2 border-navy bg-white p-4 hover:bg-peach-light';
@endphp
<x-layouts.app :seo="$seo">
    <x-account-shell :tab="$tab" :user="$user" :author="$author" :counts="$counts" :two-factor-on="$twoFactorOn" :cert-count="$certCount">
        <h2 class="font-display text-2xl font-extrabold">যাচাইয়ের লেখা</h2>
        <p class="mt-1 text-ink2">সম্পাদকমণ্ডলী এই লেখাগুলো আপনাকে পড়তে দিয়েছেন। পড়ে আপনার সত্যি মত দিন। আপনার মত শুধু সম্পাদকমণ্ডলী দেখবেন, লেখকের কাছে যাবে না।</p>

        <h3 class="mt-6 text-lg font-extrabold">পড়া বাকি @if ($open->isNotEmpty())<span class="text-red">({{ Bn::num((string) $open->count()) }})</span>@endif</h3>
        @forelse ($open as $r)
            <a href="{{ Url::to('/account/review/'.$r->id) }}" class="mt-3 {{ $card }}">
                <span class="min-w-0 flex-1">
                    <span class="block font-serif text-xl font-bold leading-snug">{{ $r->post->title }}</span>
                    <span class="block text-sm text-ink2">{{ $r->post->section?->name_bn }}@if ($r->due_on) · <strong class="text-red">{{ Bn::date($r->due_on) }}-এর মধ্যে</strong>@endif @if (! $r->opened_at) · <strong>নতুন</strong>@endif</span>
                    @if ($r->request_note)<span class="mt-1 block text-sm">ডেস্কের অনুরোধ: {{ $r->request_note }}</span>@endif
                </span>
                <span class="flex h-11 flex-none items-center rounded-full bg-red px-4 text-sm font-bold text-white">পড়ুন</span>
            </a>
        @empty
            <p class="mt-3 rounded-2xl border-2 border-dashed border-navy p-5 text-center">এখন পড়ার মতো কিছু নেই। নতুন লেখা এলে ই-মেলে জানানো হবে।</p>
        @endforelse

        @if ($done->isNotEmpty())
            <h3 class="mt-8 text-lg font-extrabold">মত দিয়েছেন ({{ Bn::num((string) $done->count()) }})</h3>
            @foreach ($done as $r)
                <a href="{{ Url::to('/account/review/'.$r->id) }}" class="mt-3 {{ $card }} border-rule">
                    <span class="min-w-0 flex-1">
                        <span class="block font-serif text-lg font-bold leading-snug">{{ $r->post->title }}</span>
                        <span class="block text-sm text-ink2">{{ \App\Models\PieceReview::VERDICTS[$r->verdict] ?? '' }} · {{ Bn::date($r->submitted_at) }}</span>
                    </span>
                    <span class="flex-none text-sm font-bold text-sky-night underline">এআই-এর সঙ্গে মিলিয়ে দেখুন</span>
                </a>
            @endforeach
        @endif
    </x-account-shell>
</x-layouts.app>
