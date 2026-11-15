{{-- D208: the jobs handed to the AI team. Switches, what is about to go (with a stop), what was held and why, what was done. Logo palette only. --}}
@php
    use App\Support\Bn;
    use App\Filament\Resources\Posts\PostResource;
    $when = fn ($t) => Bn::num($t->copy()->timezone('Asia/Kolkata')->format('d/m, H:i'));
    $box = 'border:1px solid rgba(127,127,127,.25);border-radius:1rem;padding:1rem 1.1rem';
    $did = ['handover.fixes' => __('ছোট সংশোধন বসানো হলো'), 'handover.cover' => __('প্রচ্ছদের ছবি বসানো হলো'), 'handover.comment' => __('মন্তব্য প্রকাশ হলো'),
        'handover.letter' => __('পাঠকদের চিঠি পাঠানো শুরু হলো'), 'handover.monthly' => __('মাসের ছোট কাজ হলো'), 'handover.published' => __('লেখা মনোনীত হলো'),
        'handover.publish_stopped' => __('আপনি একটি লেখা থামালেন'), 'handover.letter_stopped' => __('আপনি চিঠি থামালেন'), 'handover.switch' => __('সুইচ বদলানো হলো')];
@endphp
<x-filament-panels::page>
    <p style="max-width:52rem">{{ __('এই কাজগুলো এআই দল নিজে থেকে করে। প্রতিটি আলাদা করে বন্ধ করা যায়। লেখা বাতিল করা, লেখককে বদল করতে বলা, সম্পাদকীয়, ধারাবাহিক আর নকলের সতর্কতা আগের মতোই আপনার হাতে।') }}</p>

    <div style="display:grid;gap:.75rem;grid-template-columns:repeat(auto-fill,minmax(19rem,1fr))">
        @foreach ($jobs as $key => $j)
            <div style="{{ $box }}">
                <div style="display:flex;justify-content:space-between;gap:.5rem;align-items:center">
                    <strong style="font-size:1.05rem">{{ __($j['name']) }}</strong>
                    <x-filament::button size="xs" :color="$j['on'] ? 'gray' : 'primary'" wire:click="toggle('{{ $key }}')"
                        wire:confirm="{{ $j['on'] ? __('এই কাজটি নিজে থেকে হওয়া বন্ধ করবেন?') : __('এই কাজটি নিজে থেকে হতে দেবেন?') }}">{{ $j['on'] ? __('চালু আছে · বন্ধ করুন') : __('বন্ধ আছে · চালু করুন') }}</x-filament::button>
                </div>
                <p style="margin:.4rem 0 0;opacity:.8;font-size:.9rem">{{ __($j['what']) }}</p>
            </div>
        @endforeach
    </div>

    <x-filament::section :heading="__('নিজে থেকে মনোনীত হতে চলেছে')" :description="__('সময় হলে পরের ফাঁকা তারিখে প্রকাশের জন্য বসবে, লেখক ই-মেল পাবেন। থামালে লেখাটি আপনার জন্য অপেক্ষা করবে।')">
        @forelse ($going as $p)
            <div style="display:flex;flex-wrap:wrap;gap:.5rem;align-items:center;justify-content:space-between;padding:.45rem 0;border-top:1px solid rgba(127,127,127,.2)">
                <span><a href="{{ PostResource::getUrl('edit', ['record' => $p]) }}" style="font-weight:700;text-decoration:underline">{{ $p->title }}</a>
                    <span style="opacity:.75"> · {{ $p->authors->first()?->publicName() }} · {{ __('মান') }} {{ Bn::num((string) ($p->latestAiReview?->report['quality_score'] ?? '')) }}</span></span>
                <span style="white-space:nowrap">{{ $when($p->auto_publish_at) }}
                    <x-filament::button size="xs" color="danger" outlined wire:click="stopPiece({{ $p->id }})">{{ __('থামান') }}</x-filament::button></span>
            </div>
        @empty
            <p style="opacity:.7">{{ __('এখন কিছু নেই।') }}</p>
        @endforelse
    </x-filament::section>

    <x-filament::section :heading="__('আপনার জন্য রাখা হয়েছে')" :description="__('এই লেখাগুলো নিজে থেকে প্রকাশ হবে না। কারণ পাশে লেখা আছে; খুলে দেখে আপনি ঠিক করবেন।')">
        @forelse ($held as $r)
            <div style="padding:.45rem 0;border-top:1px solid rgba(127,127,127,.2)">
                <a href="{{ PostResource::getUrl('edit', ['record' => $r['post']]) }}" style="font-weight:700;text-decoration:underline">{{ $r['post']->title }}</a>
                <span style="opacity:.75"> · {{ $r['post']->authors->first()?->publicName() }}</span>
                <div style="color:#CB1B32;font-weight:600">{{ $r['why'] }}</div>
            </div>
        @empty
            <p style="opacity:.7">{{ __('এখন কিছু নেই।') }}</p>
        @endforelse
    </x-filament::section>

    @if ($letters->isNotEmpty())
        <x-filament::section :heading="__('চিঠি নিজে থেকে যাবে')">
            @foreach ($letters as $n)
                <div style="display:flex;flex-wrap:wrap;gap:.5rem;align-items:center;justify-content:space-between;padding:.45rem 0">
                    <a href="{{ \App\Filament\Resources\Newsletters\NewsletterResource::getUrl('edit', ['record' => $n]) }}" style="font-weight:700;text-decoration:underline">{{ $n->subject }}</a>
                    <span style="white-space:nowrap">{{ $when($n->auto_send_at) }}
                        <x-filament::button size="xs" color="danger" outlined wire:click="stopLetter({{ $n->id }})">{{ __('থামান') }}</x-filament::button></span>
                </div>
            @endforeach
        </x-filament::section>
    @endif

    <x-filament::section :heading="__('যা নিজে থেকে হয়েছে')" collapsible>
        @forelse ($log as $l)
            <div style="padding:.3rem 0;border-top:1px solid rgba(127,127,127,.15);font-size:.92rem">
                <span style="opacity:.7">{{ $when($l->created_at) }}</span> · {{ $did[$l->action] ?? $l->action }}
                @if ($l->subject instanceof \App\Models\Post) · <a href="{{ PostResource::getUrl('edit', ['record' => $l->subject]) }}" style="text-decoration:underline">{{ $l->subject->title }}</a>@endif
            </div>
        @empty
            <p style="opacity:.7">{{ __('এখনও কিছু হয়নি।') }}</p>
        @endforelse
    </x-filament::section>
</x-filament-panels::page>
