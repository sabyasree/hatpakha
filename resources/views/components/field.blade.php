@props(['name', 'label', 'type' => 'text', 'value' => null, 'autocomplete' => null, 'hint' => null])
<label class="mb-4 block">
    <span class="mb-1 block font-bold">{{ $label }}</span>
    <input name="{{ $name }}" type="{{ $type }}" value="{{ $type === 'password' ? '' : old($name, $value) }}" @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
        {{ $attributes->merge(['class' => 'block h-12 w-full rounded-xl border-2 border-navy bg-white px-4 text-lg focus:border-sky-deep']) }}>
    @if ($hint)<span class="mt-1 block text-sm text-ink2">{{ $hint }}</span>@endif
</label>
