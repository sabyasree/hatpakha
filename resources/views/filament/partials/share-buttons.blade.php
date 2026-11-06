{{-- D87: copy, or open the network's own share screen with the post ready. Never posts by itself.
     $ch: facebook | whatsapp | instagram; $link: the page the post is about; the text comes from x-ref="t". --}}
<div data-link="{{ $link }}" style="display:flex;gap:.35rem;flex-wrap:wrap;justify-content:flex-end">
    <x-filament::button size="xs" color="gray" icon="heroicon-o-clipboard"
        x-on:click="navigator.clipboard.writeText($refs.t.innerText); copied = true; setTimeout(() => copied = false, 1500)">
        <span x-text="copied ? '{{ __('কপি হয়েছে') }}' : '{{ __('কপি') }}'"></span>
    </x-filament::button>
    <x-filament::button size="xs" icon="heroicon-o-share"
        x-on:click="msg = await window.hpShare.share('{{ $ch }}', $refs.t.innerText, $el.closest('[data-link]').dataset.link, $root.closest('.hp-mk-item')?.querySelector('.hp-card[data-name]'))">
        {{ ['facebook' => __('ফেসবুকে দিন'), 'whatsapp' => __('হোয়াটসঅ্যাপে দিন'), 'instagram' => __('ইনস্টাগ্রামে দিন')][$ch] }}
    </x-filament::button>
</div>
