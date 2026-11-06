<x-layouts.app :seo="$seo">
    @vite('resources/js/submit.js')
    @include('partials.chat')
    <section class="mx-auto max-w-[820px] px-5 pb-16 pt-8">
        <nav aria-label="অবস্থান" class="text-sm text-ink2"><a href="{{ \App\Support\Url::to('/account') }}" class="hover:text-red">আমার লেখা</a> ›</nav>
        <h1 class="mt-2 font-display text-4xl font-extrabold">{{ $post->exists ? $post->title : 'লেখা পাঠান' }}</h1>
        <p class="mt-2 text-ink2">নিয়ম মনে আছে তো? <a href="{{ \App\Support\Url::to('/submissions') }}" class="underline" target="_blank">লেখা পাঠানোর নিয়ম</a></p>

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
            <form method="POST" data-submit-form @if ($editable) data-editable="1" @endif @if (! $post->exists) data-new="1" @endif
                action="{{ \App\Support\Url::to($post->exists ? '/submit/'.$post->id : '/submit') }}"
                data-autosave-url="{{ \App\Support\Url::to($post->exists ? '/submit/'.$post->id.'/autosave' : '/submit/autosave') }}"
                data-preview-url="{{ \App\Support\Url::to('/submit/preview') }}" enctype="multipart/form-data" class="mt-6">
                @csrf
                <label class="mb-4 block">
                    <span class="mb-1 block font-bold">বিভাগ</span>
                    <select name="section_id" required class="block h-12 w-full rounded-xl border-2 border-navy bg-white px-3 text-lg">
                        <option value="">বেছে নিন…</option>
                        @foreach ($sections as $s)
                            <option value="{{ $s->id }}" data-format="{{ $s->format }}" @selected((int) old('section_id', $post->section_id) === $s->id)>{{ $s->name_bn }}</option>
                        @endforeach
                    </select>
                </label>
                @php($contests = \App\Models\Contest::openNow()->orderBy('ends_at')->get())
                @if ($contests->isNotEmpty() || $post->contest_id)
                    {{-- Writing contests (D53): optional; entries go through this same form. --}}
                    <label class="mb-4 block rounded-xl border-2 border-peach-deep bg-peach-light p-3">
                        <span class="mb-1 block font-bold">প্রতিযোগিতায় পাঠাতে চান? (ঐচ্ছিক)</span>
                        <select name="contest_id" class="block h-12 w-full rounded-xl border-2 border-navy bg-white px-3 text-lg">
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
                    </label>
                @endif
                <x-field name="title" label="শিরোনাম" :value="$post->exists ? $post->title : ''" maxlength="200" required />
                {{-- Editor (D40): a small toolbar over the text. Without JavaScript the plain box below still works. --}}
                <input type="hidden" name="body_format" value="text" data-body-format>
                <script type="application/json" id="body-initial">@json(old('body_format') === 'html' ? (string) old('body') : $html)</script>
                <div class="mb-2">
                    <span class="mb-1 flex items-baseline justify-between font-bold"><span id="body-label">লেখা</span><span id="word-count" class="text-sm font-normal text-ink2"></span></span>
                    <div data-editor-shell hidden class="rounded-xl border-2 border-navy bg-white focus-within:border-sky-deep">
                        <div role="toolbar" aria-label="লেখার সাজ" data-toolbar class="ed-toolbar sticky top-0 z-10 flex gap-1 overflow-x-auto rounded-t-xl border-b-2 border-rule bg-paper p-2 sm:flex-wrap">
                            <button type="button" data-cmd="bold" title="গাঢ় (Ctrl+B)" aria-label="গাঢ়" class="ed-btn font-extrabold">ক</button>
                            <button type="button" data-cmd="italic" title="বাঁকা (Ctrl+I)" aria-label="বাঁকা" class="ed-btn italic">ক</button>
                            <button type="button" data-cmd="underline" title="নিচে দাগ (Ctrl+U)" aria-label="নিচে দাগ" class="ed-btn underline">ক</button>
                            <button type="button" data-cmd="strike" title="কেটে দেওয়া" aria-label="কেটে দেওয়া" class="ed-btn line-through">ক</button>
                            <button type="button" data-cmd="superscript" title="উপরে ছোট লেখা (যেমন ১ম, পাদটীকা¹)" aria-label="উপরে ছোট লেখা" class="ed-btn">ক<sup>২</sup></button>
                            <button type="button" data-cmd="subscript" title="নিচে ছোট লেখা" aria-label="নিচে ছোট লেখা" class="ed-btn">ক<sub>২</sub></button>
                            <span class="mx-1 w-px self-stretch bg-rule" aria-hidden="true"></span>
                            <button type="button" data-cmd="heading" title="উপশিরোনাম" aria-label="উপশিরোনাম" class="ed-btn px-3 text-sm font-bold">উপশিরোনাম</button>
                            <button type="button" data-cmd="blockquote" title="উদ্ধৃতি" aria-label="উদ্ধৃতি" class="ed-btn text-2xl leading-none">“</button>
                            <button type="button" data-cmd="bulletList" title="তালিকা" aria-label="তালিকা" class="ed-btn">•</button>
                            <button type="button" data-cmd="orderedList" title="ক্রমিক তালিকা" aria-label="ক্রমিক তালিকা" class="ed-btn">১.</button>
                            <button type="button" data-cmd="horizontalRule" title="ভাগ (অংশের মাঝে দাগ)" aria-label="ভাগ" class="ed-btn">—</button>
                            <span class="mx-1 w-px self-stretch bg-rule" aria-hidden="true"></span>
                            <button type="button" data-cmd="alignLeft" title="বাঁ দিকে" aria-label="বাঁ দিকে" class="ed-btn"><svg viewBox="0 0 20 20" width="20" height="20" aria-hidden="true" fill="currentColor"><rect x="2" y="3" width="16" height="2" rx="1"/><rect x="2" y="7" width="10" height="2" rx="1"/><rect x="2" y="11" width="16" height="2" rx="1"/><rect x="2" y="15" width="10" height="2" rx="1"/></svg></button>
                            <button type="button" data-cmd="alignCenter" title="মাঝখানে" aria-label="মাঝখানে" class="ed-btn"><svg viewBox="0 0 20 20" width="20" height="20" aria-hidden="true" fill="currentColor"><rect x="2" y="3" width="16" height="2" rx="1"/><rect x="5" y="7" width="10" height="2" rx="1"/><rect x="2" y="11" width="16" height="2" rx="1"/><rect x="5" y="15" width="10" height="2" rx="1"/></svg></button>
                            <button type="button" data-cmd="alignRight" title="ডান দিকে" aria-label="ডান দিকে" class="ed-btn"><svg viewBox="0 0 20 20" width="20" height="20" aria-hidden="true" fill="currentColor"><rect x="2" y="3" width="16" height="2" rx="1"/><rect x="8" y="7" width="10" height="2" rx="1"/><rect x="2" y="11" width="16" height="2" rx="1"/><rect x="8" y="15" width="10" height="2" rx="1"/></svg></button>
                            <button type="button" data-cmd="clear" title="বাছাই করা অংশের সব সাজ মুছুন" aria-label="সাজ মুছুন" class="ed-btn px-3 text-sm font-bold">সাজ মুছুন</button>
                            <span class="mx-1 w-px self-stretch bg-rule" aria-hidden="true"></span>
                            <button type="button" data-cmd="undo" title="ফিরিয়ে আনুন (Ctrl+Z)" aria-label="ফিরিয়ে আনুন" class="ed-btn">↶</button>
                            <button type="button" data-cmd="redo" title="আবার করুন" aria-label="আবার করুন" class="ed-btn">↷</button>
                        </div>
                        <div class="ed-wrap font-serif text-lg leading-relaxed">
                            <div data-editor-placeholder class="ed-placeholder" aria-hidden="true">এখানে লিখুন, বা Word / Google Docs / WhatsApp থেকে পেস্ট করুন।</div>
                            <div data-editor lang="bn"></div>
                        </div>
                    </div>
                    <p data-editor-hint hidden class="mt-1 text-sm text-ink2"></p>
                    <textarea name="body" rows="18" required lang="bn"
                        class="block w-full rounded-xl border-2 border-navy bg-white p-4 font-serif text-lg leading-relaxed"
                        placeholder="এখানে লিখুন, বা Word / Google Docs / WhatsApp থেকে পেস্ট করুন।&#10;কবিতায় প্রতিটি লাইন আলাদা লাইনে; স্তবকের মাঝে একটি ফাঁকা লাইন।">{{ old('body_format') === 'html' ? '' : old('body', $text) }}</textarea>
                </div>
                <p class="mb-5 text-sm text-ink2"><span id="autosave-status" aria-live="polite"></span></p>

                {{-- Optional cover (3f). Re-encoded on the server: location and camera data are removed. --}}
                <fieldset class="mb-6 rounded-xl border-2 border-rule p-4">
                    <legend class="px-1 font-bold">প্রচ্ছদের ছবি <span class="font-normal text-ink2">(ইচ্ছে হলে)</span></legend>
                    @if ($post->featured_image_path)
                        <img src="{{ asset($post->featured_image_path) }}" alt="এখনকার প্রচ্ছদ" data-current-cover class="mb-3 max-h-56 rounded-xl">
                        <label class="mb-3 flex items-center gap-3"><input type="checkbox" name="remove_cover" value="1" class="h-5 w-5"> ছবিটি সরিয়ে দিন</label>
                    @endif
                    {{-- The browser's own file button is English ("Choose File"); a Bengali label opens it instead. --}}
                    <div class="flex flex-wrap items-center gap-3">
                        <label class="relative inline-flex h-11 cursor-pointer items-center rounded-full border-2 border-navy bg-white px-5 font-bold focus-within:ring-2 focus-within:ring-sky-deep">
                            {{ $post->featured_image_path ? 'অন্য ছবি বেছে নিন' : 'ছবি বেছে নিন' }}
                            <input type="file" name="cover" accept="image/jpeg,image/png,image/webp" data-cover class="absolute inset-0 h-full w-full cursor-pointer opacity-0">
                        </label>
                        <span data-cover-name class="text-ink2">কোনো ছবি বাছা হয়নি</span>
                    </div>
                    <img data-cover-preview alt="" class="mt-3 hidden max-h-56 rounded-xl">
                    <p class="mt-2 text-sm text-ink2">নিজের তোলা বা আঁকা ছবি, অথবা যে ছবি ব্যবহারের অনুমতি আছে। JPG, PNG বা WebP; ৮ MB পর্যন্ত; অন্তত ৮০০ পিক্সেল চওড়া, আড়াআড়ি হলে ভালো। ছবির ভেতরের অবস্থান ও ক্যামেরার তথ্য মুছে রাখা হয়।</p>
                </fieldset>

                <label class="mb-6 flex items-start gap-3 rounded-xl bg-peach-light p-4">
                    <input type="checkbox" name="original" value="1" class="mt-1 h-5 w-5">
                    <span>আমি ঘোষণা করছি, লেখাটি <strong>আমার নিজের</strong>। অন্য কোথাও আগে প্রকাশিত হয়ে থাকলে তা লেখার নিচে জানিয়েছি। <a href="{{ \App\Support\Url::to('/terms-conditions') }}" class="underline" target="_blank">শর্তাবলি</a> মেনে নিচ্ছি।</span>
                </label>

                <div class="flex flex-wrap gap-3">
                    <button type="submit" name="action" value="submit" class="h-12 flex-1 rounded-full bg-red px-6 font-bold text-white">জমা দিন</button>
                    <button type="button" id="preview-btn" class="h-12 rounded-full border-2 border-navy px-6 font-bold">কেমন দেখাবে</button>
                    <button type="submit" name="action" value="draft" formnovalidate class="h-12 rounded-full px-4 font-bold underline">খসড়া রাখুন</button>
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
