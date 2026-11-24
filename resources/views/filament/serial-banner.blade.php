{{-- D279: on a writer's serial episode, at the top: which serial, which episode, and for a new serial the writer's
     whole proposal, so the proposal and its first episode are decided on one page. --}}
@php
    $post = $getRecord();
    $s = $post?->serial;
    $info = $s ? rescue(function () use ($post, $s) {
        $prev = \App\Models\Post::published()->where('serial_id', $s->id)->where('episode_no', '<', (int) $post->episode_no)->latest('published_at')->first();
        return ['prev' => $prev, 'next' => $s->status === 'proposed' ? null : $s->nextEpisodeAt($post->id)];
    }, ['prev' => null, 'next' => null], false) : null;
@endphp
@if ($s)
    <div style="border:2px solid #EFC08D;background:rgba(239,192,141,.14);border-radius:.9rem;padding:.9rem 1.1rem">
        @if ($s->status === 'proposed')
            <div style="font-weight:800;font-size:1.05rem">{{ __('নতুন ধারাবাহিকের প্রস্তাব') }}: ‘{{ $s->title }}’ · {{ __('এটি পর্ব ১') }}</div>
            <div style="margin-top:.35rem"><b>{{ __('লেখক') }}:</b> {{ $s->user?->name ?? __('এআই দল') }} · <b>{{ __('আনুমানিক পর্ব') }}:</b> {{ \App\Support\Bn::num((string) (int) $s->planned_count) }}</div>
            @if ($s->logline)<div style="margin-top:.35rem"><b>{{ __('এক-দুই লাইনে') }}:</b> {{ $s->logline }}</div>@endif
            @if ($s->plan)<details style="margin-top:.35rem" open><summary style="cursor:pointer;font-weight:700">{{ __('লেখকের পুরো পরিকল্পনা') }}</summary><div style="white-space:pre-line;margin-top:.3rem">{{ $s->plan }}</div></details>@endif
            <div style="margin-top:.5rem;opacity:.85">{{ __('এই পর্বটি মনোনীত করলে ধারাবাহিক শুরু হবে; নতুন পর্ব প্রতি শনিবার সকালে। বাতিল করলে প্রস্তাবও বাতিল।') }}</div>
        @else
            <div style="font-weight:800;font-size:1.05rem">{{ __('ধারাবাহিক') }} ‘{{ $s->title }}’ · {{ __('পর্ব') }} {{ \App\Support\Bn::num((string) $post->episode_no) }}</div>
            <div style="margin-top:.35rem">
                @if ($info['prev']){{ __('আগের পর্ব প্রকাশিত') }}: {{ \App\Support\DeskInbox::day($info['prev']->published_at) }}@else{{ __('আগের কোনো পর্ব এখনও প্রকাশিত নয়') }}@endif
                @if ($info['next']) · {{ __('এটির প্রকাশের দিন') }}: <b>{{ \App\Support\DeskInbox::day($info['next']) }}</b>@endif
            </div>
            <div style="margin-top:.35rem"><a href="{{ \App\Filament\Resources\Serials\SerialResource::getUrl('edit', ['record' => $s]) }}" style="text-decoration:underline">{{ __('ধারাবাহিকের সব পর্ব দেখুন') }}</a></div>
        @endif
    </div>
@endif
