{{-- D89: one message, printed escaped (plain text from a public form). Links are shown, not clickable. --}}
<dl style="display:grid;grid-template-columns:max-content 1fr;gap:.4rem 1rem;font-size:.95rem">
    <dt style="opacity:.7">{{ __('নাম') }}</dt><dd>{{ $m->name }}</dd>
    <dt style="opacity:.7">{{ __('ই-মেল') }}</dt><dd>{{ $m->email }}</dd>
    @if ($m->organisation)<dt style="opacity:.7">{{ __('প্রতিষ্ঠান') }}</dt><dd>{{ $m->organisation }}</dd>@endif
    @if ($m->phone)<dt style="opacity:.7">{{ __('ফোন') }}</dt><dd>{{ $m->phone }}</dd>@endif
    @if ($m->interests['when'] ?? null)<dt style="opacity:.7">{{ __('কোন সময়ে') }}</dt><dd>{{ $m->interests['when'] }}</dd>@endif
    @if ($m->link)<dt style="opacity:.7">{{ __('লিংক') }}</dt><dd style="word-break:break-all"><code>{{ $m->link }}</code></dd>@endif
    <dt style="opacity:.7">{{ __('কবে') }}</dt><dd>{{ $m->created_at->timezone('Asia/Kolkata')->format('d/m/Y H:i') }}@if ($m->user_id) · {{ __('সাইটে লগ-ইন করা') }}@endif</dd>
</dl>
<div style="margin-top:1rem;white-space:pre-wrap;line-height:1.7;padding:.8rem;border-radius:.6rem;background:rgba(127,127,127,.08)">{{ $m->body }}</div>
@if ($m->status !== 'new' && $m->handled_at)
    <p style="margin-top:.6rem;opacity:.7;font-size:.85rem">{{ __('শেষ বদল') }}: {{ $m->handler?->name }} · {{ $m->handled_at->timezone('Asia/Kolkata')->format('d/m/Y H:i') }}</p>
@endif
