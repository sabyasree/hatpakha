{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
@foreach ($urls as $u)
<url><loc>{{ $u["loc"] }}</loc>@if ($u["lastmod"])<lastmod>{{ $u["lastmod"]->toAtomString() }}</lastmod>@endif @if ($u["image"])<image:image><image:loc>{{ $u["image"] }}</image:loc></image:image>@endif</url>
@endforeach
</urlset>
