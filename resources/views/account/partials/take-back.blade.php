{{-- D205 (owner + UX): before publication the writer changes a sent piece themselves: take it back, change, send again. --}}
<div class="{{ $class ?? '' }}">
    <p class="font-bold">{{ __('কিছু বদলাতে, যোগ করতে বা লেখাটি তুলে নিতে চান?') }}</p>
    <ol class="mt-1 list-decimal ps-5 text-sm text-ink2">
        <li>{{ __('"ফিরিয়ে এনে বদলান" টিপুন। লেখাটি সম্পাদকের কাছ থেকে আপনার কাছে খসড়া হয়ে ফিরবে।') }}</li>
        <li>{{ __('যা বদলানোর বদলান, তারপর "জমা দিন" টিপুন। না পাঠাতে চাইলে খসড়াটি রেখে দিন বা মুছে ফেলুন।') }}</li>
    </ol>
    @if ($p->status === 'scheduled')<p class="mt-2 text-sm font-semibold text-red-dark">{{ __('লেখাটি প্রকাশের জন্য বাছাই হয়ে আছে। ফিরিয়ে আনলে প্রকাশের তারিখ বাতিল হবে, সম্পাদক আবার পড়বেন।') }}</p>@endif
    <form method="POST" action="{{ \App\Support\Url::to('/submit/'.$p->id.'/withdraw') }}" class="mt-3">@csrf
        <button class="flex h-12 items-center rounded-full bg-red px-6 font-bold text-white">{{ __('ফিরিয়ে এনে বদলান') }}</button>
    </form>
    <p class="mt-2 text-xs text-ink2">{{ __('একই লেখা নতুন করে আবার পাঠাবেন না; তাতে দুটো লেখা জমা পড়ে।') }}</p>
</div>
