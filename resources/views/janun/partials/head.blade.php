{{-- D301: the reference look (not a literary piece): breadcrumb, kicker chip, plain heading, trust line --}}
<nav aria-label="ব্রেডক্রাম্ব" class="text-sm text-ink2">
    @foreach ($crumbs as [$label, $href])
        @if (! $loop->last)<a href="{{ $href }}" class="inline-flex min-h-11 items-center underline hover:text-navy">{{ $label }}</a> <span aria-hidden="true">›</span> @else<span aria-current="page">{{ $label }}</span>@endif
    @endforeach
</nav>
<span class="mt-3 inline-block rounded-full bg-sky-tint px-3 py-1 text-sm font-bold text-sky-night">{{ $kicker }}</span>
<h1 class="mt-2 font-display text-3xl font-extrabold leading-tight sm:text-5xl">{{ $title }}</h1>
@if ($trust ?? null)<p class="mt-2 text-sm text-ink2">{{ $trust }}@if ($trustBy ?? null) · <a href="{{ $trustBy }}" class="inline-flex min-h-11 items-center underline hover:text-navy">হাতপাখা ডেস্ক</a>@endif</p>@endif
