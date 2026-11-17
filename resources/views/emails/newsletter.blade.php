{{-- Newsletter e-mail (D58). Inline styles only (e-mail clients); logo palette; all text escaped. --}}
@php($n = $newsletter)
@php($items = (array) $n->items)
@php($lay = \App\Support\LetterDesign::sections($n))
@php($link = fn (string $l) => \App\Support\NewsletterLinks::tracked($n, $deliveryId ?? null, $l))
{{-- Web addresses typed in the opening or closing become links (text stays escaped). --}}
@php($para = fn (string $t) => preg_replace('~https?://[^\s<>"]+[^\s<>".,;:!?)\]।]~u', '<a href="$0" style="color:#1A86C2;font-weight:700">$0</a>', nl2br(e($t))))
<!doctype html>
<html lang="bn"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>{{ $n->subject }}</title></head>
<body style="margin:0;background:#FFFBF4;font-family:'Noto Sans Bengali','Anek Bangla',Arial,sans-serif;color:#13212C">
@if ($n->preheader)<div style="display:none;max-height:0;overflow:hidden;opacity:0">{{ $n->preheader }}&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;</div>@endif
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#FFFBF4"><tr><td align="center" style="padding:24px 12px">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#FFFFFF;border:1px solid #E8DCCB;border-radius:14px;table-layout:fixed;word-break:break-word;overflow-wrap:anywhere">
    {{-- The same header as every other Hatpakha e-mail (D124): the mark, the name, a red and a peach stripe. --}}
    <tr><td style="background:#13212C;border-radius:14px 14px 0 0;padding:22px 28px">
        <table role="presentation" cellpadding="0" cellspacing="0"><tr>
            <td valign="middle" style="padding-right:12px"><img src="{{ asset('images/mail-mark.png') }}" width="40" height="40" alt="" style="display:block;border:0"></td>
            <td valign="middle">
                <a href="{{ $link('home') }}" style="color:#FFFFFF;text-decoration:none;font-size:30px;font-weight:700;line-height:1.2">হাতপাখা</a>
                <div style="color:#EFC08D;font-size:14px;line-height:1.5;margin-top:4px">বাংলা সাহিত্যের ওয়েব পত্রিকা · ২০১২ থেকে</div>
            </td>
        </tr></table>
    </td></tr>
    <tr><td height="3" style="background:#CB1B32;font-size:0;line-height:0">&nbsp;</td></tr>
    <tr><td height="2" style="background:#EFC08D;font-size:0;line-height:0">&nbsp;</td></tr>
    <tr><td style="padding:26px 28px 8px;font-size:17px;line-height:1.75">
        <h1 style="font-size:22px;line-height:1.4;margin:0 0 14px">{{ $n->subject }}</h1>
        @foreach (preg_split('/\n{2,}/', trim((string) $n->intro)) as $p)
            @if (trim($p) !== '')<p style="margin:0 0 14px">{!! $para(trim($p)) !!}</p>@endif
        @endforeach
    </td></tr>
    {{-- D225: laid out like a small magazine. One piece leads; each serial once; the rest as short rows. --}}
    @if ($lay['lead'] !== null)
        @php($it = $items[$lay['lead']])
        <tr><td style="padding:8px 28px 10px">
            <div style="font-size:13px;font-weight:700;letter-spacing:1px;color:#CB1B32;padding-bottom:8px">{{ ($it['kind'] ?? '') === 'serial' ? 'ধারাবাহিকের নতুন পর্ব' : 'এ সপ্তাহের প্রথম পাতা' }}</div>
            @if (! empty($it['image']))
                <a href="{{ $link((string) $lay['lead']) }}" style="display:block;text-decoration:none"><img src="{{ $it['image'] }}" width="544" alt="" style="display:block;width:100%;max-width:544px;height:auto;border:0;border-radius:12px"></a>
            @endif
            <a href="{{ $link((string) $lay['lead']) }}" style="display:block;text-decoration:none;color:#13212C;font-size:26px;font-weight:700;line-height:1.35;padding-top:14px">{{ $it['title'] }}</a>
            @if (! empty($it['author']))<div style="font-size:15px;color:#4D5A63;padding-top:2px">{{ $it['author'] }}</div>@endif
            @if (! empty($it['quote']))
                <div style="margin-top:14px;border-left:4px solid #CB1B32;background:#FBEBD8;border-radius:0 10px 10px 0;padding:14px 18px">
                    <div style="font-size:19px;line-height:1.75;color:#13212C">“{{ $it['quote'] }}”</div>
                    <div style="font-size:13px;color:#7A1022;padding-top:6px;font-weight:700">লেখা থেকে</div>
                </div>
            @endif
            @if (! empty($it['line']))<div style="font-size:17px;line-height:1.75;padding-top:12px">{{ $it['line'] }}</div>@endif
            <div style="padding-top:14px"><a href="{{ $link((string) $lay['lead']) }}" style="display:inline-block;background:#13212C;color:#FFFFFF;text-decoration:none;border-radius:999px;padding:10px 22px;font-weight:700;font-size:16px">পুরোটা পড়ুন →</a></div>
        </td></tr>
    @endif
    @if ($lay['serials'])
        <tr><td style="padding:16px 28px 0"><div style="border-top:2px solid #13212C;padding-top:12px;font-size:20px;font-weight:700;color:#13212C">ধারাবাহিক: গল্প এখন যেখানে</div></td></tr>
        @foreach ($lay['serials'] as $i)
            @php($it = $items[$i])
            <tr><td style="padding:8px 28px">
                <a href="{{ $link((string) $i) }}" style="display:block;text-decoration:none;color:#13212C">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
                    @if (! empty($it['image']))<td width="84" valign="top" style="padding-right:14px"><img src="{{ $it['image'] }}" width="84" height="84" alt="" style="display:block;width:84px;height:84px;object-fit:cover;border:0;border-radius:10px"></td>@endif
                    <td valign="top">
                        <span style="display:block;font-size:18px;font-weight:700;color:#CB1B32;line-height:1.4">{{ $it['serial'] ?? $it['title'] }}</span>
                        @if (! empty($it['episode']))<span style="display:block;font-size:15px;font-weight:700;color:#13212C">{{ $it['episode'] }}</span>@endif
                        @if (! empty($it['line']))<span style="display:block;font-size:16px;line-height:1.6;padding-top:4px">{{ $it['line'] }}</span>@endif
                        <span style="display:block;font-size:14px;font-weight:700;color:#1A86C2;padding-top:4px">এই পর্ব পড়ুন →</span>
                    </td>
                </tr></table>
                </a>
            </td></tr>
        @endforeach
    @endif
    @if ($lay['more'])
        @if ($lay['lead'] !== null)<tr><td style="padding:16px 28px 0"><div style="border-top:2px solid #13212C;padding-top:12px;font-size:20px;font-weight:700;color:#13212C">আরও পড়ুন</div></td></tr>@endif
        @foreach ($lay['more'] as $i)
            @php($it = $items[$i])
            <tr><td style="padding:6px 28px">
                <a href="{{ $link((string) $i) }}" style="display:block;border:1px solid #E8DCCB;border-radius:10px;padding:14px 16px;text-decoration:none;color:#13212C">
                    <span style="display:block;font-size:18px;font-weight:700;color:#CB1B32">{{ $it['title'] }}</span>
                    @if (! empty($it['author']))<span style="display:block;font-size:14px;color:#4D5A63">{{ $it['author'] }}</span>@endif
                    @if (! empty($it['line']))<span style="display:block;font-size:16px;margin-top:6px;line-height:1.6">{{ $it['line'] }}</span>@endif
                </a>
            </td></tr>
        @endforeach
    @endif
    {{-- D184: reasons to come back this week. D225: the riddle is set out to be tried, a reader's words as a letter. --}}
    @if ($n->extras)
        <tr><td style="padding:18px 28px 2px;font-size:15px;font-weight:700;color:#4D5A63">এ সপ্তাহে আরও</td></tr>
        @foreach ((array) $n->extras as $i => $it)
            <tr><td style="padding:6px 28px">
                @if (($it['kind'] ?? '') === 'riddle')
                    <a href="{{ $link('x'.$i) }}" style="display:block;background:#13212C;border-radius:12px;padding:18px 20px;text-decoration:none;color:#FFFFFF">
                        <span style="display:block;font-size:13px;font-weight:700;letter-spacing:1px;color:#EFC08D">{{ $it['title'] }} · ভেবে দেখুন</span>
                        <span style="display:block;font-size:21px;font-weight:700;line-height:1.6;padding-top:6px;color:#FFFFFF">{{ $it['line'] ?? '' }}</span>
                        <span style="display:block;font-size:15px;color:#EFC08D;padding-top:8px;font-weight:700">উত্তর মিলিয়ে নিন →</span>
                    </a>
                @elseif (($it['kind'] ?? '') === 'voice')
                    <a href="{{ $link('x'.$i) }}" style="display:block;border:2px solid #13212C;border-radius:12px;padding:16px 18px;text-decoration:none;color:#13212C">
                        <span style="display:block;font-size:13px;font-weight:700;letter-spacing:1px;color:#CB1B32">{{ $it['title'] }}</span>
                        <span style="display:block;font-size:17px;line-height:1.75;padding-top:6px">“{{ $it['line'] ?? '' }}”</span>
                        <span style="display:block;font-size:14px;color:#4D5A63;padding-top:6px">— {{ $it['author'] ?? '' }}</span>
                    </a>
                @else
                    <a href="{{ $link('x'.$i) }}" style="display:block;background:#FBEBD8;border-radius:10px;padding:14px 16px;text-decoration:none;color:#13212C">
                        <span style="display:block;font-size:17px;font-weight:700;color:#7A1022">{{ $it['title'] }}</span>
                        @if (! empty($it['line']))<span style="display:block;font-size:16px;margin-top:4px;line-height:1.6">{{ $it['line'] }}</span>@endif
                        @if (! empty($it['author']))<span style="display:block;font-size:14px;color:#4D5A63;margin-top:4px">{{ $it['author'] }} →</span>@endif
                    </a>
                @endif
            </td></tr>
        @endforeach
    @endif
    <tr><td style="padding:18px 28px 6px;font-size:17px;line-height:1.75">
        @foreach (preg_split('/\n{2,}/', trim((string) $n->closing)) as $p)
            @if (trim($p) !== '')<p style="margin:0 0 14px">{!! $para(trim($p)) !!}</p>@endif
        @endforeach
        <p style="margin:6px 0 20px"><a href="{{ $link('cta') }}" style="display:inline-block;background:#CB1B32;color:#FFFFFF;text-decoration:none;border-radius:999px;padding:12px 26px;font-weight:700;font-size:17px">{{ $n->cta_label ?: 'হাতপাখা খুলুন' }}</a></p>
    </td></tr>
    <tr><td style="border-top:1px solid #E8DCCB;padding:14px 28px;font-size:13px;color:#4D5A63;line-height:1.6">
        এই চিঠি আপনি পাচ্ছেন কারণ আপনি হাতপাখার চিঠি চেয়েছিলেন বা হাতপাখায় লেখেন।
        আর চান না? <a href="{{ $unsubscribeUrl }}" style="color:#1A86C2">এক ক্লিকে বন্ধ করুন</a>।
    </td></tr>
</table></td></tr></table>
</body></html>
