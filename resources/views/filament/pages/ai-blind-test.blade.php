<x-filament-panels::page>
    @php($reviews = $this->reviews())
    @php($result = $this->result())
    @if ($reviews->isEmpty())
        <x-filament::section>
            এখনও কোনও পরীক্ষা চলেনি। সার্ভারে চালান: <code>php artisan hatpakha:ai-blind-test</code> (খরচের হিসাব আগে দেখাবে, ₹৫০০-এর বেশি হলে চলবে না)।
        </x-filament::section>
    @else
        @if ($result)
            <x-filament::section heading="ফলাফল">
                @foreach ($result['providers'] as $p => $v)
                    <p><strong>{{ $p === 'anthropic' ? 'Claude' : 'OpenAI' }}</strong> ({{ $v['model'] }}): গড় স্কোর {{ $v['avg'] }} / ৫, {{ $v['n'] }}টি রিপোর্ট</p>
                @endforeach
                <p>জোড়ায় জয়: Claude {{ $result['wins']['anthropic'] }} · OpenAI {{ $result['wins']['openai'] }} · সমান {{ $result['wins']['tie'] }}</p>
                <p class="text-sm text-gray-500">বিজয়ী ঠিক করে <code>AI_PROVIDER</code> ENV_FILE-এ বসান (docs/AI.md)।</p>
            </x-filament::section>
        @else
            <p class="text-sm text-gray-500">প্রতিটি রিপোর্টে ১–৫ দিন: ৫ = সম্পাদক হিসেবে সবচেয়ে কাজের। সব দেওয়া হলে কোনটা কে লিখেছে দেখা যাবে। দাম বা গতি নয়, শুধু কাজের মান বিচার করুন।</p>
        @endif

        @foreach ($reviews->groupBy('post_id') as $pair)
            @php($post = $pair->first()->post)
            <x-filament::section :heading="$post?->title" collapsible>
                <details class="mb-4"><summary class="cursor-pointer text-sm font-semibold">লেখাটি পড়ুন</summary>
                    <div class="mt-2 max-h-96 overflow-auto whitespace-pre-line text-sm">{{ trim(html_entity_decode(strip_tags(preg_replace(['~<br\s*/?>~i', '~</p>~i'], ["\n", "\n\n"], (string) $post?->body_html)))) }}</div>
                </details>
                <div class="grid gap-4 md:grid-cols-2">
                    @foreach ($pair->sortBy('blind_label') as $review)
                        <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                            <div class="mb-2 text-lg font-bold">রিপোর্ট {{ $review->blind_label }}@if ($result) <span class="text-sm font-normal">({{ $review->provider === 'anthropic' ? 'Claude' : 'OpenAI' }})</span>@endif</div>
                            {{-- The report view never shows the provider while the test is blind. --}}
                            @include('filament.ai-report', ['review' => $result ? $review : (clone $review)->forceFill(['provider' => '—', 'model' => '—'])])
                            @if ($review->status === 'done')
                                <div class="mt-3 flex items-center gap-2">
                                    <select wire:model="scores.{{ $review->id }}" class="rounded-lg border-gray-300 text-sm dark:bg-white/5">
                                        <option value="">স্কোর…</option>
                                        @foreach ([5, 4, 3, 2, 1] as $n)<option value="{{ $n }}">{{ $n }}</option>@endforeach
                                    </select>
                                    <x-filament::button size="sm" wire:click="score({{ $review->id }})">রাখুন</x-filament::button>
                                    @if ($review->editor_score)<span class="text-sm text-success-600">✓ {{ $review->editor_score }}</span>@endif
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        @endforeach
    @endif
</x-filament-panels::page>
