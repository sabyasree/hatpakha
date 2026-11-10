{{-- One member's latest work (D61), in a side panel. --}}
@php($n = fn ($v) => \App\Support\Bn::num((string) $v))
<div style="display:grid;gap:.75rem">
    <div style="padding:.6rem .8rem;border-radius:.6rem;background:rgba(45,170,226,.1)">
        {{ __('ফল কোথায় দেখবেন:') }} <a href="{{ $where[1] }}" style="text-decoration:underline;font-weight:600">{{ $where[0] }}</a>
    </div>
    @forelse ($jobs as $j)
        <div style="padding:.45rem 0;border-bottom:1px solid rgba(127,127,127,.15)">
            <div style="font-size:.82rem;opacity:.7">{{ $n($j['at']->locale(app()->getLocale())->diffForHumans()) }} · ${{ $n(number_format($j['cost'], 3)) }}</div>
            <div>{{ __(\App\Ai\Team::PURPOSES[$j['purpose']] ?? $j['purpose']) }}
                @if ($j['post']) · <a href="{{ \App\Filament\Resources\Posts\PostResource::getUrl('edit', ['record' => $j['post']->id]) }}" style="text-decoration:underline">{{ \Illuminate\Support\Str::limit($j['post']->title, 60) }}</a>@endif
            </div>
        </div>
    @empty
        <div style="opacity:.7">{{ __('এখনও কোনো কাজ করেনি।') }}</div>
    @endforelse
</div>
