@props(['seo', 'data' => null])
{{-- D321: the children's screens of বাংলা শিখি (map, lessons, certificate). Full screen: no menus, no sign-in, no "লিখুন",
     no install or notification prompts, no ads, no analytics (D101). Progress stays in this browser (resources/js/learn.js). --}}
<!doctype html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#FBEBD8">
    <x-seo :seo="$seo" />
    <link rel="icon" href="{{ asset('images/logo.png') }}">
    <link rel="preload" href="{{ asset('fonts/anek-bangla-bengali.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ asset('fonts/fonts.css') }}?v=1">
    @vite(['resources/css/app.css', 'resources/js/learn.js'])
</head>
<body class="min-h-screen bg-peach-light text-navy antialiased [-webkit-tap-highlight-color:transparent]">
    {{ $slot }}
    @if ($data !== null)<script type="application/json" id="learn-data" nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">@json($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG)</script>@endif
</body>
</html>
