{{-- D331 (owner's list: "a monthly 'your month' card to share"): a player's last month in a few numbers, to send. --}}
@php
    use App\Support\Bn;
    $ym = now('Asia/Kolkata')->subMonthNoOverflow()->format('Y-m');
    $m = \App\Support\GameScores::month($user, $ym);
@endphp
@if ($m)
    @php
        $text = $m['label'].'-এ হাতপাখার খেলায় আমি: '.Bn::num((string) $m['days']).' দিন খেলেছি, সেরা দিনে '.Bn::num((string) $m['best']).' পয়েন্ট'
            .($m['favourite'] ? ', প্রিয় খেলা '.$m['favourite'] : '').($m['rank'] ? ', মাসে '.Bn::num((string) $m['rank']).' নম্বরে' : '').'।'
            ."\nআপনিও খেলুন: ".\App\Support\Url::to('/khela/aaj');
    @endphp
    <div class="mt-5 rounded-3xl border-2 border-navy bg-peach-light p-4 shadow-[4px_4px_0_#13212C]">
        <p class="font-hand text-2xl text-red">আপনার {{ $m['label'] }}</p>
        <dl class="mt-2 grid grid-cols-3 gap-2 text-center">
            <div class="rounded-2xl bg-white px-1 py-2"><dt class="text-sm font-bold text-ink2">খেলেছেন</dt><dd class="font-display text-2xl font-extrabold">{{ Bn::num((string) $m['days']) }} দিন</dd></div>
            <div class="rounded-2xl bg-white px-1 py-2"><dt class="text-sm font-bold text-ink2">সেরা দিন</dt><dd class="font-display text-2xl font-extrabold">{{ Bn::num((string) $m['best']) }}</dd></div>
            <div class="rounded-2xl bg-white px-1 py-2"><dt class="text-sm font-bold text-ink2">মাসে</dt><dd class="font-display text-2xl font-extrabold">{{ $m['rank'] ? Bn::num((string) $m['rank']).' নম্বর' : '—' }}</dd></div>
        </dl>
        @if ($m['favourite'])<p class="mt-2 text-lg">প্রিয় খেলা: <b>{{ $m['favourite'] }}</b>@if ($m['weeks']) · সপ্তাহের সেরা {{ Bn::num((string) $m['weeks']) }} বার@endif</p>@endif
        <a href="https://wa.me/?text={{ rawurlencode($text) }}" target="_blank" rel="noopener" class="mt-3 flex h-12 items-center justify-center rounded-full bg-navy px-5 font-bold text-white">WhatsApp-এ আমার মাস পাঠান</a>
    </div>
@endif
