{{-- "আমার হাতপাখা" (D59): one page, four tabs. Things that need the writer come first. --}}
@php
    use App\Support\Bn;
    use App\Support\Url;
    $S = \App\Http\Controllers\AccountController::STATUS;
    $act = $posts->whereIn('status', ['changes', 'confirm', 'draft']);
    $rest = $posts->whereNotIn('status', ['changes', 'confirm', 'draft']);
    $published = $posts->where('status', 'published')->count();
    $inReview = $posts->whereIn('status', ['pending', 'scheduled', 'confirm'])->count();
    $avatar = $author?->avatar_path;
    $hasProfile = $author && $author->published_count > 0;
@endphp
<x-layouts.app :seo="$seo">
    <div class="h-1 bg-gradient-to-r from-pink via-red to-peach" aria-hidden="true"></div>

    {{-- Header --}}
    <section class="mx-auto max-w-[1000px] px-5 pt-8">
        <div class="flex flex-wrap items-center gap-5">
            @if ($avatar)
                <img src="{{ $avatar }}" alt="" class="h-20 w-20 flex-none rounded-full border-2 border-navy object-cover md:h-24 md:w-24">
            @else
                <span class="flex h-20 w-20 flex-none items-center justify-center rounded-full border-2 border-navy bg-gradient-to-br from-peach to-peach-deep text-3xl font-extrabold md:h-24 md:w-24" aria-hidden="true">{{ mb_substr($user->name, 0, 1) }}</span>
            @endif
            <div class="min-w-0 flex-1">
                <div class="font-hand text-xl text-red">নমস্কার</div>
                <h1 class="break-words font-display text-3xl font-extrabold leading-tight md:text-4xl">{{ $author?->publicName() ?: $user->name }}</h1>
                <div class="mt-1 flex flex-wrap items-center gap-2 text-sm text-ink2">
                    <span class="break-all">{{ $user->email }}</span>
                    @if ($user->hasVerifiedEmail())<span class="rounded-full bg-sky-tint px-2 py-0.5 font-bold text-sky-night">✓ যাচাই করা</span>@endif
                    @if ($twoFactorOn)<span class="rounded-full bg-sky-tint px-2 py-0.5 font-bold text-sky-night">🔒 দুই-ধাপের সুরক্ষা</span>@endif
                </div>
            </div>
            <div class="grid w-full gap-2 sm:flex sm:w-auto">
                <a href="{{ Url::to('/submit') }}" class="flex h-12 items-center justify-center whitespace-nowrap rounded-full bg-red px-6 font-bold text-white">+ নতুন লেখা পাঠান</a>
                @if ($hasProfile)<a href="{{ $author->url() }}" class="flex h-12 items-center justify-center whitespace-nowrap rounded-full border-2 border-navy px-5 font-bold">আমার লেখক-পাতা দেখুন</a>@endif
            </div>
        </div>

        @if (session('status'))
            <div role="status" class="mt-6 rounded-xl border-2 border-sky bg-sky-tint p-4 font-semibold text-sky-night">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div role="alert" class="mt-6 rounded-xl border-2 border-red bg-peach-light p-4 font-semibold text-red-dark">@foreach ($errors->all() as $e)<p>{{ $e }}</p>@endforeach</div>
        @endif
        @if (! $twoFactorOn)
            {{-- D59: 2FA is optional, but we ask everyone kindly. --}}
            <a href="#security" class="mt-6 flex items-center gap-4 rounded-2xl border-2 border-dashed border-peach-deep bg-peach-light p-4 hover:bg-peach">
                <span class="text-3xl" aria-hidden="true">🔐</span>
                <span><strong class="block">অ্যাকাউন্টটি আরও নিরাপদ করুন</strong><span class="text-sm text-ink2">দুই-ধাপের যাচাই চালু করলে কেউ পাসওয়ার্ড জেনে গেলেও আপনার ফোন ছাড়া ঢুকতে পারবে না। দুই মিনিট লাগে।</span></span>
                <span class="ml-auto font-bold text-red" aria-hidden="true">→</span>
            </a>
        @endif

        {{-- At a glance --}}
        <dl class="mt-6 grid grid-cols-3 gap-3">
            @foreach ([['প্রকাশিত', $published], ['পড়া হচ্ছে', $inReview], ['শংসাপত্র', $certificates->count()]] as [$k, $v])
                <div class="rounded-2xl border-2 border-navy bg-white p-3 text-center">
                    <dd class="font-display text-3xl font-extrabold">{{ Bn::num((string) $v) }}</dd>
                    <dt class="text-sm font-semibold text-ink2">{{ $k }}</dt>
                </div>
            @endforeach
        </dl>
    </section>

    {{-- Tabs (plain anchors: work without JavaScript) --}}
    <nav aria-label="অ্যাকাউন্ট" class="sticky top-0 z-30 mt-8 border-y-2 border-navy bg-paper/95 backdrop-blur">
        <div class="mx-auto flex max-w-[1000px] gap-1 overflow-x-auto px-3">
            @foreach (['writing' => 'আমার লেখা', 'reading' => 'পড়ার তালিকা', 'profile' => 'প্রোফাইল', 'security' => 'নিরাপত্তা', 'letters' => 'চিঠি'] as $id => $label)
                <a href="#{{ $id }}" class="flex h-12 flex-none items-center rounded-full px-4 font-bold hover:bg-peach-light">{{ $label }}</a>
            @endforeach
        </div>
    </nav>

    <div class="mx-auto max-w-[1000px] space-y-14 px-5 pb-20 pt-8">
        {{-- আমার লেখা --}}
        <section id="writing" class="scroll-mt-20" aria-labelledby="writing-h">
            <h2 id="writing-h" class="font-display text-3xl font-extrabold">আমার লেখা</h2>
            @if ($posts->isEmpty())
                <div class="mt-5 rounded-3xl border-2 border-dashed border-navy p-8 text-center">
                    <p class="font-serif text-xl">এখনও কোনো লেখা পাঠাননি।</p>
                    <p class="mt-2 text-ink2">প্রথম লেখাটি পাঠানোর আগে <a href="{{ Url::to('/submissions') }}" class="underline">লেখা পাঠানোর নিয়ম</a> একবার দেখে নিন।</p>
                    <a href="{{ Url::to('/submit') }}" class="mt-5 inline-flex h-12 items-center rounded-full bg-red px-6 font-bold text-white">প্রথম লেখা পাঠান</a>
                </div>
            @endif

            @if ($act->isNotEmpty())
                <h3 class="mt-6 text-sm font-extrabold uppercase tracking-wide text-red">আপনার দেখার অপেক্ষায়</h3>
                <ul class="mt-3 space-y-3">
                    @foreach ($act as $p)
                        @php([$label, $hint] = $S[$p->status] ?? [$p->status, ''])
                        <li class="rounded-2xl border-2 {{ $p->status === 'draft' ? 'border-rule bg-white' : 'border-red bg-peach-light' }} p-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="font-serif text-lg font-bold leading-snug">{{ $p->title }}</div>
                                    <div class="text-sm text-ink2">{{ $p->section?->name_bn }} · {{ Bn::date($p->updated_at) }} · {{ $hint }}</div>
                                </div>
                                <a href="{{ Url::to('/submit/'.$p->id) }}" class="flex h-11 flex-none items-center rounded-full {{ $p->status === 'draft' ? 'border-2 border-navy' : 'bg-red text-white' }} px-5 font-bold">
                                    {{ ['changes' => 'ঠিক করুন', 'confirm' => 'দেখে সম্মতি দিন', 'draft' => 'লিখতে থাকুন'][$p->status] }}</a>
                            </div>
                            @if ($p->status === 'changes' && $p->review_note)
                                <div class="mt-3 rounded-xl bg-white p-3 text-sm"><strong>সম্পাদকের মন্তব্য:</strong> {{ $p->review_note }}</div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($rest->isNotEmpty())
                <h3 class="mt-8 text-sm font-extrabold uppercase tracking-wide text-ink2">সব লেখা</h3>
                <ul class="mt-3 divide-y divide-rule rounded-2xl border-2 border-navy bg-white">
                    @foreach ($rest as $p)
                        @php([$label, $hint] = $S[$p->status] ?? [$p->status, ''])
                        @php($step = ['pending' => 2, 'scheduled' => 3, 'published' => 4][$p->status] ?? 0)
                        <li class="flex flex-wrap items-center justify-between gap-3 p-4">
                            <div class="min-w-0 flex-1">
                                <div class="font-serif text-lg font-bold leading-snug">{{ $p->title }}</div>
                                <div class="text-sm text-ink2">{{ $p->section?->name_bn }} · {{ Bn::date($p->published_at ?? $p->submitted_at ?? $p->updated_at) }}</div>
                                @if ($step)
                                    <ol class="mt-2 flex flex-wrap items-center gap-1 text-xs font-bold" aria-label="অবস্থা">
                                        @foreach (['জমা', 'পড়া হচ্ছে', 'মনোনীত', 'প্রকাশিত'] as $i => $stage)
                                            <li class="flex items-center gap-1 {{ $i + 1 <= $step ? 'text-sky-night' : 'text-ink2 opacity-60' }}" @if ($i + 1 === $step) aria-current="step" @endif>
                                                <span class="inline-block h-2.5 w-2.5 rounded-full {{ $i + 1 <= $step ? 'bg-sky-deep' : 'bg-rule' }}"></span>{{ $stage }}
                                                @if (! $loop->last)<span class="mx-0.5 opacity-50">—</span>@endif
                                            </li>
                                        @endforeach
                                    </ol>
                                @endif
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="rounded-full px-3 py-1 text-sm font-bold {{ in_array($p->status, ['published', 'scheduled']) ? 'bg-sky-tint text-sky-night' : 'bg-peach-light' }}">{{ $label }}</span>
                                @if ($p->status === 'published')<a href="{{ $p->url() }}" class="font-bold text-sky-deep underline">দেখুন</a>@endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($certificates->isNotEmpty())
                <h3 class="mt-8 text-sm font-extrabold uppercase tracking-wide text-ink2">আমার শংসাপত্র</h3>
                <ul class="mt-3 grid gap-3 sm:grid-cols-2">
                    @foreach ($certificates as $c)
                        <li><a href="{{ $c->verifyUrl() }}" class="block rounded-2xl border-2 border-navy bg-white p-4 hover:bg-peach-light">
                            <span class="block text-2xl" aria-hidden="true">🏅</span>
                            <span class="mt-1 block font-serif font-bold">{{ $c->title_snapshot }}</span>
                            <span class="text-sm text-ink2">{{ $c->issue_label ?: ($c->kind === 'contest' ? 'প্রতিযোগিতা' : 'প্রকাশ') }} · {{ Bn::date($c->issued_at) }}</span>
                        </a></li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- পড়ার তালিকা ও অনুসরণ (D64) --}}
        <section id="reading" class="scroll-mt-20" aria-labelledby="reading-h">
            <h2 id="reading-h" class="font-display text-3xl font-extrabold">পড়ার তালিকা</h2>
            @if ($saved->isEmpty())
                <p class="mt-3 text-ink2">যে লেখা পরে পড়তে চান, লেখার নিচে "পরে পড়ব" চাপলে এখানে জমা থাকবে।</p>
            @else
                <ul class="mt-4 grid gap-3 sm:grid-cols-2">
                    @foreach ($saved as $p)
                        <li><a href="{{ $p->url() }}" class="block h-full rounded-2xl border-2 border-navy bg-white p-4 hover:bg-peach-light">
                            <span class="block font-serif text-lg font-bold leading-snug">{{ $p->title }}</span>
                            <span class="text-sm text-ink2">{{ $p->authors->first()?->publicName() }} · {{ $p->section?->name_bn }}</span></a></li>
                    @endforeach
                </ul>
            @endif
            <h3 class="mt-8 text-sm font-extrabold uppercase tracking-wide text-ink2">যাঁদের অনুসরণ করছেন</h3>
            @if ($following->isEmpty())
                <p class="mt-2 text-ink2">পছন্দের লেখকের পাতায় "অনুসরণ করুন" চাপলে তাঁর নতুন লেখা প্রকাশ হলেই ই-মেলে জানাব।</p>
            @else
                <ul class="mt-3 flex flex-wrap gap-2">
                    @foreach ($following as $f)
                        <li><a href="{{ $f->url() }}" class="flex h-11 items-center gap-2 rounded-full border-2 border-navy bg-white px-4 font-bold hover:bg-peach-light">
                            @if ($f->avatar_path)<img src="{{ $f->avatar_path }}" alt="" class="h-7 w-7 rounded-full object-cover">@endif{{ $f->publicName() }}</a></li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- প্রোফাইল --}}
        <section id="profile" class="scroll-mt-20" aria-labelledby="profile-h">
            <h2 id="profile-h" class="font-display text-3xl font-extrabold">লেখক-প্রোফাইল</h2>
            <p class="mt-1 text-ink2">আপনার লেখক-পাতায় পাঠকেরা যা দেখবেন: ছবি, প্রচ্ছদ, পরিচিতি।</p>
            <form method="POST" action="{{ Url::to('/account/profile') }}" enctype="multipart/form-data" class="mt-5 overflow-hidden rounded-3xl border-2 border-navy bg-white">
                @csrf
                {{-- Live preview of cover + photo --}}
                <div class="relative h-36 bg-sky md:h-48">
                    <img data-preview="cover" src="{{ $author?->cover_path }}" alt="" class="{{ $author?->cover_path ? '' : 'hidden' }} h-full w-full object-cover">
                    @unless ($author?->cover_path)<div class="halftone absolute inset-0" aria-hidden="true"></div>@endunless
                    <label class="absolute right-3 top-3 flex h-10 cursor-pointer items-center rounded-full bg-white/95 px-4 text-sm font-bold shadow">
                        প্রচ্ছদ বদলান<input type="file" name="cover" accept="image/jpeg,image/png,image/webp" data-preview-input="cover" class="sr-only"></label>
                </div>
                <div class="px-5 pb-5">
                    <div class="-mt-12 flex items-end gap-4">
                        <div class="relative">
                            <img data-preview="avatar" src="{{ $avatar }}" alt="" class="{{ $avatar ? '' : 'hidden' }} h-24 w-24 rounded-full border-4 border-white object-cover outline outline-2 outline-navy">
                            @unless ($avatar)<span data-preview-fallback="avatar" class="flex h-24 w-24 items-center justify-center rounded-full border-4 border-white bg-gradient-to-br from-peach to-peach-deep text-4xl font-extrabold outline outline-2 outline-navy" aria-hidden="true">{{ mb_substr($user->name, 0, 1) }}</span>@endunless
                        </div>
                        <label class="mb-1 flex h-10 cursor-pointer items-center rounded-full border-2 border-navy px-4 text-sm font-bold">
                            ছবি দিন<input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" data-preview-input="avatar" class="sr-only"></label>
                    </div>
                    <p class="mt-2 text-xs text-ink2">ছবি: বর্গাকার ভালো (অন্তত ২৪০×২৪০)। প্রচ্ছদ: চওড়া ছবি (৩:১, অন্তত ৭৫০ পিক্সেল)। JPG/PNG/WebP, ৮ MB পর্যন্ত।
                        @if ($avatar)<label class="ml-2 inline-flex items-center gap-1"><input type="checkbox" name="remove_avatar" value="1"> ছবি সরান</label>@endif
                        @if ($author?->cover_path)<label class="ml-2 inline-flex items-center gap-1"><input type="checkbox" name="remove_cover" value="1"> প্রচ্ছদ সরান</label>@endif
                    </p>

                    <div class="mt-5 grid gap-x-5 sm:grid-cols-2">
                        <x-field name="name" label="ছাপার নাম" :value="$user->name" maxlength="80" required hint="লেখার সঙ্গে এই নাম ছাপা হয়।" />
                        <x-field name="name_bn" label="বাংলায় নাম (ঐচ্ছিক)" :value="$author?->display_name_bn" maxlength="80" hint="থাকলে লেখক-পাতায় এটাই দেখাবে।" />
                        <x-field name="city" label="শহর (ঐচ্ছিক)" :value="$author?->city" maxlength="60" />
                        <x-field name="website" type="url" label="ওয়েবসাইট (ঐচ্ছিক)" :value="$author?->links['website'] ?? null" placeholder="https://" />
                        <x-field name="facebook" type="url" label="ফেসবুক (ঐচ্ছিক)" :value="$author?->links['facebook'] ?? null" placeholder="https://facebook.com/…" />
                        <x-field name="instagram" type="url" label="ইনস্টাগ্রাম (ঐচ্ছিক)" :value="$author?->links['instagram'] ?? null" placeholder="https://instagram.com/…" />
                    </div>
                    <label class="block">
                        <span class="mb-1 block font-bold">নিজের কথা (ঐচ্ছিক)</span>
                        <textarea name="bio" rows="4" maxlength="1000" class="block w-full rounded-xl border-2 border-navy bg-white p-3 text-lg" placeholder="দু-চার লাইনে নিজের কথা: কী লেখেন, কোথা থেকে…">{{ old('bio', $author?->bio) }}</textarea>
                        <span class="mt-1 block text-sm text-ink2">১০০০ অক্ষর পর্যন্ত। সাধারণ লেখা; লিংক উপরের ঘরে দিন।</span>
                    </label>
                    <button class="mt-5 h-12 rounded-full bg-red px-8 font-bold text-white">প্রোফাইল রাখুন</button>
                </div>
            </form>
        </section>

        {{-- নিরাপত্তা --}}
        <section id="security" class="scroll-mt-20" aria-labelledby="security-h">
            <h2 id="security-h" class="font-display text-3xl font-extrabold">নিরাপত্তা</h2>
            <div class="mt-5 grid gap-5 md:grid-cols-2">
                <div class="rounded-3xl border-2 border-navy bg-white p-5">
                    <h3 class="text-xl font-extrabold">দুই-ধাপের যাচাই</h3>
                    @if ($twoFactorOn)
                        <p class="mt-2"><span class="rounded-full bg-sky-tint px-3 py-1 font-bold text-sky-night">✓ চালু আছে</span></p>
                        <p class="mt-3 text-ink2">লগ-ইনের সময় পাসওয়ার্ডের পরে অ্যাপের কোডও লাগবে।</p>
                        @if (session('recovery_codes'))
                            <div class="mt-4 rounded-2xl border-2 border-red bg-peach-light p-4">
                                <strong>উদ্ধার-কোডগুলো এখনই লিখে রাখুন</strong>
                                <p class="text-sm">ফোন হারালে এর একটি দিয়ে ঢোকা যাবে (প্রতিটি একবার)। এগুলো আর দেখানো হবে না।</p>
                                <ul class="mt-2 grid grid-cols-1 gap-1 font-mono text-sm sm:grid-cols-2">@foreach (session('recovery_codes') as $code)<li>{{ $code }}</li>@endforeach</ul>
                            </div>
                        @endif
                        @unless ($user->hasRole(\App\Enums\Role::Editor))
                            <details class="mt-4"><summary class="cursor-pointer text-sm font-bold text-ink2">বন্ধ করতে চান?</summary>
                                <form method="POST" action="{{ Url::to('/account/2fa/disable') }}" class="mt-3">@csrf
                                    <x-field name="current_password" type="password" label="পাসওয়ার্ড দিয়ে নিশ্চিত করুন" autocomplete="current-password" required />
                                    <button class="h-11 rounded-full border-2 border-red px-5 font-bold text-red">বন্ধ করুন</button>
                                </form>
                            </details>
                        @endunless
                    @elseif ($setup)
                        <ol class="mt-3 list-decimal space-y-2 pl-5">
                            <li>ফোনে একটি অথেনটিকেটর অ্যাপ খুলুন (Google Authenticator, Microsoft Authenticator বা 2FAS)।</li>
                            <li>এই QR কোডটি স্ক্যান করুন।</li>
                        </ol>
                        @if ($setup['qr'])<img src="{{ $setup['qr'] }}" alt="QR কোড" class="mt-3 h-44 w-44 rounded-xl border-2 border-navy bg-white p-2">@endif
                        <p class="mt-2 text-sm text-ink2">স্ক্যান না হলে হাতে লিখুন: <code class="break-all rounded bg-peach-light px-1 font-mono">{{ $setup['secret'] }}</code></p>
                        <form method="POST" action="{{ Url::to('/account/2fa/confirm') }}" class="mt-4">@csrf
                            <x-field name="code" label="৩. অ্যাপে দেখানো ৬ অঙ্কের কোড" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required />
                            <button class="h-11 rounded-full bg-red px-6 font-bold text-white">চালু করুন</button>
                        </form>
                    @else
                        <p class="mt-2 text-ink2">ঐচ্ছিক, তবে আমরা জোর দিয়ে বলি। কেউ আপনার পাসওয়ার্ড জেনে গেলেও আপনার ফোনের কোড ছাড়া ঢুকতে পারবে না।</p>
                        <form method="POST" action="{{ Url::to('/account/2fa/start') }}" class="mt-4">@csrf
                            <button class="h-11 rounded-full bg-red px-6 font-bold text-white">চালু করুন</button>
                        </form>
                    @endif
                </div>
                <div class="rounded-3xl border-2 border-navy bg-white p-5">
                    <h3 class="text-xl font-extrabold">পাসওয়ার্ড বদলান</h3>
                    <form method="POST" action="{{ Url::to('/account/password') }}" class="mt-3">@csrf
                        <x-field name="current_password" type="password" label="এখনকার পাসওয়ার্ড" autocomplete="current-password" required />
                        <x-field name="password" type="password" label="নতুন পাসওয়ার্ড" autocomplete="new-password" required hint="অন্তত ১০ অক্ষর, অক্ষর আর সংখ্যা দুই-ই।" />
                        <x-field name="password_confirmation" type="password" label="নতুন পাসওয়ার্ড আবার" autocomplete="new-password" required />
                        <button class="h-11 rounded-full border-2 border-navy px-6 font-bold">বদলান</button>
                    </form>
                    <p class="mt-3 text-sm text-ink2">বদলালে অন্য সব ফোন/কম্পিউটার থেকে লগ-আউট হয়ে যাবে।</p>
                </div>
            </div>
        </section>

        {{-- চিঠি --}}
        <section id="letters" class="scroll-mt-20" aria-labelledby="letters-h">
            <h2 id="letters-h" class="font-display text-3xl font-extrabold">চিঠি</h2>
            <form method="POST" action="{{ Url::to('/account/newsletter') }}" class="mt-5 flex flex-wrap items-center justify-between gap-4 rounded-3xl border-2 border-navy bg-white p-5">
                @csrf
                <div class="min-w-0">
                    <strong class="block text-lg">হাতপাখার চিঠি</strong>
                    <span class="text-ink2">সপ্তাহে একটি: নতুন লেখার খবর; মাঝে মাঝে লেখকদের জন্য পরের সংখ্যার বিষয় আর প্রতিযোগিতার খবর।</span>
                    <span class="mt-1 block text-sm font-bold {{ $user->mail_opt_out ? 'text-red' : 'text-sky-night' }}">এখন: {{ $user->mail_opt_out ? 'বন্ধ' : 'চালু' }}</span>
                </div>
                <input type="hidden" name="newsletter" value="{{ $user->mail_opt_out ? 1 : 0 }}">
                <button class="h-11 flex-none rounded-full {{ $user->mail_opt_out ? 'bg-red text-white' : 'border-2 border-navy' }} px-6 font-bold">{{ $user->mail_opt_out ? 'চালু করুন' : 'বন্ধ করুন' }}</button>
            </form>
            <p class="mt-3 text-sm text-ink2">নিজের লেখার খবর (মনোনয়ন, বদলের অনুরোধ) চিঠি বন্ধ থাকলেও আসবে।</p>
        </section>

        <form method="POST" action="{{ Url::to('/logout') }}" class="border-t-2 border-rule pt-6 text-center">@csrf<button class="font-bold text-red underline">লগ-আউট</button></form>
    </div>
</x-layouts.app>
