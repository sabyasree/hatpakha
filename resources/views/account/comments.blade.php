{{-- D253 (owner: "in the profile they can see the comments and replies, and reply from there too"). Readers' words on
     my pieces, then my own comments that got replies; each thread with a reply box. Plain text, always escaped. --}}
@php
    use App\Support\Bn;
    use App\Support\Url;
@endphp
<x-layouts.app :seo="$seo">
    <x-account-shell tab="comments" :user="$user" :author="$author" :counts="$counts" :two-factor-on="$twoFactorOn" :cert-count="$certCount">
        <h2 class="font-display text-3xl font-extrabold">{{ __('মতামত ও উত্তর') }}</h2>
        <p class="mt-1 text-ink2">{{ __('পাঠকেরা যা লিখেছেন, আর আপনার মতামতের উত্তর। এখান থেকেই উত্তর দিতে পারেন।') }}</p>

        @php
            $isNew = fn ($x) => $x->created_at > $seen && (int) $x->user_id !== (int) $user->id;
            $newTag = '<span class="ml-1 rounded-full bg-red px-2 py-0.5 text-xs font-bold text-white">'.e(__('নতুন')).'</span>';
        @endphp
        @foreach ([[$onMine, __('আপনার লেখায় পাঠকের মতামত'), __('এখনও কেউ আপনার লেখায় মতামত লেখেননি। লেখা প্রকাশিত হলে বন্ধুদের পাঠান: যত পাঠক, তত মতামত।'), true], [$mineAnswered, __('আপনার মতামতের উত্তর'), null, false]] as [$list, $title, $empty, $isWriterList])
            @if ($list->isNotEmpty() || $empty && $writerIds)
                <h3 class="mt-8 font-display text-2xl font-extrabold">{{ $title }}</h3>
                @if ($list->isEmpty())
                    <p class="mt-2 rounded-2xl bg-peach-light p-4">{{ $empty }}</p>
                @endif
                {{-- UX review: one heading a piece, its comments under it --}}
                @foreach ($list->groupBy('post_id') as $group)
                    <h4 class="mt-5 rounded-xl bg-peach-light px-3 py-2 font-serif text-lg font-bold"><a href="{{ $group->first()->post->url() }}" class="underline">“{{ $group->first()->post->title }}”</a></h4>
                    <ol>
                        @foreach ($group as $c)
                            @php
                                $answered = $c->replies->contains(fn ($r) => (int) $r->user_id === (int) $user->id);
                                $failed = (int) old('parent_id') === $c->id && $errors->any();
                            @endphp
                            <li id="comment-{{ $c->id }}" class="scroll-mt-24 border-b border-rule py-5 last:border-0 target:rounded-xl target:border-l-4 target:border-navy target:bg-sky-tint target:px-3">
                                @if ($isNew($c))<p class="mb-1">{!! $newTag !!}</p>@endif
                                @include('partials.comment-one', ['c' => $c, 'isWriter' => in_array($c->user_id, $writerIds, true) && $c->user_id, 'isDesk' => false, 'small' => false])
                                @if ($c->replies->isNotEmpty())
                                    <ol class="ml-6 mt-3 border-l-4 border-peach pl-4">
                                        @foreach ($c->replies as $r)
                                            <li id="comment-{{ $r->id }}" class="scroll-mt-24 py-2">
                                                @if ($isNew($r))<p class="mb-1">{!! $newTag !!}</p>@endif
                                                @include('partials.comment-one', ['c' => $r, 'isWriter' => $c->post->authors()->where('user_id', $r->user_id)->exists() && $r->user_id, 'isDesk' => false, 'small' => true])
                                            </li>
                                        @endforeach
                                    </ol>
                                @endif
                                @if ($isWriterList && ! $answered && (int) (session('reply_status')['to'] ?? 0) !== $c->id)<p class="ml-6 mt-2 text-sm font-semibold text-ink2">{{ __('এখনও উত্তর দেননি') }}</p>@endif
                                @if (is_array(session('reply_status')) && (int) (session('reply_status')['to'] ?? 0) === $c->id)
                                    <p class="ml-6 mt-3 rounded-xl bg-sky-tint p-3 font-semibold" role="status">{{ session('reply_status')['text'] }}</p>
                                @endif
                                <details class="ml-6 mt-2" @if ($failed) open @endif>
                                    <summary class="inline-flex min-h-11 cursor-pointer list-none items-center rounded-full border-2 border-navy px-4 font-bold hover:bg-peach-light">{{ __('উত্তর দিন') }}</summary>
                                    <form method="POST" action="{{ route('comment.store', $c->post) }}" class="mt-3">
                                        @csrf
                                        <input type="hidden" name="parent_id" value="{{ $c->id }}">
                                        <input type="hidden" name="from" value="account">
                                        <input type="hidden" name="started" value="{{ \App\Http\Controllers\CommentController::startedToken() }}">
                                        <p class="mb-2 text-sm text-ink2"><strong class="text-navy">{{ $user->name }}</strong> {{ __('হিসেবে লিখছেন') }}</p>
                                        <label class="block"><span class="sr-only">{{ __('আপনার উত্তর') }}</span>
                                            <textarea name="body" rows="2" required minlength="2" maxlength="3000" placeholder="{{ $c->author_name }}-কে উত্তর লিখুন…" class="block w-full rounded-xl border-2 border-navy bg-paper p-3 text-lg leading-relaxed focus:border-sky-deep focus:bg-white">{{ $failed ? old('body') : '' }}</textarea>
                                        </label>
                                        @if ($failed)@error('body')<p class="mt-2 font-semibold text-red" role="alert">{{ $message }}</p>@enderror @endif
                                        <div class="mt-2 flex flex-wrap items-center gap-3">
                                            <button class="flex h-12 flex-1 items-center justify-center rounded-full bg-red px-6 text-lg font-bold text-white shadow-[3px_3px_0_#13212C] sm:flex-none">{{ __('উত্তর পাঠান') }}</button>
                                            <button type="button" data-close-details class="flex h-12 items-center px-3 font-bold text-ink2 underline">{{ __('বাতিল') }}</button>
                                        </div>
                                    </form>
                                </details>
                            </li>
                        @endforeach
                    </ol>
                @endforeach
            @endif
        @endforeach

        @if ($onMine->isEmpty() && $mineAnswered->isEmpty() && ! $writerIds)
            <p class="mt-6 rounded-2xl bg-peach-light p-4">{{ __('আপনার কোনো মতামতের উত্তর এখনও আসেনি। কোনো লেখা পড়ে মতামত লিখলে, কেউ উত্তর দিলে এখানে দেখতে পাবেন।') }}</p>
        @endif
    </x-account-shell>
</x-layouts.app>
