{{-- A8 (research 2026-11, owner approved): an empty page is never a dead end. Something real to read (the editor's
     pick, the readers' choice, the three newest pieces) and a way to hear about new ones (the weekly letter, the phone).
     $heading: optional line above. Only ids are cached (D18). --}}
@php
    $nextIds = \Illuminate\Support\Facades\Cache::remember('read-next:v1', 600, function () {
        $pick = json_decode((string) \App\Models\Setting::get('home.pick'), true) ?: [];
        $pickId = ! empty($pick['post_id']) ? \App\Models\Post::published()->whereKey($pick['post_id'])->value('id') : null;
        $choiceId = \App\Support\Feelings::latest()?->id;
        $latest = \App\Models\Post::published()->where('noindex', false)
            ->whereNotIn('id', array_filter([$pickId, $choiceId]))
            ->whereDoesntHave('section', fn ($q) => $q->whereIn('slug', ['editorial', 'english']))
            ->latest('published_at')->limit(3)->pluck('id')->all();

        return ['pick' => $pickId, 'choice' => $choiceId, 'latest' => $latest];
    });
    $nextPosts = \App\Models\Post::published()->with(['authors', 'section'])->whereKey(array_filter(array_merge([$nextIds['pick'], $nextIds['choice']], $nextIds['latest'])))->get()->keyBy('id');
    $nextRows = collect([[$nextIds['pick'], 'সম্পাদকের বাছাই'], [$nextIds['choice'], 'পাঠকের পছন্দ']])
        ->concat(collect($nextIds['latest'])->map(fn ($id) => [$id, 'নতুন লেখা']))
        ->filter(fn ($r) => $r[0] && isset($nextPosts[$r[0]]))->values();
@endphp
<section data-read-next class="mt-8 rounded-3xl border-2 border-navy bg-white p-5 sm:p-6" aria-labelledby="read-next-h">
    <h2 id="read-next-h" class="font-display text-2xl font-extrabold">{{ $heading ?? 'ততক্ষণ এগুলো পড়ুন' }}</h2>
    @if ($nextRows->isNotEmpty())
        <ul class="mt-3 divide-y divide-rule">
            @foreach ($nextRows as [$id, $why])
                <li><x-post-row :post="$nextPosts[$id]" :meta="$why.' · '.($nextPosts[$id]->authors->first()?->publicName() ?? '')" /></li>
            @endforeach
        </ul>
    @endif
    <div class="mt-4 flex flex-wrap gap-2">
        <a href="{{ \App\Support\Url::to('/pathoker-pochhondo') }}" class="inline-flex min-h-11 items-center rounded-full border-2 border-navy bg-peach-light px-4 font-bold hover:bg-peach">পাঠকের পছন্দ →</a>
        <a href="{{ \App\Support\Url::to('/archive') }}" class="inline-flex min-h-11 items-center rounded-full border-2 border-navy bg-white px-4 font-bold hover:bg-peach-light">আর্কাইভ: ২০১২ থেকে সব লেখা →</a>
    </div>
    <div class="mt-4"><x-push-button topic="daily" off="🔔 নতুন লেখা এলে ফোনে জানান" on="✓ নতুন লেখা এলে ফোনে জানাব" /></div>
</section>
@guest
    <x-newsletter-signup variant="card" />
@endguest
