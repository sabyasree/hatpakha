@props(['topic', 'off' => '🔔 ফোনে জানান', 'on' => '✓ ফোনে জানাব', 'small' => false])
{{-- D128: phone notification for one topic, for this browser, with or without an account. Shown by the script only where it can work.
     D132: small = a chip (the section choices). --}}
@if (\App\Support\WebPush::publicKey())
    <button type="button" hidden data-push-topic="{{ $topic }}" data-off="{{ $off }}" data-on="{{ $on }}" aria-pressed="false"
        {{ $attributes->merge(['class' => ($small ? 'h-10 px-4 text-sm' : 'h-12 px-5').' inline-flex items-center justify-center rounded-full border-2 border-navy bg-white font-bold aria-pressed:bg-sky-tint']) }}>{{ $off }}</button>
@endif
