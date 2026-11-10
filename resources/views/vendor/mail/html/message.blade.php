<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
হাতপাখা
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
হাতপাখা · বাংলা সাহিত্যের ওয়েব পত্রিকা · ISSN 2321-3035 · [hatpakha.com]({{ \App\Support\Url::to('/') }})
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
