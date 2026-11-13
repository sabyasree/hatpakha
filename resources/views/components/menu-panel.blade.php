@props(['menu', 'wide' => false])
{{-- D95: every section, grouped by kind (UX review; English is an ordinary section in "আরও"), then the archive. Shared by the desktop
     "সব বিভাগ" panel and the phone "বিভাগ" menu. --}}
<nav aria-label="সব বিভাগ" class="{{ $wide ? 'grid grid-cols-3 gap-x-6 gap-y-4' : 'space-y-4' }}">
    @foreach ($menu['groups'] as $g)
        <div>
            <p class="mb-1 px-3 text-sm font-bold text-ink2">{{ __($g['label']) }}</p>
            <ul class="grid {{ $wide ? 'grid-cols-1' : 'grid-cols-2' }} gap-1">
                @foreach ($g['links'] as [$name, $href, $lang])
                    <li><a href="{{ $href }}" @if ($lang === 'en') lang="en" @endif class="flex min-h-11 items-center rounded-xl px-3 font-bold hover:bg-peach-light">{{ $name }}</a></li>
                @endforeach
            </ul>
        </div>
    @endforeach
</nav>
<div class="mt-3 flex flex-wrap gap-2 border-t-2 border-rule pt-3">
    @if (\App\Models\Collection::live())
        <a href="{{ \App\Support\Url::to('/sonkolon') }}" class="flex min-h-11 items-center rounded-full border-2 border-navy px-4 font-bold hover:bg-peach-light">সংকলন</a>
    @endif
    @if ($menuIssue = \App\Models\Issue::latestLive())
        <a href="{{ $menuIssue->url() }}" class="flex min-h-11 items-center rounded-full bg-red px-4 font-bold text-white">এই মাসের সংখ্যা</a>
    @endif
    <a href="{{ \App\Support\Url::to('/archive') }}" class="flex min-h-11 items-center rounded-full border-2 border-navy px-4 font-bold hover:bg-peach-light">আর্কাইভ</a>
    <a href="{{ \App\Support\Url::to('/app') }}" data-app-link class="flex min-h-11 items-center rounded-full border-2 border-navy px-4 font-bold hover:bg-peach-light">📱 ফোনে হাতপাখা</a>
</div>
