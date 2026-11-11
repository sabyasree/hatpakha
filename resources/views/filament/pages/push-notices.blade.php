{{-- D132: phone notifications at a glance (numbers only), and the owner's announcements. --}}
<x-filament-panels::page>
    <style>
        .hp-kpis { display: grid; gap: .75rem; grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr)); }
        .hp-kpi { border: 1px solid rgba(127,127,127,.22); border-radius: .9rem; padding: .9rem 1rem; }
        .hp-kpi b { display: block; font-size: 1.6rem; line-height: 1.2; }
        .hp-muted { font-size: .875rem; opacity: .7; }
        .hp-table { width: 100%; border-collapse: collapse; font-size: .9rem; }
        .hp-table td, .hp-table th { text-align: left; padding: .45rem .3rem; border-bottom: 1px solid rgba(127,127,127,.15); }
    </style>
    @unless ($configured)
        <x-filament::section><p>{{ __('ফোনে খবর এখনও চালু নেই: ENV_FILE-এ VAPID_PUBLIC_KEY আর VAPID_PRIVATE_KEY দিন (docs/APP.md)।') }}</p></x-filament::section>
    @endunless
    <div class="hp-kpis">
        <div class="hp-kpi"><span class="hp-muted">{{ __('ফোনে খবর চালু') }}</span><b>{{ $n($phones) }}</b><span class="hp-muted">{{ __('অ্যাকাউন্টসহ') }} {{ $n($accounts) }}</span></div>
        <div class="hp-kpi"><span class="hp-muted">{{ __('রোজকার খবর') }}</span><b>{{ $n($daily) }}</b></div>
        <div class="hp-kpi"><span class="hp-muted">{{ __('বিভাগ বেছেছেন') }}</span><b>{{ $n($sections) }}</b></div>
        <div class="hp-kpi"><span class="hp-muted">{{ __('ধারাবাহিক') }} · {{ __('লেখক') }}</span><b>{{ $n($serials) }} · {{ $n($writers) }}</b></div>
    </div>

    <x-filament::section :heading="__('গত ৩০ দিনে')" :description="__('কতগুলো খবর গেছে আর কতগুলো ছুঁয়ে খোলা হয়েছে। কে খুলেছেন তা রাখা হয় না, শুধু সংখ্যা।')">
        <table class="hp-table">
            <tr><th>{{ __('কী খবর') }}</th><th>{{ __('গেছে') }}</th><th>{{ __('খোলা হয়েছে') }}</th><th>%</th></tr>
            @php($labels = ['post' => 'লেখকের নতুন লেখা', 'serial' => 'ধারাবাহিকের নতুন পর্ব', 'section' => 'বিভাগের নতুন লেখা', 'daily' => 'রোজকার খবর', 'announce' => 'ঘোষণা', 'team' => 'দলের কাজ (আপনার)', 'test' => 'পরীক্ষা', 'other' => 'অন্য'])
            @forelse ($stats as $kind => $row)
                <tr><td>{{ __($labels[$kind] ?? $kind) }}</td><td>{{ $n($row->sent) }}</td><td>{{ $n($row->opened) }}</td><td>{{ $row->sent ? $n(round(100 * $row->opened / $row->sent)) : '—' }}</td></tr>
            @empty
                <tr><td colspan="4" class="hp-muted">{{ __('এখনও কোনো খবর যায়নি।') }}</td></tr>
            @endforelse
        </table>
    </x-filament::section>

    <div style="display:grid;gap:1rem;grid-template-columns:repeat(auto-fit,minmax(18rem,1fr))">
        <x-filament::section :heading="__('সবচেয়ে বেশি যা বেছেছেন')">
            @forelse ($top as $t)
                <div style="display:flex;justify-content:space-between;padding:.3rem 0"><span>{{ $t['name'] }} <span class="hp-muted">({{ __(['section' => 'বিভাগ', 'serial' => 'ধারাবাহিক', 'author' => 'লেখক'][$t['kind']] ?? $t['kind']) }})</span></span><b>{{ $n($t['n']) }}</b></div>
            @empty
                <p class="hp-muted">{{ __('এখনও কেউ আলাদা করে কিছু বাছেননি।') }}</p>
            @endforelse
        </x-filament::section>
        <x-filament::section :heading="__('কোন যন্ত্রে')">
            @foreach ($devices as $d => $c)
                <div style="display:flex;justify-content:space-between;padding:.3rem 0"><span>{{ $d }}</span><b>{{ $n($c) }}</b></div>
            @endforeach
        </x-filament::section>
    </div>

    <x-filament::section :heading="__('ঘোষণা')" :description="__('উপরের “সবার ফোনে একটি ঘোষণা” থেকে। মিনিটে ২০০টি করে যায়।')">
        @forelse ($announcements as $a)
            <div style="display:flex;justify-content:space-between;gap:.5rem;flex-wrap:wrap;padding:.5rem 0;border-bottom:1px solid rgba(127,127,127,.15)">
                <span><b>{{ $a->title }}</b> <span class="hp-muted">· {{ $a->body }}</span></span>
                <span class="hp-muted">{{ __(['queued' => 'শুরু হবে', 'sending' => 'যাচ্ছে', 'sent' => 'গেছে', 'cancelled' => 'থামানো'][$a->status] ?? $a->status) }} · {{ $n($a->sent) }}/{{ $n($a->recipients) }}
                    @if (in_array($a->status, ['queued', 'sending'], true))
                        <x-filament::button size="xs" color="danger" outlined wire:click="cancelAnnouncement({{ $a->id }})" wire:confirm="{{ __('ঘোষণা পাঠানো থামাবেন?') }}">{{ __('থামান') }}</x-filament::button>
                    @endif
                </span>
            </div>
        @empty
            <p class="hp-muted">{{ __('এখনও কোনো ঘোষণা পাঠানো হয়নি।') }}</p>
        @endforelse
    </x-filament::section>
</x-filament-panels::page>
