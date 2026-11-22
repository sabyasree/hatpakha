{{-- The picture card dialog (hp-share-card.js): on the writer's stats page and on /chhobi/{post}/ (D252). --}}
<dialog data-card-dialog class="w-[min(92vw,460px)] rounded-3xl border-2 border-navy p-0 backdrop:bg-navy/70">
            <div class="p-4">
                <div class="flex items-center justify-between gap-2">
                    <h3 class="text-lg font-extrabold">{{ __('আমার লেখা হাতপাখায়') }}</h3>
                    <button type="button" data-card-close class="h-10 w-10 rounded-full border-2 border-navy font-bold" aria-label="{{ __('কার্ড বন্ধ করুন') }}">✕</button>
                </div>
                <canvas data-card-canvas width="1080" height="1350" class="mt-3 w-full rounded-xl border border-rule" role="img" aria-label="{{ __('শেয়ার কার্ড') }}"></canvas>
                <p class="mt-2 text-sm text-ink2">{{ __('ছবিটি ফেসবুক বা হোয়াটসঅ্যাপ স্ট্যাটাসে দিন, সঙ্গে লেখার লিংকটিও দিন (“লিংক কপি” টিপলেই কপি হয়ে যাবে)।') }}</p>
                <div class="mt-3 grid gap-2 sm:grid-cols-3">
                    <button type="button" data-card-share hidden class="h-12 rounded-full bg-red px-3 font-bold text-white sm:col-span-3">{{ __('ফেসবুক / হোয়াটসঅ্যাপে পাঠান') }}</button>
                    <button type="button" data-card-download class="h-12 rounded-full bg-navy font-bold text-white">{{ __('ছবি ডাউনলোড') }}</button>
                    <button type="button" data-card-copy class="h-12 rounded-full border-2 border-navy font-bold">{{ __('লিংক কপি') }}</button>
                </div>
                <p data-card-msg class="mt-2 text-center text-sm font-bold text-sky-night" role="status"></p>
            </div>
        </dialog>
