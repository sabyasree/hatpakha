{{-- D321: the children's map: who is learning, every unit and lesson with its stars, "চালিয়ে যাও" --}}
@php($N = fn ($n) => \App\Support\Bn::num((string) $n))
<x-layouts.learn :seo="$seo" :data="['units' => collect($units)->map(fn ($u, $slug) => ['slug' => $slug, 'n' => $u['n'], 'count' => count($u['lessons']), 'sticker' => $u['sticker']])->values()]">
    <div data-learn-map class="mx-auto max-w-[720px] px-4 pb-10 pt-[max(1rem,env(safe-area-inset-top))]">
        <header class="flex items-center gap-3">
            <a href="{{ \App\Support\LearnCourse::url() }}" class="flex h-12 flex-none items-center rounded-full border-2 border-navy bg-paper px-4 font-bold" aria-label="বাংলা শিখি, বাবা-মায়ের পাতা" data-en-label="Learn Bengali, page for grown-ups">← হাতপাখা</a>
            <button type="button" data-ui-toggle class="flex h-12 flex-none items-center rounded-full border-2 border-navy bg-paper px-3 font-bold"><span data-en="বাংলা">English</span></button>
            <button type="button" data-who class="ml-auto flex h-12 items-center gap-2 rounded-full border-2 border-navy bg-paper px-4 font-bold"><span data-who-face class="text-2xl" aria-hidden="true">🐟</span><span data-who-name>কে শিখছে?</span></button>
        </header>
        <h1 class="mt-5 font-display text-4xl font-extrabold">বাংলা শিখি</h1>
        <p lang="en" class="text-base text-ink2">Learn to read Bengali</p>
        <p class="mt-1 text-lg"><span data-done>{{ $N(0) }}</span>/{{ \App\Support\LearnCourse::count() }} <span data-en="lessons">পাঠ</span> · <span data-stars>{{ $N(0) }}</span> ⭐</p>
        <div data-sticker-book class="mt-2 flex min-h-9 flex-wrap gap-1" aria-label="আমার স্টিকার" data-en-label="My stickers"></div>
        <a data-continue href="{{ \App\Support\LearnCourse::url('first-letters', 1) }}" class="mt-4 flex min-h-16 items-center justify-center rounded-2xl bg-red px-6 font-display text-2xl font-extrabold text-white shadow-[4px_4px_0_#13212C]">শুরু করো →</a>
        <ol class="mt-8 space-y-6">
            @foreach ($units as $slug => $u)
                <li data-unit="{{ $slug }}" class="rounded-3xl border-2 border-navy bg-paper p-4">
                    <div class="flex items-center gap-3">
                        <span class="flex h-14 w-14 flex-none items-center justify-center rounded-full bg-peach text-3xl grayscale-[85%] opacity-60 transition" data-sticker title="{{ $u['sticker'][1] }}" aria-hidden="true">{{ $u['sticker'][0] }}</span>
                        <div class="min-w-0"><h2 class="font-display text-xl font-extrabold leading-tight">{{ $N($u['n']) }}. {{ $u['title'] }}</h2>
                            <p lang="en" class="text-sm text-ink2">{{ $u['en'] }}</p></div>
                    </div>
                    <ol class="mt-3 flex flex-wrap gap-3">
                        @foreach ($u['lessons'] as $i => $l)
                            <li><a href="{{ \App\Support\LearnCourse::url($slug, $i + 1) }}" data-lesson="{{ $slug }}/{{ $i + 1 }}" class="relative flex h-16 w-16 flex-col items-center justify-center rounded-2xl border-2 border-navy bg-white font-display text-xl font-extrabold"
                                aria-label="পাঠ {{ $N($i + 1) }}: {{ $l['title'] }}" data-en-label="Lesson {{ $i + 1 }}: {{ $l['title'] }}"><span data-num>{{ $N($i + 1) }}</span><span data-lesson-stars class="text-xs leading-none" aria-hidden="true"></span></a></li>
                        @endforeach
                    </ol>
                </li>
            @endforeach
        </ol>
        <p class="mt-8 text-center text-sm text-ink2" data-en="Your stars and stickers stay on this phone. Nothing is sent anywhere.">তোমার তারা আর স্টিকার এই ফোনেই থাকে, কোথাও পাঠানো হয় না।</p>
    </div>
    <dialog data-who-dialog class="m-auto w-[min(94vw,460px)] rounded-2xl border-2 border-navy bg-paper p-5 text-navy backdrop:bg-navy/60" aria-labelledby="who-h">
        <h2 id="who-h" class="font-display text-2xl font-extrabold" data-en="Who is learning?">কে শিখছে?</h2>
        <ul data-who-list class="mt-3 space-y-2"></ul>
        <form data-who-add class="mt-4 rounded-2xl border-2 border-dashed border-navy p-3">
            <label for="who-new" class="font-bold" data-en="A new name (up to four)">নতুন নাম (চারজন পর্যন্ত)</label>
            <input id="who-new" name="name" maxlength="20" autocomplete="off" class="mt-1 block min-h-12 w-full rounded-xl border-2 border-navy bg-white px-3 text-lg" placeholder="যেমন: মিঠু" data-en-placeholder="e.g. Mithu">
            <div class="mt-2 flex flex-wrap gap-2" role="radiogroup" aria-label="একটা ছবি বাছো" data-en-label="Pick a picture" data-faces></div>
            <button class="mt-3 min-h-12 w-full rounded-full bg-red font-bold text-white" data-en="Add">যোগ করো</button>
        </form>
        <button type="button" data-who-close class="mt-3 min-h-11 w-full rounded-full border-2 border-navy font-bold" data-en="Close">বন্ধ</button>
    </dialog>
</x-layouts.learn>
