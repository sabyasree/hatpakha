{{-- D166 "আমার পাতা" (now "প্রোফাইল", D169) (owner + UX review + tests with older users): a reader sets a photo and a name; a writer also a
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
            <p class="text-ink2">{{ __('এই তথ্য আপনার লেখক-পাতায় আর প্রতিটি লেখার নিচে পাঠকেরা দেখেন।') }}@if ($author && $author->published_count > 0) <a href="{{ $author->url() }}" class="font-bold text-sky-night underline">{{ __('লেখক-পাতাটি দেখুন') }}</a>@endif</p>
        @endif
        <form method="POST" action="{{ Url::to('/account/profile') }}" enctype="multipart/form-data" class="mt-4 max-w-xl">
            @csrf
            <div class="flex flex-col items-center text-center">
                <img data-preview="avatar" @if ($avatar) src="{{ $avatar }}" @endif alt="" class="{{ $avatar ? '' : 'hidden' }} h-24 w-24 rounded-full border-2 border-navy object-cover">
                @unless ($avatar)<span data-preview-fallback="avatar" class="flex h-24 w-24 items-center justify-center rounded-full border-2 border-navy bg-peach text-4xl font-extrabold" aria-hidden="true">{{ $acct['initial'] }}</span>@endunless
                <label class="mt-3 inline-flex h-12 cursor-pointer items-center rounded-full border-2 border-navy bg-white px-6 font-bold hover:bg-peach-light">
                    {{ $avatar ? __('ছবি বদলান') : __('ছবি দিন') }}<input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" data-preview-input="avatar" class="sr-only"></label>
                <p class="mt-1 text-sm text-ink2">{{ __('ফোনের যেকোনো ছবি চলবে।') }}{{ $writer ? ' '.__('মুখের ছবি হলে ভালো।') : '' }}</p>
                @if ($avatar)<label class="mt-1 inline-flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" name="remove_avatar" value="1"> {{ __('ছবি সরিয়ে দিন') }}</label>@endif
            </div>

            @if ($writer)
                <label class="mt-6 block"><span class="text-lg font-bold">{{ __('আপনার নাম (বাংলায়)') }}</span>
                    <input name="name_bn" value="{{ old('name_bn', $author?->display_name_bn) }}" maxlength="80" class="{{ $in }}" placeholder="{{ __('যেমন: মনোরমা চক্রবর্তী') }}">
                    <span class="mt-1 block text-sm text-ink2">{{ __('লেখার সঙ্গে এই নামটিই ছাপা হয়।') }}</span></label>
                <label class="mt-5 block"><span class="text-lg font-bold">{{ __('নাম ইংরেজিতে') }}</span> <span class="text-ink2">{{ __('(ইচ্ছে হলে)') }}</span>
                    <input name="name" value="{{ old('name', $user->name) }}" maxlength="80" class="{{ $in }}" lang="en"></label>
                <label class="mt-5 block"><span class="text-lg font-bold">{{ __('নিজের কথা') }}</span>
                    <textarea name="bio" rows="4" maxlength="1000" class="mt-1 block w-full rounded-xl border-2 border-navy bg-white p-3 text-lg" placeholder="{{ __('যেমন: হাওড়ায় থাকি, কবিতা লিখি কলেজের দিন থেকে।') }}">{{ old('bio', $author?->bio) }}</textarea>
                    <span class="mt-1 block text-sm text-ink2">{{ __('দু-চার লাইন। পাঠকেরা আপনাকে চিনবেন।') }}</span></label>
                <details class="group mt-5 rounded-xl border-2 border-navy bg-white" @if ($errors->hasAny(['city', 'website', 'facebook', 'instagram', 'cover'])) open @endif>
                    <summary class="flex min-h-14 cursor-pointer list-none items-center justify-between gap-3 px-4 py-2 font-bold [&::-webkit-details-marker]:hidden">
                        <span>{{ __('আরও কিছু যোগ করুন') }}<span class="block text-sm font-medium text-ink2">{{ __('ইচ্ছে হলে: শহর, ফেসবুক, ওয়েবসাইট, লেখক-পাতার ওপরে একটা চওড়া ছবি') }}</span></span>
                        <span class="transition group-open:rotate-180" aria-hidden="true">▾</span></summary>
                    <div class="border-t-2 border-rule p-4">
                        <x-field name="city" :label="__('শহর')" :value="$author?->city" maxlength="60" />
                        <x-field name="facebook" type="url" :label="__('ফেসবুক লিংক')" :value="$author?->links['facebook'] ?? null" placeholder="https://facebook.com/…" />
                        <x-field name="instagram" type="url" :label="__('ইনস্টাগ্রাম লিংক')" :value="$author?->links['instagram'] ?? null" placeholder="https://instagram.com/…" />
                        <x-field name="website" type="url" :label="__('ওয়েবসাইট লিংক')" :value="$author?->links['website'] ?? null" placeholder="https://" />
                        <p class="font-bold">{{ __('লেখক-পাতার ওপরের চওড়া ছবি') }}</p>
                        <img data-preview="cover" @if ($author?->cover_path) src="{{ $author->cover_path }}" @endif alt="" class="{{ $author?->cover_path ? '' : 'hidden' }} mt-2 h-24 w-full rounded-xl border-2 border-navy object-cover">
                        <label class="mt-2 inline-flex h-11 cursor-pointer items-center rounded-full border-2 border-navy px-4 font-bold">{{ __('ছবি দিন') }}<input type="file" name="cover" accept="image/jpeg,image/png,image/webp" data-preview-input="cover" class="sr-only"></label>
                        @if ($author?->cover_path)<label class="ml-2 inline-flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" name="remove_cover" value="1"> {{ __('সরিয়ে দিন') }}</label>@endif
                    </div>
                </details>
            @else
                <label class="mt-6 block"><span class="text-lg font-bold">{{ __('আপনার নাম') }}</span>
                    <input name="name" value="{{ old('name', $user->name) }}" maxlength="80" required class="{{ $in }}">
                    <span class="mt-1 block text-sm text-ink2">{{ __('মন্তব্যে পাঠকেরা এই নামটি দেখবেন। বাংলায় বা ইংরেজিতে, যেভাবে চান।') }}</span></label>
                <div class="mt-5"><span class="text-lg font-bold">{{ __('ই-মেল') }}</span>
                    <div class="mt-1 flex h-14 items-center rounded-xl border-2 border-rule bg-peach-light px-4 text-ink2">{{ $user->email }}</div>
                    <span class="mt-1 block text-sm text-ink2">{{ __('এই ই-মেল দিয়েই সাইন ইন করেন।') }} {{ __('বদলাতে চাইলে:') }} <a href="{{ Url::to('/contact-us') }}" class="font-bold text-sky-night underline">{{ __('আমাদের লিখুন') }}</a></span></div>
            @endif
            <button class="mt-6 h-14 w-full rounded-full bg-red text-lg font-bold text-white shadow-[3px_3px_0_#13212C]">{{ __('সেভ করুন') }}</button>
        </form>
        <a href="{{ Url::to('/account/settings') }}" class="mt-6 flex min-h-14 max-w-xl items-center justify-between gap-3 rounded-xl border-2 border-navy bg-white px-4 font-bold hover:bg-peach-light">
            <span>{{ __('ই-মেল বা নোটিফিকেশন বন্ধ করবেন? পাসওয়ার্ড বদলাবেন?') }}<span class="block text-sm font-medium text-ink2">{{ __('সেটিংস') }}</span></span><span aria-hidden="true">›</span></a>
        {{-- D178: badges earned on Hatpakha --}}
        <h2 id="badges" class="mt-10 scroll-mt-24 font-display text-2xl font-extrabold">{{ __('আমার ব্যাজ') }}</h2>
        <p class="mt-1 text-ink2">{{ __('পড়লে, খেললে, লিখলে, মতামত দিলে ব্যাজ জমে। প্রতিটির তিন ধাপ: ব্রোঞ্জ, রুপো, সোনা।') }}</p>
        @include('partials.badges', ['badges' => \App\Support\Badges::for($user), 'who' => \App\Support\AccountMenu::for($user)['name']])
        @include('partials.invite', ['user' => $user])
    </x-account-shell>
</x-layouts.app>
