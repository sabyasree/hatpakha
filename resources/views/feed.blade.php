{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
<title>{{ $title }}</title>
<link>{{ $link }}</link>
<description>হাতপাখা — বাংলা সাহিত্যের অনলাইন পত্রিকা</description>
<language>bn</language>
@foreach ($posts as $p)
<item><title>{{ $p->title }}</title><link>{{ $p->url() }}</link><guid isPermaLink="true">{{ $p->url() }}</guid><pubDate>{{ $p->published_at?->toRfc2822String() }}</pubDate><dc:creator xmlns:dc="http://purl.org/dc/elements/1.1/">{{ $p->authors->pluck("display_name")->join(", ") }}</dc:creator><description>{{ $p->metaDescription() }}</description></item>
@endforeach
</channel>
</rss>
