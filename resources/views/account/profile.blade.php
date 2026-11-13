{{-- D166 "আমার পাতা" (owner + UX review + tests with older users): a reader sets a photo and a name; a writer also a
     Bengali name, a few lines about themselves, and (folded away) city, links and a wide picture for the writer page. --}}
@php
    use App\Support\Url;
    $acct = \App\Support\AccountMenu::for($user);
    $writer = $acct['writer'];
    $avatar = $author?->avatar_path;
    $in = 'mt-1 block h-14 w-full rounded-xl border-2 border-navy bg-white px-4 text-lg';
@endphp
<x-layouts.app :seo="$seo">
    <x-account-shell tab="profile" :user="$user" :author="$author" :counts="$counts" :two-factor-on="$twoFactorOn" :cert-count="$certCount">
        @if ($writer)
            <p class="text-ink2">এই তথ্য আপনার লেখক-পাতায় আর প্রতিটি লেখার নিচে পাঠকেরা দেখেন।@if ($author && $author->published_count > 0) <a href="{{ $author->url() }}" class="font-bold text-sky-night underline">লেখক-পাতাটি দেখুন</a>@endif</p>
        @endif
        <form method="POST" action="{{ Url::to('/account/profile') }}" enctype="multipart/form-data" class="mt-4 max-w-xl">
            @csrf
            <div class="flex flex-col items-center text-center">
                <img data-preview="avatar" @if ($avatar) src="{{ $avatar }}" @endif alt="" class="{{ $avatar ? '' : 'hidden' }} h-24 w-24 rounded-full border-2 border-navy object-cover">
                @unless ($avatar)<span data-preview-fallback="avatar" class="flex h-24 w-24 items-center justify-center rounded-full border-2 border-navy bg-peach text-4xl font-extrabold" aria-hidden="true">{{ $acct['initial'] }}</span>@endunless
                <label class="mt-3 inline-flex h-12 cursor-pointer items-center rounded-full border-2 border-navy bg-white px-6 font-bold hover:bg-peach-light">
                    {{ $avatar ? 'ছবি বদলান' : 'ছবি দিন' }}<input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" data-preview-input="avatar" class="sr-only"></label>
                <p class="mt-1 text-sm text-ink2">ফোনের যেকোনো ছবি চলবে।{{ $writer ? ' মুখের ছবি হলে ভালো।' : '' }}</p>
                @if ($avatar)<label class="mt-1 inline-flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" name="remove_avatar" value="1"> ছবি সরিয়ে দিন</label>@endif
            </div>

            @if ($writer)
                <label class="mt-6 block"><span class="text-lg font-bold">আপনার নাম (বাংলায়)</span>
                    <input name="name_bn" value="{{ old('name_bn', $author?->display_name_bn) }}" maxlength="80" class="{{ $in }}" placeholder="যেমন: মনোরমা চক্রবর্তী">
                    <span class="mt-1 block text-sm text-ink2">লেখার সঙ্গে এই নামটিই ছাপা হয়।</span></label>
                <label class="mt-5 block"><span class="text-lg font-bold">নাম ইংরেজিতে</span> <span class="text-ink2">(ইচ্ছে হলে)</span>
                    <input name="name" value="{{ old('name', $user->name) }}" maxlength="80" class="{{ $in }}" lang="en"></label>
                <label class="mt-5 block"><span class="text-lg font-bold">নিজের কথা</span>
                    <textarea name="bio" rows="4" maxlength="1000" class="mt-1 block w-full rounded-xl border-2 border-navy bg-white p-3 text-lg" placeholder="যেমন: হাওড়ায় থাকি, কবিতা লিখি কলেজের দিন থেকে।">{{ old('bio', $author?->bio) }}</textarea>
                    <span class="mt-1 block text-sm text-ink2">দু-চার লাইন। পাঠকেরা আপনাকে চিনবেন।</span></label>
                <details class="group mt-5 rounded-xl border-2 border-navy bg-white" @if ($errors->hasAny(['city', 'website', 'facebook', 'instagram', 'cover'])) open @endif>
                    <summary class="flex min-h-14 cursor-pointer list-none items-center justify-between gap-3 px-4 py-2 font-bold [&::-webkit-details-marker]:hidden">
                        <span>আরও কিছু যোগ করুন<span class="block text-sm font-medium text-ink2">ইচ্ছে হলে: শহর, ফেসবুক, ওয়েবসাইট, লেখক-পাতার মাথায় একটা চওড়া ছবি</span></span>
                        <span class="transition group-open:rotate-180" aria-hidden="true">▾</span></summary>
                    <div class="border-t-2 border-rule p-4">
                        <x-field name="city" label="শহর" :value="$author?->city" maxlength="60" />
                        <x-field name="facebook" type="url" label="ফেসবুক পাতার ঠিকানা" :value="$author?->links['facebook'] ?? null" placeholder="https://facebook.com/…" />
                        <x-field name="instagram" type="url" label="ইনস্টাগ্রাম" :value="$author?->links['instagram'] ?? null" placeholder="https://instagram.com/…" />
                        <x-field name="website" type="url" label="ওয়েবসাইট" :value="$author?->links['website'] ?? null" placeholder="https://" />
                        <p class="font-bold">লেখক-পাতার মাথার চওড়া ছবি</p>
                        <img data-preview="cover" @if ($author?->cover_path) src="{{ $author->cover_path }}" @endif alt="" class="{{ $author?->cover_path ? '' : 'hidden' }} mt-2 h-24 w-full rounded-xl border-2 border-navy object-cover">
                        <label class="mt-2 inline-flex h-11 cursor-pointer items-center rounded-full border-2 border-navy px-4 font-bold">ছবি দিন<input type="file" name="cover" accept="image/jpeg,image/png,image/webp" data-preview-input="cover" class="sr-only"></label>
                        @if ($author?->cover_path)<label class="ml-2 inline-flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" name="remove_cover" value="1"> সরিয়ে দিন</label>@endif
                    </div>
                </details>
            @else
                <label class="mt-6 block"><span class="text-lg font-bold">আপনার নাম</span>
                    <input name="name" value="{{ old('name', $user->name) }}" maxlength="80" required class="{{ $in }}">
                    <span class="mt-1 block text-sm text-ink2">মন্তব্যে পাঠকেরা এই নামটি দেখবেন। বাংলায় বা ইংরেজিতে, যেভাবে চান।</span></label>
                <div class="mt-5"><span class="text-lg font-bold">ই-মেল</span>
                    <div class="mt-1 flex h-14 items-center rounded-xl border-2 border-rule bg-peach-light px-4 text-ink2">{{ $user->email }}</div>
                    <span class="mt-1 block text-sm text-ink2">এই ই-মেল দিয়েই ঢোকেন। বদলাতে চাইলে <a href="{{ Url::to('/contact-us') }}" class="font-bold text-sky-night underline">আমাদের লিখুন</a>।</span></div>
            @endif
            <button class="mt-6 h-14 w-full rounded-full bg-red text-lg font-bold text-white shadow-[3px_3px_0_#13212C]">রাখুন</button>
        </form>
        <a href="{{ Url::to('/account/settings') }}" class="mt-6 flex min-h-14 max-w-xl items-center justify-between gap-3 rounded-xl border-2 border-navy bg-white px-4 font-bold hover:bg-peach-light">
            <span>ই-মেল বা ফোনের খবর বন্ধ করবেন? পাসওয়ার্ড বদলাবেন?<span class="block text-sm font-medium text-ink2">ই-মেল ও পাসওয়ার্ড</span></span><span aria-hidden="true">›</span></a>
    </x-account-shell>
</x-layouts.app>
