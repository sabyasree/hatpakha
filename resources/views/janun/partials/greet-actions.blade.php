{{-- D315: copy, WhatsApp (with the page's address) and a share picture for one greeting --}}
<div class="mt-2 flex flex-wrap gap-2 text-sm">
    <button type="button" data-copy="{{ $text }}" class="min-h-11 rounded-full border-2 border-navy px-4 font-bold hover:bg-peach-light">কপি</button>
    <a href="https://wa.me/?text={{ rawurlencode($text."\n\n".$s->url()) }}" target="_blank" rel="noopener" class="flex min-h-11 items-center rounded-full border-2 border-navy px-4 font-bold hover:bg-peach-light">হোয়াটসঅ্যাপ</a>
    @if (mb_strlen($text) <= 240)<button type="button" data-card="{{ $text }}" class="min-h-11 rounded-full bg-red px-4 font-bold text-white hover:bg-red-deep">ছবি</button>@endif
</div>
