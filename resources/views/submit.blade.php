<x-layouts.app :seo="$seo">
    @vite('resources/js/submit.js')
    @include('partials.chat')
    <section class="mx-auto max-w-[820px] px-5 pb-28 pt-8 sm:pb-16">
        <nav aria-label="অবস্থান" class="text-sm text-ink2"><a href="{{ \App\Support\Url::to('/account') }}" class="inline-flex min-h-11 items-center hover:text-red">← আমার লেখা</a></nav>
        <div class="mt-2 flex flex-wrap items-end justify-between gap-2">
            <h1 class="font-display text-4xl font-extrabold">{{ $post->exists ? 'লেখাটি সাজান' : 'নতুন লেখা' }}</h1>
            <span id="autosave-status" aria-live="polite" class="text-sm font-semibold text-sky-night"></span>
        </div>
        <p class="mt-1 text-sm text-ink2">লিখতে লিখতে নিজে থেকেই খসড়া রাখা হয়। <a href="{{ \App\Support\Url::to('/submissions') }}" class="font-bold underline" target="_blank">লেখা পাঠানোর নিয়ম</a></p>

        @if (session('status'))
            <div role="status" class="mt-5 rounded-xl border-2 border-sky bg-sky-tint p-4 font-semibold text-sky-night">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div role="alert" class="mt-5 rounded-xl border-2 border-red bg-peach-light p-4 font-semibold text-red-dark">@foreach ($errors->all() as $e)<p>{{ $e }}</p>@endforeach</div>
        @endif
        @if ($post->status === 'changes' && $post->review_note)
            <div class="mt-5 rounded-xl border-2 border-red bg-peach-light p-4"><strong>সম্পাদকের মন্তব্য:</strong> {{ $post->review_note }}</div>
        @endif
        @if ($needsVerification)
            <div class="mt-5 rounded-xl border-2 border-red bg-peach-light p-4 font-semibold">জমা দেওয়ার আগে ইমেল যাচাই করুন। খসড়া এখনই লিখে রাখতে পারেন।</div>
        @endif

        @if ($post->status === 'confirm' && $post->pending_edits)
            {{-- The editor's word changes, waiting for the writer's OK (D42). --}}
            <div class="mt-6 rounded-2xl border-2 border-sky bg-sky-tint p-5">
                <h2 class="text-xl font-extrabold">সম্পাদকের প্রস্তাবিত বদল</h2>
                <p class="mt-1">লেখাটি মনোনীত হয়েছে। প্রকাশের আগে সম্পাদক এই শব্দগুলো বদলাতে চান:</p>
                <ul class="mt-3 space-y-2">
                    @foreach ((array) ($post->pending_edits['edits'] ?? []) as $e)
                        <li class="rounded-xl bg-white p-3"><del class="text-ink2">{{ $e['original'] }}</del> → <strong>{{ $e['replacement'] }}</strong></li>
                    @endforeach
                </ul>
                <form method="POST" action="{{ \App\Support\Url::to('/submit/'.$post->id.'/confirm') }}" class="mt-4">
                    @csrf
                    <button name="answer" value="accept" class="h-12 rounded-full bg-red px-6 font-bold text-white">ঠিক আছে, প্রকাশ করুন</button>
                </form>
                <form method="POST" action="{{ \App\Support\Url::to('/submit/'.$post->id.'/confirm') }}" class="mt-4">
                    @csrf
                    <label class="block"><span class="mb-1 block font-bold">আপত্তি থাকলে লিখুন</span>
                        <textarea name="reason" rows="3" class="block w-full rounded-xl border-2 border-navy bg-white p-3">{{ old('reason') }}</textarea></label>
                    <button name="answer" value="object" class="mt-2 h-11 rounded-full border-2 border-navy px-5 font-bold">আপত্তি জানান</button>
                </form>
            </div>
        @endif
        @if (! $editable)
            <div class="mt-6 rounded-xl border-2 border-navy p-5">
                <p class="font-bold">লেখাটি জমা পড়েছে; এখন আর বদলানো যায় না।</p>
                <p class="mt-1 text-ink2">অবস্থা: {{ \App\Http\Controllers\AccountController::STATUS[$post->status][0] ?? $post->status }}</p>
            </div>
            @if ($post->featured_image_path)<img src="{{ asset($post->featured_image_path) }}" alt="" class="mt-6 w-full rounded-2xl">@endif
            <div class="{{ $post->format === 'poem' ? 'poem-bn text-center' : 'prose-bn' }} mt-6">{!! $post->body_html !!}</div>
        @else
            @php(request()->attributes->set('hp.no-bottom-nav', true))
            {{-- The composer (owner: "as easy as Facebook or Instagram", on phone and web). Field names are unchanged;
                 without JavaScript every part still works as a plain form. --}}
            <form method="POST" data-submit-form @if ($editable) data-editable="1" @endif @if (! $post->exists) data-new="1" @endif
                action="{{ \App\Support\Url::to($post->exists ? '/submit/'.$post->id : '/submit') }}"
                data-autosave-url="{{ \App\Support\Url::to($post->exists ? '/submit/'.$post->id.'/autosave' : '/submit/autosave') }}"
                data-preview-url="{{ \App\Support\Url::to('/submit/preview') }}" enctype="multipart/form-data" class="mt-5" novalidate>
                @csrf

                {{-- 1. What kind of writing: pills, one tap --}}
                <fieldset class="mb-5 min-w-0">
                    <legend class="mb-2 font-bold">কী লিখছেন?</legend>
                    <div class="relative flex flex-wrap gap-2" data-sections>
                        @foreach ($sections as $s)
                            <label class="relative flex-none">
                                <input type="radio" name="section_id" value="{{ $s->id }}" data-format="{{ $s->format }}" class="peer sr-only" @checked((int) old('section_id', $post->section_id) === $s->id) @if ($loop->first) required @endif>
                                <span class="flex h-11 cursor-pointer items-center rounded-full border-2 border-navy bg-white px-4 font-bold peer-checked:bg-navy peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-sky-deep">{{ $s->name_bn }}</span>
                            </label>
                        @endforeach
                    </div>
                    <p data-error="section" hidden class="mt-2 text-sm font-bold text-red" role="alert">কোন ধরনের লেখা, একটি বেছে নিন।</p>
                </fieldset>

                @php($contests = \App\Models\Contest::openNow()->orderBy('ends_at')->get())
                @if ($contests->isNotEmpty() || $post->contest_id)
                    {{-- Writing contests (D53): optional; entries go through this same form. --}}
                    <details class="mb-5 rounded-2xl border-2 border-peach-deep bg-peach-light p-4" @if (old('contest_id', $post->contest_id)) open @endif>
                        <summary class="cursor-pointer font-bold">প্রতিযোগিতায় পাঠাবেন? <span class="font-normal text-ink2">(ইচ্ছে হলে)</span></summary>
                        <select name="contest_id" aria-label="প্রতিযোগিতা" class="mt-3 block h-12 w-full rounded-xl border-2 border-navy bg-white px-3 text-lg">
                            <option value="">না, সাধারণ লেখা</option>
                            @foreach ($contests as $c)
                                <option value="{{ $c->id }}" @selected((int) old('contest_id', $post->contest_id) === $c->id)>{{ $c->title }}@if ($c->ends_at) (শেষ {{ \App\Support\Bn::num($c->ends_at->timezone('Asia/Kolkata')->format('d/m')) }})@endif</option>
                            @endforeach
                        </select>
                        @foreach ($contests as $c)
                            @if ($c->rules)
                                <details class="mt-2 text-sm"><summary class="cursor-pointer font-semibold">"{{ $c->title }}"-এর নিয়ম</summary><div class="mt-1 whitespace-pre-line">{{ $c->rules }}</div></details>
                            @endif
                        @endforeach
                    </details>
                @endif

                {{-- 2. The writing: title, then the text, in one clean card --}}
                <div class="rounded-2xl border-2 border-navy bg-white shadow-[4px_4px_0_#13212C] focus-within:border-sky-deep">
                    <label class="block border-b-2 border-rule px-4 pt-3">
                        <span class="sr-only">শিরোনাম</span>
                        <input name="title" value="{{ old('title', $post->exists ? $post->title : '') }}" maxlength="200" required autocomplete="off" enterkeyhint="next"
                            placeholder="শিরোনাম" class="block h-14 w-full border-0 bg-transparent p-0 font-display text-2xl font-extrabold placeholder:text-ink2/60 focus:outline-none focus:ring-0">
                    </label>
                    <p data-error="title" hidden class="px-4 pt-1 text-sm font-bold text-red" role="alert">একটি শিরোনাম দিন।</p>

                    {{-- Editor (D40). Without JavaScript the plain box below still works. --}}
                    <input type="hidden" name="body_format" value="text" data-body-format>
                    <script type="application/json" id="body-initial">@json(old('body_format') === 'html' ? (string) old('body') : $html)</script>
                    <span id="body-label" class="sr-only">লেখা</span>
                    <div data-editor-shell hidden>
                        <div role="toolbar" aria-label="লেখার সাজ" data-toolbar class="ed-toolbar sticky top-0 z-10 border-b-2 border-rule bg-paper/95 backdrop-blur">
                            <div class="flex items-center gap-0.5 overflow-x-auto whitespace-nowrap p-1 sm:gap-1 sm:p-1.5 [&>*]:flex-none [&>.ed-btn]:min-w-10 sm:[&>.ed-btn]:min-w-11">
                                {{-- Symbols everyone knows from WhatsApp, Facebook and Word (owner: the Bengali terms were hard); the name shows on long-press/hover and is read aloud. --}}
                                <button type="button" data-cmd="bold" title="মোটা অক্ষর (Bold)" aria-label="মোটা অক্ষর" class="ed-btn font-sans text-xl font-black">B</button>
                                <button type="button" data-cmd="italic" title="হেলানো অক্ষর (Italic)" aria-label="হেলানো অক্ষর" class="ed-btn font-serif text-xl italic">I</button>
                                <button type="button" data-cmd="heading" title="ছোট শিরোনাম" aria-label="ছোট শিরোনাম" class="ed-btn font-sans text-xl font-black">H</button>
                                <button type="button" data-cmd="blockquote" title="উক্তি / কোটেশন" aria-label="উক্তি" class="ed-btn"><svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M6 17h3l2-4V7H5v6h3zm8 0h3l2-4V7h-6v6h3z"/></svg></button>
                                <button type="button" data-cmd="bulletList" title="তালিকা" aria-label="তালিকা" class="ed-btn"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6h11M9 12h11M9 18h11"/><circle cx="4.5" cy="6" r="1"/><circle cx="4.5" cy="12" r="1"/><circle cx="4.5" cy="18" r="1"/></svg></button>
                                <button type="button" data-pick-photo title="ছবি যোগ করুন" aria-label="ছবি যোগ করুন" class="ed-btn"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="12" cy="12" r="3.5"/><path d="M8 5l1.5-2h5L16 5"/></svg></button>
                                <button type="button" data-more-toggle aria-expanded="false" aria-controls="ed-more" class="ed-btn px-2 text-base font-bold">আরও ▾</button>
                                <span class="ml-auto hidden sm:flex">
                                    <button type="button" data-cmd="undo" title="ফিরিয়ে আনুন (Ctrl+Z)" aria-label="ফিরিয়ে আনুন" class="ed-btn">↶</button>
                                    <button type="button" data-cmd="redo" title="আবার করুন" aria-label="আবার করুন" class="ed-btn">↷</button>
                                </span>
                            </div>
                            <div id="ed-more" data-more hidden class="flex flex-wrap gap-1 border-t border-rule p-1.5 text-sm font-bold">
                                <button type="button" data-cmd="underline" class="ed-btn px-2 underline">নিচে দাগ</button>
                                <button type="button" data-cmd="strike" class="ed-btn px-2 line-through">কেটে দেওয়া</button>
                                <button type="button" data-cmd="superscript" title="উপরে ছোট লেখা (যেমন ১ম, পাদটীকা¹)" class="ed-btn px-2">উপরে ছোট<sup>২</sup></button>
                                <button type="button" data-cmd="subscript" class="ed-btn px-2">নিচে ছোট<sub>২</sub></button>
                                <button type="button" data-cmd="orderedList" class="ed-btn px-2">১. নম্বর দিয়ে তালিকা</button>
                                <button type="button" data-cmd="horizontalRule" class="ed-btn px-2">— দাগ টেনে ভাগ</button>
                                <button type="button" data-cmd="alignLeft" class="ed-btn px-2">বাঁয়ে সাজান</button>
                                <button type="button" data-cmd="alignCenter" class="ed-btn px-2">মাঝে সাজান</button>
                                <button type="button" data-cmd="alignRight" class="ed-btn px-2">ডানে সাজান</button>
                                <button type="button" data-cmd="undo" class="ed-btn px-2 sm:hidden">↶ ফিরিয়ে আনুন</button>
                                <button type="button" data-cmd="redo" class="ed-btn px-2 sm:hidden">↷ আবার</button>
                                <button type="button" data-cmd="clear" title="বাছাই করা অংশের সব সাজ মুছুন" class="ed-btn px-2">সাজ মুছুন</button>
                            </div>
                        </div>
                        <div class="ed-wrap font-serif text-lg leading-relaxed">
                            <div data-editor-placeholder class="ed-placeholder" aria-hidden="true">এখানে লিখুন… অথবা Word, Google Docs, WhatsApp বা ফেসবুক থেকে পেস্ট করুন।</div>
                            <div data-editor lang="bn"></div>
                        </div>
                    </div>
                    <textarea name="body" rows="14" required lang="bn" aria-labelledby="body-label"
                        class="block w-full rounded-b-2xl border-0 bg-transparent p-4 font-serif text-lg leading-relaxed focus:outline-none focus:ring-0"
                        placeholder="এখানে লিখুন… অথবা Word, Google Docs, WhatsApp বা ফেসবুক থেকে পেস্ট করুন।&#10;কবিতায় প্রতিটি লাইন আলাদা লাইনে; স্তবকের মাঝে একটি ফাঁকা লাইন।">{{ old('body_format') === 'html' ? '' : old('body', $text) }}</textarea>
                    <div class="flex flex-wrap items-center justify-between gap-2 border-t-2 border-rule px-4 py-2 text-sm text-ink2">
                        <span data-editor-hint hidden></span>
                        <span id="word-count" class="ml-auto"></span>
                    </div>
                </div>
                <p data-error="body" hidden class="mt-2 text-sm font-bold text-red" role="alert">লেখাটি এখনও ফাঁকা।</p>

                {{-- 3. A picture, Instagram-style: one big tap area, the photo shows at once --}}
                <div class="mt-6">
                    <span class="mb-2 block font-bold">ছবি <span class="font-normal text-ink2">(ইচ্ছে হলে)</span></span>
                    <label class="relative flex min-h-[9rem] cursor-pointer flex-col items-center justify-center overflow-hidden rounded-2xl border-2 border-dashed border-navy bg-paper text-center hover:bg-peach-light focus-within:ring-2 focus-within:ring-sky-deep" data-cover-drop>
                        <img data-cover-preview alt="" @if ($post->featured_image_path) src="{{ asset($post->featured_image_path) }}" @endif class="{{ $post->featured_image_path ? '' : 'hidden' }} absolute inset-0 h-full w-full object-cover" data-current-cover>
                        <span data-cover-empty class="{{ $post->featured_image_path ? 'hidden' : 'flex' }} flex-col items-center gap-1 p-4">
                            <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="12" cy="12" r="3.5"/><path d="M8 5l1.5-2h5L16 5"/></svg>
                            <span class="font-bold">ছবি যোগ করুন</span>
                            <span class="text-sm text-ink2">ফোনের গ্যালারি বা ক্যামেরা থেকে</span>
                        </span>
                        <span data-cover-change class="{{ $post->featured_image_path ? '' : 'hidden' }} absolute bottom-2 right-2 rounded-full bg-navy px-3 py-1.5 text-sm font-bold text-white">ছবি বদলান</span>
                        <input type="file" name="cover" accept="image/jpeg,image/png,image/webp" data-cover class="absolute inset-0 h-full w-full cursor-pointer opacity-0" aria-label="ছবি যোগ করুন">
                    </label>
                    @if ($post->featured_image_path)
                        <label class="mt-2 flex min-h-11 items-center gap-3 text-sm"><input type="checkbox" name="remove_cover" value="1"> ছবিটি সরিয়ে দিন</label>
                    @endif
                    <p class="mt-2 text-xs text-ink2">নিজের তোলা বা আঁকা ছবি, বা যে ছবি ব্যবহারের অনুমতি আছে। JPG, PNG বা WebP, ৮ MB পর্যন্ত; আড়াআড়ি হলে ভালো। ছবির ভেতরের অবস্থান ও ক্যামেরার তথ্য মুছে রাখা হয়।</p>
                </div>

                {{-- 4. The promise --}}
                <label class="mt-6 flex items-start gap-3 rounded-2xl bg-peach-light p-4">
                    <input type="checkbox" name="original" value="1" class="mt-0.5" @checked(old('original'))>
                    <span>লেখাটি <strong>আমার নিজের</strong>। আগে অন্য কোথাও প্রকাশিত হয়ে থাকলে লেখার নিচে জানিয়েছি। <a href="{{ \App\Support\Url::to('/terms-conditions') }}" class="underline" target="_blank">শর্তাবলি</a> মেনে নিচ্ছি।</span>
                </label>
                <p data-error="original" hidden class="mt-2 text-sm font-bold text-red" role="alert">জমা দেওয়ার আগে এই ঘরে টিক দিন।</p>

                {{-- 5. Actions: fixed at the bottom on phones (like a social app), at the end on wider screens --}}
                <div data-action-bar class="fixed inset-x-0 bottom-0 z-40 border-t-2 border-navy bg-paper/95 px-4 pb-[calc(.75rem+env(safe-area-inset-bottom))] pt-3 backdrop-blur sm:static sm:mt-8 sm:border-0 sm:bg-transparent sm:p-0 sm:backdrop-blur-none">
                    <div class="mx-auto flex max-w-[820px] items-center gap-2">
                        <button type="submit" name="action" value="draft" formnovalidate class="flex h-12 flex-none items-center rounded-full px-3 text-sm font-bold text-ink2 underline sm:px-4 sm:text-base" data-draft-btn>খসড়া রাখুন</button>
                        <span data-bar-status hidden class="min-w-0 flex-1 truncate text-xs font-semibold text-sky-night sm:hidden"></span>
                        <button type="button" id="preview-btn" class="h-12 flex-none rounded-full border-2 border-navy bg-white px-4 font-bold sm:px-6">কেমন দেখাবে</button>
                        <button type="submit" name="action" value="submit" class="h-12 flex-1 rounded-full bg-red px-6 text-lg font-bold text-white shadow-[3px_3px_0_#13212C]">জমা দিন</button>
                    </div>
                </div>
            </form>

            <dialog id="preview" class="m-auto max-h-[90vh] w-[min(760px,94vw)] overflow-auto rounded-2xl bg-paper p-6 text-navy backdrop:bg-navy/60">
                <div class="flex items-start justify-between gap-4">
                    <h2 data-title class="font-display text-3xl font-extrabold"></h2>
                    <button type="button" data-close class="h-11 rounded-full border-2 border-navy px-4 font-bold">বন্ধ</button>
                </div>
                <img data-preview-cover alt="" hidden class="mt-5 w-full rounded-2xl">
                <p class="mt-3 font-semibold text-ink2">{{ auth()->user()->name }}</p>
                <div data-body></div>
            </dialog>
        @endif
    </section>
</x-layouts.app>
