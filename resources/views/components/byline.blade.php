@props(['post'])
@foreach ($post->authors as $author)
    <a href="{{ $author->url() }}" class="inline-flex min-h-11 items-center font-semibold hover:text-red">{{ $author->publicName() }}</a>@if (! $loop->last), @endif
@endforeach
