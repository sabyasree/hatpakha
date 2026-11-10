{{-- Book view of an issue (D68): page-turning reader. Without JavaScript it is one long, readable page. --}}
<x-layouts.app :seo="$seo">
    <section class="mx-auto max-w-[900px] px-3 pb-6 pt-4 md:px-5">
        <div class="flex items-center justify-between gap-2">
            <a href="{{ $issue->url() }}" class="font-bold text-sky-deep underline">← {{ $issue->title }}</a>
            <div class="flex items-center gap-1" data-book-tools hidden>
                <button type="button" data-font="-1" class="h-10 w-10 rounded-full border-2 border-navy font-bold" aria-label="লেখা ছোট">অ-</button>
                <button type="button" data-font="1" class="h-10 w-10 rounded-full border-2 border-navy font-bold" aria-label="লেখা বড়">অ+</button>
            </div>
        </div>

        <div id="book" class="book mt-3 rounded-3xl border-2 border-navy bg-white shadow-[6px_6px_0_#13212C]">
            <div class="book-pages font-serif" data-book-pages>
                {{-- Cover --}}
                <div class="book-cover">
                    @if ($issue->cover_path)
                        <img src="{{ $issue->cover_path }}" alt="" class="mx-auto max-h-[60vh] rounded-xl">
                    @endif
                    <p class="mt-6 font-hand text-5xl text-red">হাতপাখা</p>
                    <h1 class="font-display text-4xl font-extrabold">{{ $issue->title }}</h1>
                    <p class="mt-2 text-ink2">বাংলা সাহিত্যের ওয়েব পত্রিকা · ISSN 2321-3035</p>
                </div>
                {{-- Contents --}}
                <h2 class="book-break font-display text-3xl font-extrabold">সূচি</h2>
                <ol class="mt-4 space-y-2">
                    @foreach ($pieces as $i => $p)
                        <li><a href="#piece-{{ $p->id }}" data-goto class="underline decoration-rule underline-offset-4">{{ $p->title }}</a> <span class="text-ink2">— {{ $p->authors->map->publicName()->implode(', ') }}</span></li>
                    @endforeach
                </ol>
                @if ($issue->editor_note)
                    <h2 class="book-break font-display text-3xl font-extrabold">সম্পাদকের কথা</h2>
                    @foreach (preg_split('/\n{2,}/', trim($issue->editor_note)) as $para)<p class="mt-4">{{ trim($para) }}</p>@endforeach
                @endif
                @foreach ($pieces as $p)
                    <article id="piece-{{ $p->id }}" class="book-break {{ $p->isPoem() ? 'text-center' : '' }}">
                        <p class="text-sm font-bold text-red">{{ $p->section?->name_bn }}</p>
                        <h2 class="font-display text-3xl font-extrabold leading-tight">{{ $p->title }}</h2>
                        <p class="mb-5 text-ink2">{{ $p->authors->map->publicName()->implode(', ') }}</p>
                        {{-- Sanitised on save (HtmlSanitizer); pictures are left out of the book view. --}}
                        <div class="{{ $p->isPoem() ? 'poem-bn' : 'prose-bn' }}">{!! \App\Support\IssueBook::xhtml((string) $p->body_html) !!}</div>
                        <p class="mt-6 text-center"><a href="{{ $p->url() }}" class="text-sm text-sky-deep underline">লেখাটির পাতা, মন্তব্য ও শেয়ার →</a></p>
                    </article>
                @endforeach
                <div class="book-break text-center">
                    <p class="font-hand text-4xl text-red">সমাপ্ত</p>
                    <p class="mt-3">আরও পড়ুন <a href="{{ url('/') }}/" class="underline">hatpakha.com</a>-এ। লেখা পাঠান বিনামূল্যে।</p>
                </div>
            </div>
        </div>
        <div class="mt-3 flex items-center justify-between gap-3" data-book-nav hidden>
            <button type="button" data-turn="-1" class="h-12 rounded-full border-2 border-navy px-5 font-bold">← আগের পাতা</button>
            <span class="text-sm font-bold tabular-nums text-ink2" data-book-count></span>
            <button type="button" data-turn="1" class="h-12 rounded-full bg-red px-5 font-bold text-white">পরের পাতা →</button>
        </div>
    </section>
    <script src="{{ asset('js/hp-book.js') }}?v=1" defer></script>
</x-layouts.app>
