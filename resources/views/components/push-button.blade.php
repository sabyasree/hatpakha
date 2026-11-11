@props(['topic', 'off' => '🔔 ফোনে জানান', 'on' => '✓ ফোনে জানাব'])
{{-- D128: phone notification for one topic, for this browser, with or without an account. Shown by the script only where it can work. --}}
@if (\App\Support\WebPush::publicKey())
    <button type="button" hidden data-push-topic="{{ $topic }}" data-off="{{ $off }}" data-on="{{ $on }}" aria-pressed="false"
        {{ $attributes->merge(['class' => 'inline-flex h-12 items-center justify-center rounded-full border-2 border-navy bg-white px-5 font-bold aria-pressed:bg-sky-tint']) }}>{{ $off }}</button>
@endif
