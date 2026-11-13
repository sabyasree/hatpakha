{{-- D168: a reviewer reads one piece and gives their own view first; only then the AI's report opens to compare. The
     writer's name stays hidden until the piece is public (owner: blind review). --}}
@php
    use App\Models\PieceReview;
    use App\Support\Bn;
    use App\Support\Url;
    $post = $review->post;
    $done = $review->status === 'done';
    $bodyClass = $post->isPoem() ? 'poem-bn mt-6 text-center'.($post->poemLinesAreParagraphs() ? ' poem-tight' : '') : 'prose-bn mt-6';
    $area = 'mt-1 block w-full rounded-xl border-2 border-navy bg-white p-3 text-lg';
    $aiDecision = ['publish' => 'প্রকাশ করা যায়', 'changes' => 'লেখককে কিছু বদল চাওয়া যায়', 'reject' => 'এবার নয়'];
@endphp
<x-layouts.app :seo="$seo">
    <x-account-shell :tab="$tab" :user="$user" :author="$author" :counts="$counts" :two-factor-on="$twoFactorOn" :cert-count="$certCount">
        <a href="{{ Url::to('/account/review') }}" class="inline-flex min-h-11 items-center font-bold text-sky-night underline">← যাচাইয়ের সব লেখা</a>

        @if ($review->request_note || $review->due_on)
            <div class="mt-3 rounded-2xl bg-peach-light p-4">
                @if ($review->request_note)<p><strong>ডেস্কের অনুরোধ:</strong> {{ $review->request_note }}</p>@endif
                @if ($review->due_on)<p class="mt-1 text-sm">সম্ভব হলে {{ Bn::date($review->due_on) }}-এর মধ্যে।</p>@endif
            </div>
        @endif

        <article class="mt-5 rounded-3xl border-2 border-navy bg-paper px-5 py-6 sm:px-8" lang="{{ $post->language }}">
            <p class="text-sm font-bold text-ink2">{{ $post->section?->name_bn }}</p>
            <h2 class="mt-1 font-display text-3xl font-extrabold leading-tight">{{ $post->title }}</h2>
            <p class="mt-2 text-sm text-ink2">
                @if ($review->showsWriter())
                    {{ $post->authors->map->publicName()->implode(', ') }}
                @else
                    লেখকের নাম দেখানো হচ্ছে না, যাতে শুধু লেখাটি দেখে মত দিতে পারেন।
                @endif
            </p>
            {{-- body_html is sanitised on save (App\Support\HtmlSanitizer) — the only raw output allowed. --}}
            <div class="{{ $bodyClass }}">{!! $body !!}</div>
        </article>

        @if (! $done)
            <form method="POST" action="{{ Url::to('/account/review/'.$review->id) }}" class="mt-8" id="feedback">@csrf
                <h2 class="font-display text-2xl font-extrabold">আপনার মত</h2>
                <p class="mt-1 text-ink2">যেমন মনে হয়েছে তেমন লিখুন, সাহিত্যের ভাষায় লেখার দরকার নেই। পাঠানোর পরে চাইলে এআই কী বলেছে মিলিয়ে দেখতে পারবেন।</p>

                <fieldset class="mt-5">
                    <legend class="text-lg font-bold">সব মিলিয়ে কী মনে হল?</legend>
                    <div class="mt-2 grid gap-2 sm:grid-cols-3">
                        @foreach (PieceReview::VERDICTS as $key => $label)
                            <label class="flex min-h-14 cursor-pointer items-center gap-3 rounded-2xl border-2 border-navy bg-white px-4 font-bold has-[:checked]:bg-navy has-[:checked]:text-white">
                                <input type="radio" name="verdict" value="{{ $key }}" class="h-5 w-5 flex-none accent-red" @checked(old('verdict') === $key) required>{{ $label }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <label class="mt-5 block"><span class="text-lg font-bold">কেমন লাগল?</span> <span class="text-ink2">কোন জায়গাটা মনে থেকে গেল, কোথায় থমকে গেলেন</span>
                    <textarea name="liked" rows="5" required minlength="10" maxlength="4000" class="{{ $area }}">{{ old('liked') }}</textarea></label>

                <label class="mt-5 block"><span class="text-lg font-bold">কী বদলালে আরও ভালো হয়?</span> <span class="text-ink2">(ঐচ্ছিক)</span>
                    <textarea name="improve" rows="4" maxlength="4000" class="{{ $area }}">{{ old('improve') }}</textarea></label>

                <fieldset class="mt-5">
                    <legend class="text-lg font-bold">পাঁচে কত দেবেন? <span class="font-normal text-ink2">(ঐচ্ছিক)</span></legend>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach (range(1, 5) as $n)
                            <label class="flex h-12 w-12 cursor-pointer items-center justify-center rounded-full border-2 border-navy bg-white text-lg font-bold has-[:checked]:bg-navy has-[:checked]:text-white">
                                <input type="radio" name="rating" value="{{ $n }}" class="sr-only" @checked((int) old('rating') === $n)>{{ Bn::num((string) $n) }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <button class="mt-6 h-12 w-full rounded-full bg-red px-8 text-lg font-bold text-white shadow-[3px_3px_0_#13212C] sm:w-auto">মত পাঠান</button>
            </form>
        @else
            <section class="mt-8 rounded-3xl border-2 border-navy bg-white p-5">
                <h2 class="font-display text-2xl font-extrabold">আপনার মত</h2>
                <p class="mt-2"><strong>{{ PieceReview::VERDICTS[$review->verdict] ?? '' }}</strong>@if ($review->rating) · পাঁচে {{ Bn::num((string) $review->rating) }}@endif</p>
                <p class="mt-2 whitespace-pre-line">{{ $review->liked }}</p>
                @if ($review->improve)<p class="mt-3 font-bold">আরও ভালো হতে পারে:</p><p class="whitespace-pre-line">{{ $review->improve }}</p>@endif
                <p class="mt-3 text-sm text-ink2">পাঠিয়েছেন {{ Bn::date($review->submitted_at) }}।</p>
            </section>

            <section id="ai" class="mt-6 scroll-mt-24 rounded-3xl border-2 border-dashed border-navy p-5">
                <h2 class="font-display text-2xl font-extrabold">এআই কী বলেছে</h2>
                <p class="mt-1 text-sm text-ink2">হাতপাখার এআই সম্পাদকের পাঠ। আপনার মতের সঙ্গে মিলিয়ে দেখুন; মত বদলানোর দরকার নেই।</p>
                @if ($ai)
                    <div class="mt-3 flex flex-wrap gap-2">
                        @isset($ai['score'])<span class="rounded-full bg-navy px-3 py-1 font-bold text-white">মান: দশে {{ Bn::num((string) $ai['score']) }}</span>@endisset
                        @if (isset($ai['decision'], $aiDecision[$ai['decision']]))<span class="rounded-full border-2 border-navy px-3 py-1 font-bold">প্রস্তাব: {{ $aiDecision[$ai['decision']] }}</span>@endif
                    </div>
                    @isset($ai['summary'])<p class="mt-3">{{ $ai['summary'] }}</p>@endisset
                    @isset($ai['strengths'])
                        <p class="mt-3 font-bold">ভালো দিক</p>
                        <ul class="list-disc ps-6">@foreach ($ai['strengths'] as $s)<li>{{ $s }}</li>@endforeach</ul>
                    @endisset
                    @isset($ai['suggestions'])
                        <p class="mt-3 font-bold">পরামর্শ</p>
                        <ol class="list-decimal ps-6">@foreach ($ai['suggestions'] as $s)<li>{{ $s }}</li>@endforeach</ol>
                    @endisset
                @else
                    <p class="mt-3">এই লেখার এআই রিপোর্ট নেই।</p>
                @endif

                <form method="POST" action="{{ Url::to('/account/review/'.$review->id.'/after-ai') }}" class="mt-5">@csrf
                    <label class="block"><span class="text-lg font-bold">এআই-এর পাঠ পড়ে কিছু বলার আছে?</span> <span class="text-ink2">(ঐচ্ছিক; কোথায় একমত, কোথায় নন)</span>
                        <textarea name="after_ai" rows="3" maxlength="2000" class="{{ $area }}">{{ old('after_ai', $review->after_ai) }}</textarea></label>
                    <button class="mt-3 h-12 rounded-full bg-navy px-6 font-bold text-white">রাখুন</button>
                </form>
            </section>
        @endif
    </x-account-shell>
</x-layouts.app>
