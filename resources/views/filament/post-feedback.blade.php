{{-- D113: human feedback on an AI-written piece, and the rewrite waiting beside the current text. Inline styles: /desk has no utility CSS. --}}
@php
    $items = \App\Models\WritingFeedback::where('post_id', $post->id)->latest()->get();
@endphp
<div style="display:grid;gap:.8rem">
    @foreach ($items as $f)
        <div style="border:1px solid rgba(127,127,127,.3);border-radius:.6rem;padding:.6rem .8rem">
            <div style="font-size:.82rem;opacity:.7">{{ $f->giver ?: __('নাম নেই') }} · {{ $f->created_at?->diffForHumans() }}</div>
            <div style="white-space:pre-line;margin-top:.2rem">{{ $f->body }}</div>
            <div style="font-size:.82rem;margin-top:.3rem;opacity:.8">
                @if ($f->apply_now)
                    {{ __('এই লেখা') }}: {{ __(['queued' => 'আবার লেখা হচ্ছে…', 'running' => 'আবার লেখা হচ্ছে…', 'done' => 'নতুন সংস্করণ তৈরি', 'failed' => 'হয়নি'][$f->rewrite_status] ?? '—') }}@if ($f->rewrite_error) ({{ $f->rewrite_error }})@endif
                @endif
                @if ($f->apply_future)
                    · {{ __('পরের লেখায়') }}: {{ $f->scopeLabel() }}@if (! $f->active) ({{ __('বন্ধ') }})@endif
                @endif
            </div>
        </div>
    @endforeach
    @if ($post->rewrite_html)
        <details style="border:2px solid #2DAAE2;border-radius:.6rem;padding:.6rem .8rem" open>
            <summary style="font-weight:700;cursor:pointer">{{ $post->rewrite_kind === 'previous' ? __('আগের সংস্করণ (রাখা আছে)') : __('মতামত মেনে নতুন সংস্করণ (দেখে বসান)') }}</summary>
            @if ($post->rewrite_note && $post->rewrite_kind === 'proposed')<p style="margin:.4rem 0;font-size:.9rem"><b>{{ __('কী বদলেছে') }}:</b> {{ $post->rewrite_note }}</p>@endif
            @if ($post->rewrite_title)<p style="font-weight:700;font-size:1.1rem;margin-top:.4rem">{{ $post->rewrite_title }}</p>@endif
            <div style="max-height:28rem;overflow:auto;margin-top:.4rem;line-height:1.8">{!! \App\Support\HtmlSanitizer::clean($post->rewrite_html) !!}</div>
        </details>
    @endif
</div>
