{{-- The writer's profile, its own main tab (owner): what readers see on the writer page. --}}
@php
    use App\Support\Bn;
    use App\Support\Url;
    $avatar = $author?->avatar_path;
@endphp
<x-layouts.app :seo="$seo">
    <x-account-shell tab="profile" :user="$user" :author="$author" :counts="$counts" :two-factor-on="$twoFactorOn" :cert-count="$certCount">
        <div class="mb-4 flex flex-wrap items-end justify-between gap-2">
            <div>
                <h2 class="font-display text-2xl font-extrabold">লেখক-প্রোফাইল</h2>
                <p class="text-sm text-ink2">আপনার লেখক-পাতায় পাঠকেরা যা দেখবেন: ছবি, প্রচ্ছদ, নাম, নিজের কথা।</p>
            </div>
            @if ($author && $author->published_count > 0)
                <a href="{{ $author->url() }}" class="flex h-11 items-center rounded-full border-2 border-navy bg-white px-4 text-sm font-bold hover:bg-peach-light">লেখক-পাতা দেখুন ↗</a>
            @endif
        </div>
            <form method="POST" action="{{ Url::to('/account/profile') }}" enctype="multipart/form-data" class="overflow-hidden rounded-3xl border-2 border-navy bg-white">
                @csrf
                {{-- Live preview of cover + photo --}}
                <div class="relative h-36 bg-sky md:h-48">
                    <img data-preview="cover" @if ($author?->cover_path) src="{{ $author->cover_path }}" @endif alt="" class="{{ $author?->cover_path ? '' : 'hidden' }} h-full w-full object-cover">
                    @unless ($author?->cover_path)<div class="halftone absolute inset-0" aria-hidden="true"></div>@endunless
                    <label class="absolute right-3 top-3 flex h-10 cursor-pointer items-center rounded-full bg-white/95 px-4 text-sm font-bold shadow">
                        প্রচ্ছদ বদলান<input type="file" name="cover" accept="image/jpeg,image/png,image/webp" data-preview-input="cover" class="sr-only"></label>
                </div>
                <div class="px-4 pb-5 sm:px-5">
                    <div class="-mt-12 flex items-end gap-4">
                        <div class="relative">
                            <img data-preview="avatar" @if ($avatar) src="{{ $avatar }}" @endif alt="" class="{{ $avatar ? '' : 'hidden' }} h-24 w-24 rounded-full border-4 border-white object-cover outline outline-2 outline-navy">
                            @unless ($avatar)<span data-preview-fallback="avatar" class="flex h-24 w-24 items-center justify-center rounded-full border-4 border-white bg-gradient-to-br from-peach to-peach-deep text-4xl font-extrabold outline outline-2 outline-navy" aria-hidden="true">{{ mb_substr($user->name, 0, 1) }}</span>@endunless
                        </div>
                        <label class="mb-1 flex h-10 cursor-pointer items-center rounded-full border-2 border-navy px-4 text-sm font-bold">
                            ছবি দিন<input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" data-preview-input="avatar" class="sr-only"></label>
                    </div>
                    <p class="mt-2 text-xs text-ink2">ছবি: বর্গাকার ভালো (অন্তত ২৪০×২৪০)। প্রচ্ছদ: চওড়া ছবি (৩:১, অন্তত ৭৫০ পিক্সেল)। JPG/PNG/WebP, ৮ MB পর্যন্ত।
                        @if ($avatar)<label class="ml-2 inline-flex items-center gap-1"><input type="checkbox" name="remove_avatar" value="1"> ছবি সরান</label>@endif
                        @if ($author?->cover_path)<label class="ml-2 inline-flex items-center gap-1"><input type="checkbox" name="remove_cover" value="1"> প্রচ্ছদ সরান</label>@endif
                    </p>

                    <div class="mt-5 grid gap-x-5 sm:grid-cols-2">
                        <x-field name="name" label="ছাপার নাম" :value="$user->name" maxlength="80" required hint="লেখার সঙ্গে এই নাম ছাপা হয়।" />
                        <x-field name="name_bn" label="বাংলায় নাম (ঐচ্ছিক)" :value="$author?->display_name_bn" maxlength="80" hint="থাকলে লেখক-পাতায় এটাই দেখাবে।" />
                        <x-field name="city" label="শহর (ঐচ্ছিক)" :value="$author?->city" maxlength="60" />
                        <x-field name="website" type="url" label="ওয়েবসাইট (ঐচ্ছিক)" :value="$author?->links['website'] ?? null" placeholder="https://" />
                        <x-field name="facebook" type="url" label="ফেসবুক (ঐচ্ছিক)" :value="$author?->links['facebook'] ?? null" placeholder="https://facebook.com/…" />
                        <x-field name="instagram" type="url" label="ইনস্টাগ্রাম (ঐচ্ছিক)" :value="$author?->links['instagram'] ?? null" placeholder="https://instagram.com/…" />
                    </div>
                    <label class="block">
                        <span class="mb-1 block font-bold">নিজের কথা (ঐচ্ছিক)</span>
                        <textarea name="bio" rows="4" maxlength="1000" class="block w-full rounded-xl border-2 border-navy bg-white p-3 text-lg" placeholder="দু-চার লাইনে নিজের কথা: কী লেখেন, কোথা থেকে…">{{ old('bio', $author?->bio) }}</textarea>
                        <span class="mt-1 block text-sm text-ink2">১০০০ অক্ষর পর্যন্ত। সাধারণ লেখা; লিংক উপরের ঘরে দিন।</span>
                    </label>
                    <button class="mt-5 h-12 rounded-full bg-red px-8 font-bold text-white">প্রোফাইল রাখুন</button>
                </div>
            </form>
    </x-account-shell>
</x-layouts.app>
