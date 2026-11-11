{{-- Plain-text part (text/plain, never shown as HTML): written unescaped, so & or quotes stay as typed --}}
{!! $newsletter->subject !!}

{!! $newsletter->intro !!}

@php($link = fn (string $l) => \App\Support\NewsletterLinks::tracked($newsletter, $deliveryId ?? null, $l))
@foreach ((array) $newsletter->items as $i => $it)
* {{ $it['title'] }}{{ ! empty($it['author']) ? ' — '.$it['author'] : '' }}
  {{ $it['line'] ?? '' }}
  {!! $link((string) $i) !!}

@endforeach
{!! $newsletter->closing !!}

{{ $newsletter->cta_label ?: 'হাতপাখা খুলুন' }}: {!! $link('cta') !!}

@if (str_contains($unsubscribeUrl, 'unsubscribe'))
আর চিঠি চান না? {!! $unsubscribeUrl !!}
@endif
