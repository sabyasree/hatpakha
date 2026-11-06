{{-- Cover box on the post edit page (D64): what is on the site now, and the AI designer's proposal. Inline styles. --}}
@php
    $now = $post->featured_image_path;
    $drawn = $post->proposed_image_path;
    $src = fn ($p) => str_starts_with($p, 'http') ? $p : asset(ltrim($p, '/'));
@endphp
<div style="display:grid;gap:.75rem">
    <div>
        <div style="font-size:.8rem;opacity:.7;margin-bottom:.25rem">{{ __('সাইটে এখন') }}</div>
        @if ($now)
            <img src="{{ $src($now) }}" alt="" style="width:100%;max-height:14rem;object-fit:cover;border-radius:.75rem">
        @else
            <div style="padding:1rem;border:1px dashed rgba(127,127,127,.4);border-radius:.75rem;opacity:.75">{{ __('প্রচ্ছদ নেই। উপরের "প্রচ্ছদ → এআই দিয়ে ছবি আঁকান" দিয়ে আঁকিয়ে নিতে পারেন।') }}</div>
        @endif
    </div>
    @if ($drawn)
        <div style="padding:.6rem;border:2px solid #2DAAE2;border-radius:.9rem">
            <div style="font-size:.85rem;font-weight:600;margin-bottom:.35rem">{{ __('এআই-এর আঁকা নতুন ছবি (এখনও বসানো হয়নি)') }}</div>
            <img src="{{ $src($drawn) }}" alt="" style="width:100%;max-height:14rem;object-fit:cover;border-radius:.6rem">
            <div style="font-size:.8rem;opacity:.75;margin-top:.35rem">{{ __('নিচের "এটা বসান" চাপলে সাইটে এই ছবি দেখা যাবে।') }}</div>
        </div>
    @elseif (\App\Ai\Illustrator::drawing($post))
        <div style="padding:.6rem;border-radius:.75rem;background:rgba(45,170,226,.1)">{{ __('ছবি আঁকা হচ্ছে… দুই-তিন মিনিট পরে পাতাটি আবার খুলুন।') }}</div>
    @elseif ($post->proposed_image_error)
        <div style="font-size:.8rem;opacity:.75">{{ __('শেষবার আঁকা যায়নি:') }} {{ $post->proposed_image_error }}</div>
    @endif
</div>
