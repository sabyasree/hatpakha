{{ $newsletter->subject }}

{{ $newsletter->intro }}

@foreach ((array) $newsletter->items as $it)
* {{ $it['title'] }}{{ ! empty($it['author']) ? ' — '.$it['author'] : '' }}
  {{ $it['line'] ?? '' }}
  {{ $it['url'] }}

@endforeach
{{ $newsletter->closing }}

{{ $newsletter->cta_label ?: 'হাতপাখা খুলুন' }}: {{ \App\Ai\Copywriter::ctaUrl($newsletter) }}

আর চিঠি চান না? {{ $unsubscribeUrl }}
