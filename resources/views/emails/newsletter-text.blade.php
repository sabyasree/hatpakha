{{-- Plain-text part (text/plain, never shown as HTML): written unescaped, so & or quotes stay as typed --}}
{!! $newsletter->subject !!}

{!! $newsletter->intro !!}

@php($link = fn (string $l) => \App\Support\NewsletterLinks::tracked($newsletter, $deliveryId ?? null, $l))
@php($lay = \App\Support\LetterDesign::sections($newsletter))
@php($items = (array) $newsletter->items)
@if ($lay['lead'] !== null)
@php($it = $items[$lay['lead']])
== {!! $it['title'] !!}{!! ! empty($it['author']) ? ' — '.$it['author'] : '' !!} ==
@if (! empty($it['quote']))
  “{!! $it['quote'] !!}”
@endif
  {!! $it['line'] ?? '' !!}
  {!! $link((string) $lay['lead']) !!}

@endif
@if ($lay['serials'])
ধারাবাহিক: গল্প এখন যেখানে

@endif
@foreach (array_merge($lay['serials'], $lay['more']) as $i)
@php($it = $items[$i])
* {!! $it['title'] !!}{!! ! empty($it['author']) && ($it['kind'] ?? '') !== 'serial' ? ' — '.$it['author'] : '' !!}
  {!! $it['line'] ?? '' !!}
  {!! $link((string) $i) !!}

@endforeach
@foreach ((array) $newsletter->extras as $i => $it)
* {!! $it['title'] !!}
  {!! $it['line'] ?? '' !!}
  {!! $link('x'.$i) !!}

@endforeach
{!! $newsletter->closing !!}

{{ $newsletter->cta_label ?: 'হাতপাখা খুলুন' }}: {!! $link('cta') !!}

@if (str_contains($unsubscribeUrl, 'unsubscribe'))
আর চিঠি চান না? {!! $unsubscribeUrl !!}
@endif
