@props(['preheader' => null])
<x-mail::layout :preheader="$preheader">
<x-slot:header>
<x-mail::header :url="\App\Support\Url::to('/')">
হাতপাখা
</x-mail::header>
</x-slot:header>

{!! $slot !!}

@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

<x-slot:footer>
<x-mail::footer>
**হাতপাখা** · বাংলা সাহিত্যের ওয়েব পত্রিকা · ISSN 2321‑3035

[hatpakha.com]({{ \App\Support\Url::to('/') }}) · [ফেসবুক]({{ config('services.social.facebook') }}) · [ইনস্টাগ্রাম]({{ config('services.social.instagram') }}) · [যোগাযোগ]({{ \App\Support\Url::to('/contact-us') }})

হাতপাখায় আপনার অ্যাকাউন্ট, লেখা বা পাঠানো ফর্মের জন্য এই ই-মেল। উত্তর দিলে সম্পাদকের কাছে পৌঁছবে।
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
