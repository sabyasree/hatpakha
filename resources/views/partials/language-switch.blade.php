{{-- D169: one tap to the other language. The current page comes back in it. --}}
@php
    $toEn = app()->getLocale() !== 'en';
    $back = '/'.ltrim(request()->path(), '/').(request()->path() === '/' ? '' : '/').(request()->getQueryString() ? '?'.request()->getQueryString() : '');
    $back = preg_replace('#/+#', '/', $back);
@endphp
<a href="{{ url('/language/'.($toEn ? 'en' : 'bn')) }}/?back={{ rawurlencode($back) }}" @if ($toEn) lang="en" @else lang="bn" @endif
    class="{{ $class ?? 'inline-flex min-h-11 items-center gap-1.5 rounded-full border-2 border-navy bg-white px-4 text-sm font-bold hover:bg-peach-light' }}">
    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c3 3.5 3 14.5 0 18M12 3c-3 3.5-3 14.5 0 18"/></svg>
    {{ $toEn ? 'English' : 'বাংলা' }}
</a>
