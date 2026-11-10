@props(['preheader' => null])
{{-- D124: Hatpakha's e-mail frame (all automatic e-mails). Logo palette only; tables and inline styles for e-mail clients;
     Bengali-friendly type (bigger size, airy lines); works at phone width. --}}
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="bn" xml:lang="bn">
<head>
<title>হাতপাখা</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta name="color-scheme" content="light dark">
<meta name="supported-color-schemes" content="light dark">
<style>
@media only screen and (max-width: 620px) {
.inner-body, .footer, .header-table { width: 100% !important; }
.content-cell { padding: 26px 20px !important; }
.header-cell { padding: 20px !important; }
}
@media only screen and (max-width: 500px) {
.button { display: block !important; text-align: center !important; font-size: 18px !important; }
.button-cell { width: 100% !important; }
.action table { width: 100% !important; }
.button { max-width: none !important; }
}
/* Dark mode (Apple Mail; Outlook via [data-ogsc]): navy card, paper text, peach labels; the button stays red. */
@media (prefers-color-scheme: dark) {
.wrapper, body { background-color: #13212C !important; }
.inner-body { background-color: #13212C !important; border-color: #4D5A63 !important; }
.content-cell p, .content-cell h1, .content-cell h2, .content-cell td, .footer p { color: #FFFBF4 !important; }
.hp-link, .footer a { color: #FFFBF4 !important; }
.hp-note { background-color: #4D5A63 !important; }
.hp-note-label { color: #EFC08D !important; }
}
[data-ogsc] .wrapper { background-color: #13212C !important; }
[data-ogsc] .inner-body { background-color: #13212C !important; border-color: #4D5A63 !important; }
[data-ogsc] .content-cell p, [data-ogsc] .content-cell h2, [data-ogsc] .content-cell td, [data-ogsc] .footer p { color: #FFFBF4 !important; }
[data-ogsc] .hp-link, [data-ogsc] .footer a { color: #FFFBF4 !important; }
[data-ogsc] .hp-note { background-color: #4D5A63 !important; }
[data-ogsc] .hp-note-label { color: #EFC08D !important; }
</style>
{!! $head ?? '' !!}
</head>
<body>
{{-- Inbox preview line (hidden in the e-mail itself) --}}
@if (! empty($preheader ?? null))<div style="display:none;max-height:0;overflow:hidden;opacity:0;mso-hide:all">{{ $preheader }}&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;</div>@endif

<table class="wrapper" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="center" style="padding: 24px 12px;">
<table class="content" width="100%" cellpadding="0" cellspacing="0" role="presentation">
{!! $header ?? '' !!}

<tr>
<td class="body" width="100%" cellpadding="0" cellspacing="0">
<!--[if mso]><table align="center" width="580" cellpadding="0" cellspacing="0" role="presentation"><tr><td><![endif]-->
<table class="inner-body" align="center" width="100%" cellpadding="0" cellspacing="0" role="presentation" style="max-width:580px;margin:0 auto;">
<tr>
<td class="content-cell">
{!! Illuminate\Mail\Markdown::parse($slot) !!}

{!! $subcopy ?? '' !!}
</td>
</tr>
</table>
<!--[if mso]></td></tr></table><![endif]-->
</td>
</tr>

{!! $footer ?? '' !!}
</table>
</td>
</tr>
</table>
</body>
</html>
