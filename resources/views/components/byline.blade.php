@props(['post'])
@foreach ($post->authors as $author)
    <a href="{{ $author->url() }}" class="font-semibold hover:text-red">{{ $author->publicName() }}</a>@if (! $loop->last), @endif
@endforeach
