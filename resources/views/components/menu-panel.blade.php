@props(['menu', 'wide' => false])
{{-- D95: every section, grouped by kind (UX review), then English and the archive. Shared by the desktop
     "সব বিভাগ" panel and the phone "বিভাগ" menu. --}}
@if (! $wide && $menu['english'])
    {{-- Phones: English first, so it is never below the fold (UX review) --}}
    <a href="{{ $menu['english'] }}" lang="en" class="mb-3 flex min-h-11 items-center justify-between rounded-xl border-2 border-navy px-4 font-bold hover:bg-sky-tint">English <span class="text-sm font-semibold text-ink2">Hatpakha in English →</span></a>
@endif
<nav aria-label="সব বিভাগ" class="{{ $wide ? 'grid grid-cols-3 gap-x-6 gap-y-4' : 'space-y-4' }}">
    @foreach ($menu['groups'] as $g)
        <div>
            <p class="mb-1 px-3 text-sm font-bold text-ink2">{{ $g['label'] }}</p>
            <ul class="grid {{ $wide ? 'grid-cols-1' : 'grid-cols-2' }} gap-1">
                @foreach ($g['links'] as [$name, $href])
                    <li><a href="{{ $href }}" class="flex min-h-11 items-center rounded-xl px-3 font-bold hover:bg-peach-light">{{ $name }}</a></li>
                @endforeach
            </ul>
        </div>
    @endforeach
</nav>
<div class="mt-3 flex flex-wrap gap-2 border-t-2 border-rule pt-3">
    @if ($wide && $menu['english'])
        <a href="{{ $menu['english'] }}" lang="en" class="flex min-h-11 items-center gap-2 rounded-full border-2 border-navy px-4 font-bold hover:bg-sky-tint">English <span class="text-sm font-semibold text-ink2">Hatpakha in English</span></a>
    @endif
    <a href="{{ \App\Support\Url::to('/archive') }}" class="flex min-h-11 items-center rounded-full border-2 border-navy px-4 font-bold hover:bg-peach-light">আর্কাইভ</a>
</div>
