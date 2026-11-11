{{-- The issues' e-books (owner, 2026-11-12): built by themselves; the owner checks the magazine and approves. --}}
<x-filament-panels::page>
    <style>
        .hp-eb { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fill, minmax(17rem, 1fr)); }
        .hp-eb-card { display: flex; gap: .9rem; border: 1px solid rgba(127,127,127,.25); border-radius: .9rem; padding: .9rem; }
        .hp-eb-cover { flex: none; width: 70px; aspect-ratio: 1/1.41; border-radius: .3rem; overflow: hidden; background: #13212C; position: relative; }
        .hp-eb-cover img { width: 100%; height: 100%; object-fit: cover; }
        .hp-eb-cover span { position: absolute; left: .35rem; top: .3rem; color: #fff; font-family: 'Galada', cursive; font-size: .95rem; }
        .hp-eb-badge { display: inline-block; font-size: .78rem; font-weight: 700; padding: .1rem .55rem; border-radius: 999px; margin: .25rem 0; }
        .hp-eb-wait { background: #CB1B32; color: #fff; }
        .hp-eb-ok { background: #13212C; color: #FFFBF4; }
        .hp-eb-none { border: 1px solid rgba(127,127,127,.4); }
        .hp-muted { font-size: .85rem; opacity: .75; }
    </style>
    <x-filament::section>
        <p>{{ __('প্রতি রাতে, কোনো সংখ্যার লেখা বদলালে তার ই-বুক নিজে থেকেই নতুন করে তৈরি হয়: প্রচ্ছদ, সূচি, সম্পাদকীয়, সব লেখা, লেখকদের পরিচয় আর শেষ পাতা। আপনি “পত্রিকা দেখুন” খুলে মিলিয়ে নিন, তারপর “অনুমোদন দিন”। তখনই পাঠকেরা সংখ্যার পাতা থেকে নামাতে পারবেন।') }}</p>
    </x-filament::section>
    <div class="hp-eb">
        @forelse ($issues as $i)
            @php($waiting = \App\Support\IssueEbook::waiting($i))
            @php($approved = \App\Support\IssueEbook::approved($i))
            <div class="hp-eb-card">
                <div class="hp-eb-cover">@if ($i->cover_path)<img src="{{ $i->cover_path }}" alt="">@endif<span>হাতপাখা</span></div>
                <div style="min-width:0;flex:1">
                    <b>{{ $i->title }}</b><br>
                    @if ($waiting)
                        <span class="hp-eb-badge hp-eb-wait">{{ $approved ? __('নতুন সংস্করণ অনুমোদনের অপেক্ষায়') : __('তৈরি; অনুমোদনের অপেক্ষায়') }}</span>
                    @elseif ($approved)
                        <span class="hp-eb-badge hp-eb-ok">✓ {{ __('পাঠকদের জন্য খোলা') }}</span>
                    @else
                        <span class="hp-eb-badge hp-eb-none">{{ __('এখনও তৈরি হয়নি') }}</span>
                    @endif
                    <div class="hp-muted">
                        @if ($i->ebook_built_at){{ __('তৈরি') }}: {{ \App\Support\Bn::date($i->ebook_built_at) }}@endif
                        @if ($i->ebook_approved_at) · {{ __('অনুমোদন') }}: {{ \App\Support\Bn::date($i->ebook_approved_at) }}@endif
                    </div>
                    <div style="display:flex;flex-wrap:wrap;gap:.4rem;margin-top:.5rem">
                        <x-filament::button size="xs" tag="a" :href="\App\Support\Url::to('/sonkha/'.$i->slug.'/boi')" target="_blank" icon="heroicon-o-book-open">{{ __('পত্রিকা দেখুন') }}</x-filament::button>
                        @if ($i->ebook_path)
                            <x-filament::button size="xs" color="gray" icon="heroicon-o-arrow-down-tray" wire:click="download({{ $i->id }})">EPUB</x-filament::button>
                        @endif
                        @if ($waiting && auth()->user()->hasRole(\App\Enums\Role::Admin))
                            <x-filament::button size="xs" color="success" icon="heroicon-o-check" wire:click="approve({{ $i->id }})" wire:confirm="{{ __('এই ই-বুক পাঠকদের জন্য খুলে দেবেন?') }}">{{ __('অনুমোদন দিন') }}</x-filament::button>
                        @endif
                        <x-filament::button size="xs" color="gray" icon="heroicon-o-arrow-path" wire:click="rebuild({{ $i->id }})">{{ $i->ebook_path ? __('আবার তৈরি করুন') : __('এখনই তৈরি করুন') }}</x-filament::button>
                        @if ($approved && auth()->user()->hasRole(\App\Enums\Role::Admin))
                            <x-filament::button size="xs" color="danger" outlined wire:click="withdraw({{ $i->id }})" wire:confirm="{{ __('পাঠকদের কাছ থেকে ই-বুক সরিয়ে নেবেন?') }}">{{ __('সরিয়ে নিন') }}</x-filament::button>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <p class="hp-muted">{{ __('এখনও কোনো প্রকাশিত সংখ্যা নেই।') }}</p>
        @endforelse
    </div>
</x-filament-panels::page>
