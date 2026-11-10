{{-- The graphic designer's card template (D51): our own Bengali typesetting over the picture;
     the image model never writes text. 1080×1080, logo palette only. --}}
@php($quote = $layout === 'quote')
@if ($layout === 'book')
{{-- E-book cover (D68): 1600×2560, the shape book stores recommend. Our own Bengali type over the artwork. --}}
<div class="hp-card" style="width:1600px;height:2560px;position:relative;overflow:hidden;font-family:'Anek Bangla',sans-serif;background:#13212C;color:#FFFFFF">
    @if ($card['image'])<img src="{{ $card['image'] }}" alt="" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover">@endif
    <div style="position:absolute;inset:0;background:linear-gradient(180deg,rgba(19,33,44,.88) 0%,rgba(19,33,44,0) 34%,rgba(19,33,44,0) 66%,rgba(19,33,44,.92) 100%)"></div>
    <div style="position:absolute;top:120px;left:100px;right:100px;text-align:center">
        <div style="font-family:'Galada',cursive;font-size:280px;line-height:1.05">হাতপাখা</div>
        <div style="font-size:54px;opacity:.9">বাংলা সাহিত্যের ওয়েব পত্রিকা</div>
    </div>
    <div style="position:absolute;left:100px;right:100px;bottom:260px;text-align:center">
        <div style="display:inline-block;background:#CB1B32;border-radius:999px;padding:24px 70px;font-size:104px;font-weight:700;line-height:1.2">{{ $card['title'] }}</div>
        @if (! empty($card['by']))<div style="margin-top:40px;font-size:52px;color:#EFC08D">{{ $card['by'] }}</div>@endif
    </div>
    <div style="position:absolute;left:100px;right:100px;bottom:90px;font-size:40px;opacity:.85;display:flex;justify-content:space-between"><span>hatpakha.com</span><span>ISSN 2321-3035</span></div>
</div>
@elseif ($layout === 'issue')
<div class="hp-card" style="width:1080px;height:1350px;position:relative;overflow:hidden;font-family:'Anek Bangla',sans-serif;background:#13212C;color:#FFFFFF">
    @if ($card['image'])<img src="{{ $card['image'] }}" alt="" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover">@endif
    <div style="position:absolute;inset:0;background:linear-gradient(180deg,rgba(19,33,44,.85) 0%,rgba(19,33,44,0) 38%,rgba(19,33,44,0) 70%,rgba(19,33,44,.9) 100%)"></div>
    <div style="position:absolute;top:60px;left:72px;right:72px;text-align:center">
        <div style="font-family:'Galada',cursive;font-size:170px;line-height:1.1">হাতপাখা</div>
        <div style="font-size:34px;letter-spacing:.06em;opacity:.9">{{ $card['section'] }}</div>
    </div>
    <div style="position:absolute;left:72px;right:72px;bottom:110px;text-align:center">
        <span style="background:#CB1B32;color:#FFFFFF;border-radius:999px;padding:14px 44px;font-size:60px;font-weight:700">{{ $card['title'] }}</span>
    </div>
    <div style="position:absolute;left:72px;right:72px;bottom:40px;font-size:26px;opacity:.85;display:flex;justify-content:space-between">
        <span>hatpakha.com</span><span>ISSN 2321-3035</span>
    </div>
</div>
@else
<div class="hp-card" style="width:1080px;height:1080px;position:relative;overflow:hidden;font-family:'Anek Bangla',sans-serif;background:{{ $quote ? '#FBEBD8' : '#13212C' }};color:{{ $quote ? '#13212C' : '#FFFFFF' }}">
    @if (! $quote && $card['image'])
        <img src="{{ $card['image'] }}" alt="" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover">
        <div style="position:absolute;inset:0;background:linear-gradient(180deg,rgba(19,33,44,0) 35%,rgba(19,33,44,.92) 78%)"></div>
    @endif
    <div style="position:absolute;top:56px;left:64px;right:64px;display:flex;justify-content:space-between;align-items:center">
        <span style="font-family:'Galada',cursive;font-size:64px;color:{{ $quote ? '#CB1B32' : '#FFFFFF' }}">হাতপাখা</span>
        @if ($card['section'])<span style="background:#CB1B32;color:#FFFFFF;border-radius:999px;padding:10px 28px;font-size:30px">{{ $card['section'] }}</span>@endif
    </div>
    @if ($quote)
        <div style="position:absolute;left:96px;right:96px;top:230px;bottom:260px;display:flex;align-items:center">
            <div style="font-family:'Noto Serif Bengali',serif;font-size:{{ mb_strlen($card['quote']) > 90 ? 54 : 66 }}px;line-height:1.45">
                <span style="color:#CB1B32;font-size:120px;line-height:0;vertical-align:-40px">“</span>{{ $card['quote'] }}
            </div>
        </div>
        <div style="position:absolute;left:96px;right:96px;bottom:110px;font-size:36px;border-top:4px solid #CB1B32;padding-top:28px">
            <strong>{{ $card['by'] }}</strong> · {{ $card['title'] }}
        </div>
    @else
        <div style="position:absolute;left:64px;right:64px;bottom:120px">
            <div style="font-size:{{ mb_strlen($card['title']) > 40 ? 64 : 84 }}px;font-weight:700;line-height:1.25">{{ $card['title'] }}</div>
            <div style="font-size:40px;margin-top:20px;color:#EFC08D">{{ $card['by'] }}</div>
        </div>
    @endif
    <div style="position:absolute;left:64px;right:64px;bottom:44px;font-size:26px;opacity:.85;display:flex;justify-content:space-between">
        <span>hatpakha.com</span><span>ISSN 2321-3035</span>
    </div>
</div>
@endif
