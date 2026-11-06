{{ $newsletter->subject }}

{{ $newsletter->intro }}

@foreach ((array) $newsletter->items as $it)
* {{ $it['title'] }}{{ ! empty($it['author']) ? ' — '.$it['author'] : '' }}
  {{ $it['line'] ?? '' }}
  {{ $it['url'] }}

@endforeach
{{ $newsletter->closing }}

— হাতপাখা ({{ url('/') }}/)

আর চিঠি চান না? {{ $unsubscribeUrl }}
