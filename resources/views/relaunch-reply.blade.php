{{-- D104: the writer's answer to the apology letter. Nothing here publishes anything. --}}
<x-layouts.app :seo="$seo">
    <section class="mx-auto max-w-[720px] px-5 py-12">
        <div class="font-hand text-2xl text-red">হাতপাখা</div>
        <h1 class="font-display text-4xl font-extrabold leading-tight">আপনার পুরনো লেখা</h1>

        @if ($done)
            <div class="mt-6 rounded-2xl border-2 border-navy bg-sky-tint p-5" role="status">
                <p class="font-bold">ধন্যবাদ, আপনার উত্তর পেয়েছি।</p>
                <p class="mt-1">{{ $done === 'yes' ? 'যে লেখা প্রকাশ করতে চেয়েছেন, সম্পাদক সেগুলো পড়ে আপনাকে জানাবেন।' : 'লেখাগুলো সরিয়ে রাখা হয়েছে; প্রকাশ হবে না।' }} চাইলে নিচে উত্তর বদলাতে পারেন।</p>
            </div>
        @endif

        <p class="mt-5 font-serif text-lg leading-relaxed text-ink2">নমস্কার, {{ $author->publicName() }}। অনেক দেরিতে উত্তর দেওয়ার জন্য আবার ক্ষমা চাইছি। প্রতিটি লেখার পাশে জানান, আপনি এখনও এটি হাতপাখায় প্রকাশ করতে চান কি না। "চাই" বললেও সম্পাদক পড়ে তবেই সিদ্ধান্ত নেবেন।</p>

        <form method="POST" action="{{ request()->fullUrl() }}" class="mt-8">
            @csrf
            @if ($pieces->count() > 3)
                <div class="mb-4 flex flex-wrap items-center gap-2 text-sm font-bold">
                    <span>সবগুলো:</span>
                    <button type="button" data-all="yes" class="min-h-11 rounded-full border-2 border-navy px-4 hover:bg-peach-light">প্রকাশ চাই</button>
                    <button type="button" data-all="no" class="min-h-11 rounded-full border-2 border-navy px-4 hover:bg-peach-light">চাই না</button>
                </div>
            @endif
            @error('answer')<p class="mb-3 font-semibold text-red" role="alert">প্রতিটি লেখার জন্য একটি উত্তর বেছে নিন।</p>@enderror
            <ul class="divide-y divide-rule border-y border-rule">
                @foreach ($pieces as $p)
                    @php
                        $current = old('answer.'.$p->id, in_array($p->relaunch_status, ['yes', 'no'], true) ? $p->relaunch_status : null);
                    @endphp
                    <li class="py-4">
                        <fieldset>
                            <legend class="font-serif text-xl font-bold">{{ $p->title }}</legend>
                            @if ($p->submitted_at)<div class="text-sm text-ink2">পাঠিয়েছিলেন {{ \App\Support\Bn::date($p->submitted_at) }}</div>@endif
                            <div class="mt-2 flex flex-wrap gap-x-5 gap-y-1">
                                <label class="flex min-h-11 items-center gap-2"><input type="radio" name="answer[{{ $p->id }}]" value="yes" class="h-6 w-6 accent-red" @checked($current === 'yes') required> প্রকাশ করতে চাই</label>
                                <label class="flex min-h-11 items-center gap-2"><input type="radio" name="answer[{{ $p->id }}]" value="no" class="h-6 w-6 accent-red" @checked($current === 'no')> চাই না</label>
                                <label class="flex min-h-11 items-center gap-2 text-ink2"><input type="checkbox" name="elsewhere[{{ $p->id }}]" value="1" @checked($p->relaunch_elsewhere)> অন্য কোথাও আগে প্রকাশিত হয়েছে</label>
                            </div>
                        </fieldset>
                    </li>
                @endforeach
            </ul>
            <button class="mt-6 h-12 rounded-full bg-red px-8 font-bold text-white">উত্তর পাঠান</button>
        </form>
    </section>
    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
        document.querySelectorAll('[data-all]').forEach((b) => b.addEventListener('click', () => {
            document.querySelectorAll('input[type=radio][value="' + b.dataset.all + '"]').forEach((r) => { r.checked = true; });
        }));
    </script>
</x-layouts.app>
