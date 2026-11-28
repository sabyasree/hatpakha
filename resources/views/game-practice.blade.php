{{-- D330 (owner's list: "old puzzles to replay for practice, without points"): the last thirty days, newest first. --}}
@php
    use App\Support\Bn;
    use App\Support\Url;
@endphp
<x-layouts.app :seo="$seo">
    <section class="mx-auto max-w-[560px] px-5 pb-12 pt-6">
        @include('partials.game-nav', ['on' => 'hub'])
        <p class="font-hand text-2xl text-red">বাদ পড়া দিনগুলো</p>
        <h1 class="font-display text-4xl font-extrabold leading-tight">পুরোনো খেলা</h1>
        <p class="mt-2 text-lg text-ink2">গত তিরিশ দিনের খেলা। যে দিনটা খেলা হয়নি, এখন খেলে নিন। এখানে পয়েন্ট ওঠে না, শুধু অনুশীলন আর মজা।</p>
        <ul class="mt-5 grid gap-3">
            @foreach ($days as $date => $games)
                <li class="rounded-2xl border-2 border-navy bg-white p-3">
                    <p class="font-display text-lg font-extrabold">{{ Bn::date(\Illuminate\Support\Carbon::parse($date)) }}</p>
                    <p class="mt-1 flex flex-wrap gap-2">
                        @foreach ($games as $slug => [$name])
                            <a href="{{ Url::to('/khela/purono/'.$slug.'/'.$date) }}" class="flex min-h-11 items-center rounded-full border-2 border-navy px-3 text-[15px] font-bold hover:bg-peach-light">{{ $name }}</a>
                        @endforeach
                    </p>
                </li>
            @endforeach
        </ul>
        <x-ad-slot spot="list" :height="250" class="!mt-12" />
    </section>
</x-layouts.app>
