{{-- Newsletter e-mail (D58). Inline styles only (e-mail clients); logo palette; all text escaped. --}}
@php($n = $newsletter)
@php($link = fn (string $l) => \App\Support\NewsletterLinks::tracked($n, $deliveryId ?? null, $l))
<!doctype html>
<html lang="bn"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>{{ $n->subject }}</title></head>
<body style="margin:0;background:#FFFBF4;font-family:'Noto Sans Bengali','Anek Bangla',Arial,sans-serif;color:#13212C">
@if ($n->preheader)<div style="display:none;max-height:0;overflow:hidden;opacity:0">{{ $n->preheader }}&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;</div>@endif
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#FFFBF4"><tr><td align="center" style="padding:24px 12px">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#FFFFFF;border:1px solid #E8DCCB;border-radius:14px">
    <tr><td style="background:#13212C;border-radius:14px 14px 0 0;padding:22px 28px">
        <a href="{{ $link('home') }}" style="color:#FFFFFF;text-decoration:none;font-size:30px;font-weight:700">হাতপাখা</a>
        <div style="color:#EFC08D;font-size:13px;margin-top:4px">বাংলা সাহিত্যের ওয়েব পত্রিকা · ISSN 2321-3035</div>
    </td></tr>
    <tr><td style="padding:26px 28px 8px;font-size:17px;line-height:1.75">
        <h1 style="font-size:22px;line-height:1.4;margin:0 0 14px">{{ $n->subject }}</h1>
        @foreach (preg_split('/\n{2,}/', trim((string) $n->intro)) as $para)
            @if (trim($para) !== '')<p style="margin:0 0 14px">{!! nl2br(e(trim($para))) !!}</p>@endif
        @endforeach
    </td></tr>
    @foreach ((array) $n->items as $i => $it)
        <tr><td style="padding:6px 28px">
            <a href="{{ $link((string) $i) }}" style="display:block;border:1px solid #E8DCCB;border-radius:10px;padding:14px 16px;text-decoration:none;color:#13212C">
                <span style="display:block;font-size:18px;font-weight:700;color:#CB1B32">{{ $it['title'] }}</span>
                @if (! empty($it['author']))<span style="display:block;font-size:14px;color:#4D5A63">{{ $it['author'] }}</span>@endif
                @if (! empty($it['line']))<span style="display:block;font-size:16px;margin-top:6px;line-height:1.6">{{ $it['line'] }}</span>@endif
            </a>
        </td></tr>
    @endforeach
    <tr><td style="padding:18px 28px 6px;font-size:17px;line-height:1.75">
        @foreach (preg_split('/\n{2,}/', trim((string) $n->closing)) as $para)
            @if (trim($para) !== '')<p style="margin:0 0 14px">{!! nl2br(e(trim($para))) !!}</p>@endif
        @endforeach
        <p style="margin:6px 0 20px"><a href="{{ $link('cta') }}" style="display:inline-block;background:#CB1B32;color:#FFFFFF;text-decoration:none;border-radius:999px;padding:12px 26px;font-weight:700;font-size:17px">{{ $n->cta_label ?: 'হাতপাখা খুলুন' }}</a></p>
    </td></tr>
    <tr><td style="border-top:1px solid #E8DCCB;padding:14px 28px;font-size:13px;color:#4D5A63;line-height:1.6">
        এই চিঠি আপনি পাচ্ছেন কারণ আপনি হাতপাখার চিঠি চেয়েছিলেন বা হাতপাখায় লেখেন।
        আর চান না? <a href="{{ $unsubscribeUrl }}" style="color:#1A86C2">এক ক্লিকে বন্ধ করুন</a>।
    </td></tr>
</table></td></tr></table>
</body></html>
