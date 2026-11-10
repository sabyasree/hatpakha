@php($posts = $this->posts())
<x-filament-panels::page>
    @if ($posts->isEmpty())
        <x-filament::section>{{ __('অনুমোদনের অপেক্ষায় কোনো ছবি নেই।') }}</x-filament::section>
    @else
        <div style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:center;justify-content:space-between">
            <p style="margin:0">{{ __('ভালোগুলোয় টিক দিয়ে অনুমোদন করুন। যেটা মানায়নি, "আবার আঁকুন" বা "বাদ দিন"।') }}</p>
            <div style="display:flex;gap:.5rem">
                <x-filament::button color="gray" wire:click="pickAll">{{ __('সবগুলোয় টিক') }}</x-filament::button>
                <x-filament::button icon="heroicon-o-check" wire:click="approvePicked">{{ __('টিক দেওয়াগুলো সাইটে বসান') }}</x-filament::button>
            </div>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(15rem,1fr));gap:1rem">
            @foreach ($posts as $p)
                <div wire:key="img-{{ $p->id }}" style="border:1px solid rgba(127,127,127,.25);border-radius:.9rem;overflow:hidden;background:rgba(127,127,127,.04)">
                    <label style="display:block;cursor:pointer;position:relative">
                        <img src="{{ asset($p->proposed_image_path) }}" alt="" loading="lazy" style="display:block;width:100%;aspect-ratio:3/2;object-fit:cover">
                        <input type="checkbox" wire:model.live="picked.{{ $p->id }}" style="position:absolute;top:.6rem;left:.6rem;width:1.4rem;height:1.4rem">
                    </label>
                    <div style="padding:.6rem .8rem">
                        <div style="font-weight:700;line-height:1.4">{{ $p->title }}</div>
                        <div style="font-size:.85rem;opacity:.7">{{ $p->authors->first()?->publicName() }} · {{ __(\App\Filament\Resources\Posts\Tables\PostsTable::STATUS[$p->status] ?? $p->status) }}</div>
                        <div style="display:flex;gap:.4rem;margin-top:.5rem">
                            <x-filament::button size="xs" color="gray" icon="heroicon-o-arrow-path" wire:click="redraw({{ $p->id }})">{{ __('আবার আঁকুন') }}</x-filament::button>
                            <x-filament::button size="xs" color="gray" icon="heroicon-o-x-mark" wire:click="drop({{ $p->id }})">{{ __('বাদ দিন') }}</x-filament::button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
