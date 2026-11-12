<?php

namespace App\Filament\Pages;

use App\Ai\EditorialWriter;
use App\Enums\Role;
use App\Filament\Resources\Posts\PostResource;
use App\Models\Author;
use App\Models\Issue;
use App\Models\Post;
use App\Models\Section;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * Owner (2026-11-12): "there is no separate editorial section in the desk". Every issue with its editorial in one
 * list: written or not, draft or published, and one button for the next step (write it yourself, have the AI draft it
 * for you to edit, open it). Editorials not tied to an issue are listed below. Nothing is published without a click (D42).
 */
class Editorials extends Page
{
    use \App\Filament\Concerns\TranslatedPageLabels;

    protected string $view = 'filament.pages.editorials';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static ?string $navigationLabel = 'সম্পাদকীয়';

    protected static ?string $title = 'সম্পাদকীয়';

    protected static ?string $slug = 'editorials';

    protected static ?int $navigationSort = 11;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(Role::Editor) ?? false;
    }

    /** This month's or next month's issue without a published editorial. */
    public static function getNavigationBadge(): ?string
    {
        $n = rescue(fn () => \App\Ai\IssueWatch::current()->filter(fn ($i) => ! $i->liveEditorial())->count(), 0, false);

        return $n ? \App\Support\Bn::num((string) $n) : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public function getViewData(): array
    {
        $now = now('Asia/Kolkata');
        $issues = Issue::where(fn ($q) => $q->where('status', '!=', 'published')->orWhere('slug', '>=', $now->copy()->subMonths(18)->format('Y-m')))
            ->orderByDesc('slug')->limit(30)->get();
        $linked = Issue::whereNotNull('editorial_post_id')->pluck('editorial_post_id');
        $shown = $issues->map(fn ($i) => $i->editorialPost()?->id)->filter();
        $others = Post::with('issue')->whereHas('section', fn ($q) => $q->where('slug', 'editorial'))
            ->whereNotIn('id', $linked->merge($shown))->latest('updated_at')->limit(30)->get();

        return ['issues' => $issues, 'others' => $others];
    }

    /** "Write it myself": an empty draft in the editorial section, under my name, tied to the issue; opens for writing. */
    public function write(int $issueId)
    {
        $issue = Issue::findOrFail($issueId);
        if ($existing = $issue->editorialPost()) {
            return redirect(PostResource::getUrl('edit', ['record' => $existing]));
        }
        $section = Section::where('slug', 'editorial')->first();
        if (! $section) {
            Notification::make()->title(__('সম্পাদকীয় বিভাগ পাওয়া যায়নি'))->danger()->send();

            return null;
        }
        $user = auth()->user();
        $author = Author::where('user_id', $user->id)->first()
            ?? Author::create(['user_id' => $user->id, 'display_name' => $user->name, 'slug' => 'editor-'.$user->id]);
        $post = Post::create([
            'title' => 'সম্পাদকীয়: '.$issue->title, 'body_html' => '', 'section_id' => $section->id, 'format' => 'prose', 'language' => 'bn',
            'status' => 'draft', 'slug' => 'sompadokiyo-'.$issue->slug, 'issue_id' => $issue->id,
        ]);
        $post->authors()->sync([$author->id => ['position' => 0]]);
        $issue->forceFill(['editorial_post_id' => $post->id])->save();
        \App\Models\AuditLog::record('editorial.created', $post, ['issue' => $issue->slug]);

        return redirect(PostResource::getUrl('edit', ['record' => $post]));
    }

    /** "Have the AI draft it": the AI editor writes a draft for the owner to edit (D42); done after this page answers. */
    public function draft(int $issueId): void
    {
        abort_unless(auth()->user()?->hasRole(Role::Admin), 403);
        $issue = Issue::findOrFail($issueId);
        if ($issue->editorialPost()) {
            Notification::make()->title(__('এই সংখ্যার সম্পাদকীয় আগেই আছে; খুলে বদলান'))->warning()->send();

            return;
        }
        if ($why = \App\Ai\Claude::blocked()) {
            Notification::make()->title(__('এআই এখন কাজ করতে পারছে না'))->body($why)->warning()->send();

            return;
        }
        $owner = auth()->user();
        dispatch(fn () => EditorialWriter::draft(Issue::findOrFail($issueId), $owner))->afterResponse();
        Notification::make()->title(__('সম্পাদকীয়র খসড়া লেখা হচ্ছে; দু-তিন মিনিট পরে পাতাটি আবার খুলুন'))->success()->send();
    }
}
