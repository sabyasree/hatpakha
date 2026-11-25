{{-- D293: links to topic pages. $topics: slug => name; $heading; $current: the topic page we are on (optional) --}}
@if (! empty($topics))
    <nav class="{{ $class ?? 'mt-6' }}" aria-label="{{ $heading }}">
        <p class="text-sm font-bold text-ink2">{{ $heading }}</p>
        <div class="mt-2 flex flex-wrap gap-2">
            @foreach ($topics as $slug => $name)
                @if ($slug !== ($current ?? null))
                    <a href="{{ \App\Support\Topics::url($slug) }}" class="flex min-h-11 items-center rounded-full border-2 border-navy bg-paper px-4 font-bold hover:bg-peach-light">{{ $name }}</a>
                @endif
            @endforeach
        </div>
    </nav>
@endif
