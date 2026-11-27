{{-- D328: the weekly quiz (read, change, approve) and the teams (take one off the boards). Logo palette only. --}}
@php($n = fn ($v) => \App\Support\Bn::num((string) $v))
<x-filament-panels::page>
    <style>
        .hp-g-box { border: 1px solid rgba(127,127,127,.25); border-radius: 1rem; padding: 1rem 1.1rem; }
        .hp-g-q { border: 1px solid rgba(127,127,127,.25); border-radius: .8rem; padding: .8rem; margin-top: .7rem; }
        .hp-g-in { width: 100%; min-height: 2.6rem; border-radius: .6rem; border: 1px solid rgba(127,127,127,.4); padding: .3rem .6rem; background: transparent; }
        .hp-g-row { display: grid; grid-template-columns: auto 1fr; gap: .4rem .6rem; align-items: center; margin-top: .4rem; }
        .hp-g-btn { display: inline-flex; align-items: center; min-height: 2.75rem; padding: 0 .9rem; border-radius: 999px; font-weight: 700; border: 2px solid #13212C; background: #fff; color: #13212C; cursor: pointer; }
        .hp-g-red { background: #CB1B32; border-color: #CB1B32; color: #fff; }
        .hp-g-tools { display: flex; flex-wrap: wrap; gap: .4rem; margin-top: .7rem; }
        .hp-g-badge { display: inline-block; font-size: .78rem; font-weight: 700; padding: .15rem .6rem; border-radius: 999px; background: #FBEBD8; color: #13212C; }
        .hp-g-ok { background: #DDF1FA; color: #12304A; }
        .hp-g-table td, .hp-g-table th { padding: .45rem .5rem; border-bottom: 1px solid rgba(127,127,127,.2); text-align: left; }
    </style>

    <div class="hp-g-box">
        <h2 style="font-size:1.2rem;font-weight:700">{{ __('পত্রিকা-কুইজ') }}</h2>
        <p style="margin-top:.3rem">{{ __('কুইজ নিজে থেকে চলে: প্রতি শনিবার এআই পাঁচটা প্রশ্ন লেখে, আরেকবার লেখার সঙ্গে মিলিয়ে যাচাই করে, যাচাইয়ে টেকা প্রশ্ন নিয়ে রবিবার থেকে কুইজ চালু হয়। আপনাকে কিছু করতে হয় না। চাইলে এখানে পড়ে দেখতে, বদলাতে বা সরিয়ে নিতে পারেন।') }}</p>
    </div>

    @foreach ($quizzes as $quiz)
        @php($w = $quiz['week'])
        <div class="hp-g-box" wire:key="quiz-{{ $w }}">
            <p style="font-weight:700;font-size:1.1rem">
                {{ $quiz['current'] ? __('এই সপ্তাহ') : __('পরের সপ্তাহ') }}: {{ \App\Support\Bn::date(\Illuminate\Support\Carbon::parse($w)) }} {{ __('থেকে') }}
                <span class="hp-g-badge {{ $quiz['status'] === 'approved' ? 'hp-g-ok' : '' }}">{{ ['approved' => __('অনুমোদিত'), 'draft' => __('খসড়া'), 'none' => __('এখনও নেই')][$quiz['status']] }}</span>
            </p>
            @if ($quiz['note'])<p style="margin-top:.3rem;color:#CB1B32;font-weight:700">{{ $quiz['note'] }}</p>@endif
            @if (! $pieces[$w])<p style="margin-top:.3rem">{{ __('এই সপ্তাহের জন্য গত সাত দিনে কোনো লেখা বেরোয়নি; প্রশ্ন নিজে লিখতে পারেন, লেখা না বেছে।') }}</p>@endif

            @foreach ($edit[$w] ?? [] as $i => $q)
                <div class="hp-g-q" wire:key="q-{{ $w }}-{{ $i }}">
                    <div style="display:flex;justify-content:space-between;align-items:center"><b>{{ __('প্রশ্ন') }} {{ $n($i + 1) }}</b>
                        <button type="button" class="hp-g-btn" wire:click="removeQuestion('{{ $w }}', {{ $i }})">{{ __('বাদ দিন') }}</button></div>
                    <textarea class="hp-g-in" style="margin-top:.4rem" rows="2" wire:model="edit.{{ $w }}.{{ $i }}.q" aria-label="{{ __('প্রশ্ন') }}"></textarea>
                    <div class="hp-g-row">
                        @foreach ([0, 1, 2, 3] as $k)
                            <label style="display:flex;align-items:center;gap:.3rem;min-height:2.6rem"><input type="radio" value="{{ $k }}" wire:model="edit.{{ $w }}.{{ $i }}.answer"> {{ __('ঠিক') }}</label>
                            <input class="hp-g-in" wire:model="edit.{{ $w }}.{{ $i }}.options.{{ $k }}" aria-label="{{ __('উত্তর') }} {{ $n($k + 1) }}">
                        @endforeach
                    </div>
                    <div class="hp-g-row">
                        <span>{{ __('লেখা') }}</span>
                        <select class="hp-g-in" wire:model="edit.{{ $w }}.{{ $i }}.post_id">
                            <option value="">{{ __('কোনোটা নয়') }}</option>
                            @foreach ($pieces[$w] as $id => $title)<option value="{{ $id }}">{{ $title }}</option>@endforeach
                        </select>
                        <span>{{ __('কোথায় আছে') }}</span>
                        <input class="hp-g-in" wire:model="edit.{{ $w }}.{{ $i }}.why" aria-label="{{ __('উত্তর কোথায়') }}">
                    </div>
                </div>
            @endforeach
            <div class="hp-g-tools">
                @if (count($edit[$w] ?? []) < \App\Support\WeeklyQuiz::QUESTIONS)<button type="button" class="hp-g-btn" wire:click="addQuestion('{{ $w }}')">{{ __('প্রশ্ন যোগ করুন') }}</button>@endif
                <button type="button" class="hp-g-btn" wire:click="save('{{ $w }}')">{{ __('রাখুন') }}</button>
                @if ($quiz['status'] === 'approved')
                    <button type="button" class="hp-g-btn" wire:click="save('{{ $w }}', true)">{{ __('বদল রেখে অনুমোদিত রাখুন') }}</button>
                    <button type="button" class="hp-g-btn" wire:click="unapprove('{{ $w }}')" wire:confirm="{{ __('কুইজটা পাঠকদের কাছ থেকে সরিয়ে খসড়ায় ফেরাবেন?') }}">{{ __('খসড়ায় ফেরান') }}</button>
                @else
                    <button type="button" class="hp-g-btn hp-g-red" wire:click="save('{{ $w }}', true)">{{ __('অনুমোদন') }}</button>
                @endif
                @if ($quiz['status'] !== 'approved')<button type="button" class="hp-g-btn" wire:click="draft('{{ $w }}')" wire:confirm="{{ __('এআই নতুন করে প্রশ্ন লিখবে; এখনকার খসড়া মুছে যাবে। চালাবেন?') }}">{{ __('এআই দিয়ে নতুন খসড়া') }}</button>@endif
            </div>
        </div>
    @endforeach

    <div class="hp-g-box">
        <h2 style="font-size:1.2rem;font-weight:700">{{ __('পাড়ার দল') }}</h2>
        <p style="margin-top:.3rem">{{ __('পাঠকেরা নিজেরা দল বানান। কোনো নাম ঠিক না লাগলে "তালিকা থেকে সরান": দলটা আর কোনো তালিকায় দেখা যাবে না, নতুন কেউ যোগও দিতে পারবেন না।') }}</p>
        @if ($teams->isEmpty())
            <p style="margin-top:.6rem">{{ __('এখনও কোনো দল নেই।') }}</p>
        @else
            <table class="hp-g-table" style="width:100%;margin-top:.6rem">
                <thead><tr><th>{{ __('দল') }}</th><th>{{ __('খেলোয়াড়') }}</th><th>{{ __('এ মাসে') }}</th><th></th></tr></thead>
                <tbody>
                    @foreach ($teams as $t)
                        <tr wire:key="team-{{ $t->id }}">
                            <td><a href="{{ \App\Support\Url::to('/khela/dol/'.$t->code) }}" target="_blank" style="text-decoration:underline">{{ $t->name }}</a>@if ($t->hidden_at) <span class="hp-g-badge">{{ __('সরানো') }}</span>@endif</td>
                            <td>{{ $n($counts[$t->id] ?? 0) }}</td>
                            <td>{{ isset($ranks[$t->id]) ? $n($ranks[$t->id]['rank']).' · '.$n(number_format($ranks[$t->id]['points'])) : '—' }}</td>
                            <td>@if ($t->hidden_at)<button type="button" class="hp-g-btn" wire:click="hideTeam({{ $t->id }}, false)">{{ __('ফিরিয়ে আনুন') }}</button>
                                @else<button type="button" class="hp-g-btn" wire:click="hideTeam({{ $t->id }})" wire:confirm="{{ __('দলটা তালিকা থেকে সরাবেন?') }}">{{ __('তালিকা থেকে সরান') }}</button>@endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</x-filament-panels::page>
