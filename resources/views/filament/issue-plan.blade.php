{{-- The AI editor's issue proposal, for the owner (D42). Inline styles: /desk has no utility CSS. --}}
@php
    $plan = (array) $issue->ai_plan;
    $posts = \App\Models\Post::with('authors', 'section')->whereIn('id', (array) ($plan['post_ids'] ?? []))->get()->keyBy('id');
    $names = \App\Support\Lang::t(\App\Filament\Resources\Issues\Schemas\IssueForm::THEMES);
@endphp
<div style="line-height:1.8">
    <p style="margin:0 0 .5rem"><strong>{{ __('থিম:') }}</strong> {{ $names[$issue->theme_key] ?? $issue->theme_key }}</p>
    @if (! empty($plan['reason']))<p style="margin:0 0 .75rem">{{ $plan['reason'] }}</p>@endif
    @if (! empty($plan['cover_lines']))
        <p style="margin:0 0 .75rem"><strong>{{ __('প্রচ্ছদের লাইন:') }}</strong> {{ implode(' · ', $plan['cover_lines']) }}</p>
    @endif
    <p style="margin:0 0 .25rem;font-weight:700">{{ __('লেখার তালিকা (পড়ার ক্রমে)') }}</p>
    <ol style="margin:0;padding-inline-start:1.4rem;list-style:decimal">
        @foreach ((array) ($plan['post_ids'] ?? []) as $id)
            @if ($p = $posts[$id] ?? null)
                <li><a href="{{ \App\Filament\Resources\Posts\PostResource::getUrl('edit', ['record' => $p]) }}" style="text-decoration:underline">{{ $p->title }}</a>
                    <span style="opacity:.7">· {{ $p->authors->first()?->publicName() }} · {{ $p->section?->name_bn }}</span></li>
            @endif
        @endforeach
    </ol>
    @if ($issue->editorial)
        <p style="margin:.75rem 0 0"><strong>{{ __('সম্পাদকীয় খসড়া:') }}</strong>
            <a href="{{ \App\Filament\Resources\Posts\PostResource::getUrl('edit', ['record' => $issue->editorial]) }}" style="text-decoration:underline">{{ $issue->editorial->title }}</a>
            ({{ $issue->editorial->status === 'published' ? __('প্রকাশিত') : __('আপনার সম্পাদনা ও অনুমোদনের অপেক্ষায়') }})</p>
    @endif
</div>
