{{-- D123: every e-mail the site sends. --}}
@php($all = \App\Support\MailTemplates::all())
<x-filament-panels::page>
    <div style="font-size:.9rem;opacity:.8;line-height:1.7">{{ __('হাতপাখা যে ই-মেলগুলো নিজে থেকে পাঠায়, সব এখানে। “দেখুন” টিপলে পাঠকের ইনবক্সে যেমন দেখাবে তেমনই দেখা যাবে; “বদলান” দিয়ে নিজের মতো লিখুন। বদল রাখার পর থেকে সেই লেখাতেই ই-মেল যাবে; “আগের লেখায় ফেরান” দিয়ে আসলটিতে ফেরা যায়।') }}</div>
    <div style="display:grid;gap:.8rem">
        @foreach ($all as $key => [$label, $when, $to, $vars, $subject])
            @php($t = \App\Support\MailTemplates::text($key))
            <x-filament::section wire:key="tpl-{{ $key }}" compact>
                <div style="display:flex;justify-content:space-between;gap:.8rem;flex-wrap:wrap;align-items:flex-start">
                    <div style="min-width:0;flex:1">
                        <div style="font-weight:700">{{ __($label) }}
                            @if (\App\Support\MailTemplates::edited($key))<x-filament::badge color="info" style="display:inline-flex;margin-left:.4rem">{{ __('আপনার লেখা') }}</x-filament::badge>@endif
                        </div>
                        <div style="font-size:.85rem;opacity:.75;margin-top:.2rem">{{ __('কখন') }}: {{ __($when) }} · {{ __('কাকে') }}: {{ __($to) }}</div>
                        <div style="font-size:.9rem;margin-top:.3rem">{{ __('বিষয়') }}: <span style="opacity:.85">{{ $t['subject'] }}</span></div>
                    </div>
                    <div style="display:flex;gap:.4rem;flex-wrap:wrap">
                        {{ ($this->previewAction)(['key' => $key]) }}
                        {{ ($this->editAction)(['key' => $key]) }}
                        {{ ($this->resetAction)(['key' => $key]) }}
                    </div>
                </div>
            </x-filament::section>
        @endforeach
    </div>
    <x-filament::section icon="heroicon-o-information-circle" collapsible persist-collapsed collapse-id="hp-mail-templates-elsewhere">
        <x-slot name="heading">{{ __('যে ই-মেলগুলো অন্য জায়গায় লেখা হয়') }}</x-slot>
        <div style="display:grid;gap:.6rem">
            @foreach (\App\Support\MailTemplates::elsewhere() as $label => [$where, $url])
                <div style="display:flex;justify-content:space-between;gap:.6rem;flex-wrap:wrap;align-items:center">
                    <div><strong>{{ __($label) }}</strong><div style="font-size:.85rem;opacity:.75">{{ __($where) }}</div></div>
                    <x-filament::button size="sm" color="gray" tag="a" :href="$url" icon="heroicon-o-arrow-top-right-on-square">{{ __('খুলুন') }}</x-filament::button>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-panels::page>
