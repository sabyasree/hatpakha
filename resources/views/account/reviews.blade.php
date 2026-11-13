{{-- D168: the pieces the desk asked this reviewer (রিভিউয়ার) to read: to read first, then the ones already answered. --}}
@php
    use App\Support\Bn;
    use App\Support\Url;
    $card = 'flex items-center gap-3 rounded-2xl border-2 border-navy bg-white p-4 hover:bg-peach-light';
@endphp
<x-layouts.app :seo="$seo">
    <x-account-shell :tab="$tab" :user="$user" :author="$author" :counts="$counts" :two-factor-on="$twoFactorOn" :cert-count="$certCount">
        <h2 class="font-display text-2xl font-extrabold">{{ __('রিভিউ করার লেখা') }}</h2>
        <p class="mt-1 text-ink2">{{ __('সম্পাদকেরা এই লেখাগুলো আপনাকে পড়তে দিয়েছেন। পড়ে আপনার সত্যি মতামত দিন। আপনার মতামত শুধু সম্পাদকেরা দেখবেন, লেখকের কাছে যাবে না।') }}</p>

        <h3 class="mt-6 text-lg font-extrabold">{{ __('পড়া বাকি') }} @if ($open->isNotEmpty())<span class="text-red">({{ Bn::num((string) $open->count()) }})</span>@endif</h3>
        @forelse ($open as $r)
            <a href="{{ Url::to('/account/review/'.$r->id) }}" class="mt-3 {{ $card }}">
                <span class="min-w-0 flex-1">
                    <span class="block font-serif text-xl font-bold leading-snug">{{ $r->post->title }}</span>
                    <span class="block text-sm text-ink2">{{ $r->post->section?->name_bn }}@if ($r->due_on) · <strong class="text-red">{{ __(':date-এর মধ্যে', ['date' => Bn::date($r->due_on)]) }}</strong>@endif @if (! $r->opened_at) · <strong>{{ __('নতুন') }}</strong>@endif</span>
                    @if ($r->request_note)<span class="mt-1 block text-sm">{{ __('সম্পাদকের অনুরোধ:') }} {{ $r->request_note }}</span>@endif
                </span>
                <span class="flex h-11 flex-none items-center rounded-full bg-red px-4 text-sm font-bold text-white">{{ __('পড়ুন') }}</span>
            </a>
        @empty
            <p class="mt-3 rounded-2xl border-2 border-dashed border-navy p-5 text-center">{{ __('এখন পড়ার মতো কিছু নেই। নতুন লেখা এলে ই-মেলে জানানো হবে।') }}</p>
        @endforelse

        @if ($done->isNotEmpty())
            <h3 class="mt-8 text-lg font-extrabold">{{ __('মতামত দিয়েছেন (:count)', ['count' => Bn::num((string) $done->count())]) }}</h3>
            @foreach ($done as $r)
                <a href="{{ Url::to('/account/review/'.$r->id) }}" class="mt-3 {{ $card }} border-rule">
                    <span class="min-w-0 flex-1">
                        <span class="block font-serif text-lg font-bold leading-snug">{{ $r->post->title }}</span>
                        <span class="block text-sm text-ink2">{{ isset(\App\Models\PieceReview::VERDICTS[$r->verdict]) ? __(\App\Models\PieceReview::VERDICTS[$r->verdict]) : '' }} · {{ Bn::date($r->submitted_at) }}</span>
                    </span>
                    <span class="flex-none text-sm font-bold text-sky-night underline">{{ __('এআই-এর সঙ্গে মিলিয়ে দেখুন') }}</span>
                </a>
            @endforeach
        @endif
    </x-account-shell>
</x-layouts.app>
