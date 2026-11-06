@props(['url', 'label' => 'ডেস্কে সম্পাদনা'])
{{-- For editors and admins only (D63): straight from the page on the site to its editing screen. --}}
@auth
    @if (auth()->user()->hasRole(\App\Enums\Role::Editor))
        <a href="{{ $url }}" class="fixed bottom-20 left-4 z-50 flex h-11 items-center gap-2 rounded-full border-2 border-navy bg-paper px-4 font-bold shadow-lg hover:bg-peach-light md:bottom-6"
            rel="nofollow">✎ {{ $label }}</a>
    @endif
@endauth
