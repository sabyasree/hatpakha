{{-- D315: every live greetings page, the occasions coming soonest first --}}
@php($U = fn ($p) => \App\Support\Url::to($p))
<x-layouts.app :seo="$seo">
    <article class="mx-auto max-w-[760px] px-5 py-8">
        @include('janun.partials.head', ['crumbs' => [['হাতপাখা', $U('/')], ['জেনে রাখুন', $U('/janun')], ['শুভেচ্ছা বার্তা', '']],
            'kicker' => 'জেনে রাখুন', 'title' => 'শুভেচ্ছা বার্তা'])
        <p lang="en" class="mt-1 text-sm text-ink2">Bengali wishes for festivals and birthdays</p>
        <p class="mt-4 font-serif text-lg leading-relaxed">উৎসবে, জন্মদিনে, প্রিয়জনকে পাঠানোর মতো বাংলা বার্তা। সব হাতপাখার নিজের লেখা, পরিবার, বন্ধু আর অফিসের জন্য আলাদা করে। পছন্দের বার্তা দিয়ে ছবিও বানিয়ে নিতে পারেন।</p>
        <ul class="mt-5 grid gap-3 sm:grid-cols-2">
            @foreach ($sets as $o)
                <li><a href="{{ $o->url() }}" class="block rounded-2xl border-2 border-navy p-4 hover:bg-peach-light"><span class="block font-display text-xl font-extrabold">{{ $o->name() }}</span>
                    <span class="mt-1 block text-sm text-ink2">{{ \App\Support\Bn::num((string) count($o->lines())) }}টি বার্তা</span></a></li>
            @endforeach
        </ul>
        <x-ad-slot spot="list" :height="250" />
    </article>
</x-layouts.app>
