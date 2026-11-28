{{-- D330 (owner's list: "old puzzles to replay for practice, without points"): shown above a past day's game. --}}
@if (! empty($practice))
    <div class="mb-3 rounded-2xl border-2 border-navy bg-sky-tint p-3">
        <p class="font-bold text-sky-night">অনুশীলন: {{ \App\Support\Bn::date(\Illuminate\Support\Carbon::parse($game['date'])) }}-এর খেলা। মজার জন্য খেলুন, পয়েন্ট ওঠে না।</p>
        <p class="mt-1 flex flex-wrap gap-x-4"><a href="{{ \App\Support\Url::to('/khela/aaj') }}" class="inline-flex min-h-11 items-center font-bold underline">আজকের খেলা →</a><a href="{{ \App\Support\Url::to('/khela/purono') }}" class="inline-flex min-h-11 items-center font-bold underline">আরও পুরোনো খেলা</a></p>
    </div>
@endif
