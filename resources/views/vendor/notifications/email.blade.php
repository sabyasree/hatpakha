<x-mail::message :preheader="$introLines[0] ?? null">
{{-- Greeting: plain bold text; the headline ("## …" in the template) carries the news (D124) --}}
@if (! empty($greeting))
**{{ $greeting }}**

@endif

{{-- Intro Lines --}}
@foreach ($introLines as $line)
{{ $line }}

@endforeach

{{-- Action Button --}}
@isset($actionText)
<?php
    $color = match ($level) {
        'success', 'error' => $level,
        default => 'primary',
    };
?>
<x-mail::button :url="$actionUrl" :color="$color">
{{ $actionText }}
</x-mail::button>
@endisset

{{-- Outro Lines --}}
@foreach ($outroLines as $line)
{{ $line }}

@endforeach

{{-- Salutation --}}
@if (! empty($salutation))
{{ $salutation }}
@else
— হাতপাখা
@endif

{{-- Subcopy --}}
@isset($actionText)
<x-slot:subcopy>
{{-- D123: in Bengali, like the rest of the e-mail --}}
বোতামটি কাজ না করলে এই লিংকে চাপ দিন, বা লিংকটি কপি করে ব্রাউজারে বসান:

<span class="break-all">[{{ $displayableActionUrl }}]({{ $actionUrl }})</span>
</x-slot:subcopy>
@endisset
</x-mail::message>
