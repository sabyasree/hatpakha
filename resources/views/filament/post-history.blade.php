{{--
  D171: every time the piece was sent in or sent back, newest first, with what changed since the time before.
  Inline styles: /desk ships only Filament's CSS. Notes are escaped; the diff is built escaped by TextDiff.
  $post: Post · $open: how many of the newest steps start opened (default 1)
--}}
@php
    use App\Models\PostVersion;
    use App\Support\Bn;
    $versions = $post->versions()->with('user')->get();
    $open = $open ?? 1;
    $box = 'border:1px solid rgba(127,127,127,.28);border-radius:.85rem;padding:.8rem 1rem;margin-top:.7rem';
@endphp
<div style="font-size:.95rem;line-height:1.75">
    @if ($versions->isEmpty())
        <p>{{ __('এখনও কোনো বদল রাখা নেই। এখন থেকে ডেস্কে লেখা বা শিরোনাম বদলে সেভ করলে, লেখক আবার পাঠালে বা আপনি বদল চাইলে, প্রতিবার এখানে থাকবে। (বদল রাখা শুরু হয়েছে ১৩ নভেম্বর ২০২৬ থেকে; তার আগের বদল জানা নেই।)') }}</p>
    @endif
    @php
        // D210: what the AI team did to this piece besides the words (those are in the steps below)
        $did = ['handover.cover' => __('এআই-এর আঁকা ছবি প্রচ্ছদে বসানো হয়েছে (ফেরাতে: প্রচ্ছদের অংশে "আগের ছবিতে ফিরুন" বা "ছবি সরান")'),
            'handover.publish_planned' => __('নিজে থেকে মনোনীত হওয়ার সময় বসানো হয়েছে'), 'handover.published' => __('নিজে থেকে মনোনীত হয়েছে, প্রকাশের তারিখ বসেছে'),
            'handover.publish_stopped' => __('আপনি নিজে থেকে প্রকাশ থামিয়েছেন'), 'handover.monthly' => __('যন্ত্রকণ্ঠের পাঠ অনুমোদিত হয়েছে'),
            'seo.applied' => __('এসইও তথ্য বসানো হয়েছে (ফেরাতে: উপরের "এসইও-র শেষ বদল ফেরান")')];
        $others = \App\Models\AuditLog::whereIn('action', array_keys($did))->where('subject_type', $post->getMorphClass())->where('subject_id', $post->id)->latest('id')->limit(15)->get();
    @endphp
    @if ($others->isNotEmpty())
        <div style="{{ $box }}">
            <p style="font-weight:700;margin:0 0 .3rem">{{ __('এআই দল এই লেখায় আর যা করেছে') }}</p>
            @foreach ($others as $l)
                <div style="font-size:.92rem;padding:.15rem 0"><span style="opacity:.7">{{ Bn::num($l->created_at->copy()->setTimezone('Asia/Kolkata')->format('d/m, H:i')) }}</span> · {{ $did[$l->action] }}</div>
            @endforeach
        </div>
    @endif
    @foreach ($versions->reverse()->values() as $i => $v)
        @php
            $prev = $versions->where('id', '<', $v->id)->last();
            $diff = $prev ? \App\Support\TextDiff::compare($prev->body_html, $v->body_html, $prev->title, $v->title) : null;
        @endphp
        <details style="{{ $box }}" @if ($i < $open) open @endif>
            <summary style="cursor:pointer;font-weight:700">
                {{ __(PostVersion::KINDS[$v->kind] ?? $v->kind) }} · {{ Bn::date($v->created_at->copy()->setTimezone('Asia/Kolkata')) }} {{ Bn::num($v->created_at->copy()->setTimezone('Asia/Kolkata')->format('H:i')) }}
                @if ($diff)
                    <span style="font-weight:400;opacity:.75"> · {{ $diff['same'] ? __('লেখায় কোনো বদল নেই') : __('+:a শব্দ, −:r শব্দ', ['a' => Bn::num((string) $diff['added']), 'r' => Bn::num((string) $diff['removed'])]) }}</span>
                @endif
            </summary>
            @if ($v->note)
                <div style="margin-top:.5rem;padding:.6rem .8rem;border-radius:.6rem;background:rgba(239,192,141,.25);white-space:pre-line">
                    <strong>{{ $v->kind === 'sent_back' ? __('সম্পাদকের কথা:') : __('লেখকের কথা:') }}</strong> {{ $v->note }}
                </div>
            @elseif ($v->kind === 'resubmitted')
                <p style="margin:.5rem 0 0;opacity:.7">{{ __('লেখক কোনো কথা লেখেননি।') }}</p>
            @endif
            @if (in_array($v->kind, ['ai_fix', 'reverted'], true))
                {{-- D210: who put the fixes in, each one by itself, with a way back --}}
                <p style="margin:.4rem 0 0;opacity:.75">{{ __('করেছেন:') }} {{ $v->user?->name ?? __('এআই দল, নিজে থেকে') }}</p>
                <ul style="margin:.4rem 0 0;padding:0;list-style:none">
                    @foreach ((array) $v->changes as $ci => $c)
                        <li style="display:flex;flex-wrap:wrap;gap:.5rem;align-items:center;justify-content:space-between;padding:.3rem 0;border-top:1px solid rgba(127,127,127,.18)">
                            <span>@if ($v->kind === 'reverted')<span style="color:#CB1B32">{{ $c['replacement'] ?? '' }}</span> → <strong>{{ $c['original'] ?? '' }}</strong>
                                @else<span style="color:#CB1B32;text-decoration:line-through">{{ $c['original'] ?? '' }}</span> → <strong>{{ $c['replacement'] ?? '' }}</strong>@endif
                                @if (! empty($c['kind']))<span style="opacity:.65"> · {{ __(\App\Ai\CopyEdit::LABELS[$c['kind']] ?? $c['kind']) }}</span>@endif</span>
                            @if ($v->kind === 'ai_fix')
                                @if (! empty($c['reverted']))<span style="opacity:.65">{{ __('ফেরানো হয়েছে') }}</span>
                                @elseif ($canRevert ?? false)<x-filament::button size="xs" color="gray" wire:click="revertFix({{ $v->id }}, {{ $ci }})" wire:loading.attr="disabled">{{ __('এটি ফেরান') }}</x-filament::button>@endif
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
            @if ($v->kind === 'desk_edit' && $v->user)
                <p style="margin:.4rem 0 0;opacity:.75">{{ __('বদল করেছেন:') }} {{ $v->user->name }}</p>
            @endif
            @if ($diff && ! $diff['same'])
                <p style="margin:.6rem 0 .2rem;font-weight:700">{{ __('আগের বারের থেকে কী বদলেছে') }} <span style="font-weight:400;opacity:.7">({{ __('সবুজ: নতুন, লাল: বাদ গেছে') }})</span></p>
                <div style="max-height:28rem;overflow:auto;border-left:3px solid rgba(127,127,127,.3);padding-left:.8rem">{!! $diff['html'] !!}</div>
            @elseif ($diff && $v->kind === 'resubmitted')
                <p style="margin:.5rem 0 0">{{ __('লেখক কিছু না বদলেই আবার পাঠিয়েছেন।') }}</p>
            @elseif ($diff)
                <p style="margin:.5rem 0 0;opacity:.75">{{ __('লেখায় কোনো বদল নেই') }}</p>
            @endif
        </details>
    @endforeach
</div>
