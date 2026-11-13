@props(['title', 'subtitle' => null])
<section class="mx-auto max-w-[480px] px-5 pb-16 pt-6">
    {{-- D169: sign-up and sign-in in বাংলা or English --}}
    <div class="mb-4 flex justify-end">@include('partials.language-switch')</div>
    <h1 class="font-display text-4xl font-extrabold">{{ $title }}</h1>
    @if ($subtitle)<p class="mt-2 font-serif text-lg text-ink2">{{ $subtitle }}</p>@endif
    @if (session('status'))
        <div role="status" class="mt-5 rounded-xl border-2 border-sky bg-sky-tint p-4 font-semibold text-sky-night">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div role="alert" class="mt-5 rounded-xl border-2 border-red bg-peach-light p-4 font-semibold text-red-dark">
            @foreach ($errors->all() as $e)<p>{{ $e }}</p>@endforeach
        </div>
    @endif
    <div class="mt-6">{{ $slot }}</div>
</section>
