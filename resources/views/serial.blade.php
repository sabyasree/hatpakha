{{-- D108: one serial: poster, the story in a line, every episode, and "tell me when the next one comes". --}}
<x-layouts.app :seo="$seo">
    <div class="h-1 bg-gradient-to-r from-pink via-red to-peach" aria-hidden="true"></div>
    <article class="mx-auto max-w-[820px] px-5 pb-16 pt-8">
        <nav aria-label="অবস্থান" class="text-sm text-ink2">
            <a href="{{ \App\Support\Url::to('/dharabahik') }}" class="inline-flex min-h-11 items-center hover:text-red">← সব ধারাবাহিক</a>
        </nav>
        @unless ($serial->isPublic())
            <p class="mt-3 rounded-xl border-2 border-red bg-peach-light p-3 font-bold" role="status">প্রস্তাব: এখনও শুরু হয়নি। শুধু সম্পাদকেরা দেখতে পাচ্ছেন।</p>
        @endunless
        @if ($serial->cover_path)
            <img src="{{ asset($serial->cover_path) }}" alt="" width="{{ $serial->cover_width }}" height="{{ $serial->cover_height }}" fetchpriority="high" class="mt-4 block h-auto max-h-[70vh] w-full rounded-2xl object-cover">
            <p class="mt-1 text-right text-xs text-ink2">ছবি: এআই-এর সাহায্যে আঁকা</p>
        @endif
        <div class="mt-4 flex flex-wrap items-center gap-2 text-sm font-bold">
            <span class="rounded-full bg-red px-3 py-1 text-white">ধারাবাহিক</span>
            @if ($serial->genre)<span class="rounded-full border-2 border-navy px-3 py-0.5">{{ $serial->genre }}</span>@endif
            <span class="text-ink2">{{ $serial->status === 'finished' ? 'সমাপ্ত' : 'প্রতি '.$serial->dayName().' নতুন পর্ব' }}</span>
        </div>
        <h1 class="mt-3 font-display text-5xl font-extrabold leading-tight md:text-6xl" style="font-stretch: 85%">{{ $serial->title }}</h1>
        @if ($serial->logline)<p class="mt-4 font-serif text-xl leading-relaxed">{{ $serial->logline }}</p>@endif

        @if (session('serial_status'))<p class="mt-4 rounded-2xl bg-sky-tint p-3 font-semibold" role="status">{{ session('serial_status') }}</p>@endif
        <div class="mt-5 flex flex-wrap gap-3">
            @if ($episodes->isNotEmpty())
                <a href="{{ $episodes->first()->url() }}" class="inline-flex h-12 items-center rounded-full bg-navy px-6 font-bold text-white">প্রথম পর্ব থেকে পড়ুন →</a>
            @endif
            @if ($serial->status !== 'finished')
                @include('serial-follow')
            @endif
        </div>

        <h2 class="mt-10 font-display text-3xl font-extrabold">সব পর্ব</h2>
        @if ($episodes->isEmpty())
            <p class="mt-3 text-ink2">প্রথম পর্ব শিগগির আসছে।</p>
        @else
            <ol class="mt-3 divide-y divide-rule border-y-2 border-navy">
                @foreach ($episodes as $e)
                    <li><a href="{{ $e->url() }}" class="flex min-h-14 items-center justify-between gap-3 py-3 hover:text-red">
                        <span><span class="block font-serif text-lg font-bold">{{ $e->episodeLabel() }}</span>
                            <span class="block text-sm text-ink2">{{ \App\Support\Bn::date($e->published_at) }}</span></span><span aria-hidden="true">→</span></a></li>
                @endforeach
            </ol>
            @if ($serial->status === 'active')
                <p class="mt-3 font-bold text-ink2">পরের পর্ব আসবে {{ $serial->dayName() }}।</p>
            @endif
            <x-ad-slot spot="list" :height="250" />
        @endif
    </article>
</x-layouts.app>
