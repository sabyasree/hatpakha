<?php

namespace App\Filament\Pages;

use App\Enums\Role;
use App\Models\Issue;
use App\Ai\Illustrator;
use App\Ai\IssueWatch;
use App\Support\CoverImage;
use App\Support\IssueEbook;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;

/**
 * Owner (2026-11-12): each month's issue in one place, as a checklist: the designer's cover, the editorial, and
 * the magazine itself. Things are made by the AI team automatically (App\Ai\IssueWatch); the owner chooses the
 * cover and publishes the editorial (D42). The EPUB is kept here for the owner only; readers read on the site.
 */
class Ebooks extends Page
{
    use \App\Filament\Concerns\TranslatedPageLabels;

    protected string $view = 'filament.pages.ebooks';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?string $navigationLabel = 'মাসের পত্রিকা';

    protected static ?string $title = 'মাসের পত্রিকা';

    protected static ?string $slug = 'ebooks';

    protected static string|\UnitEnum|null $navigationGroup = 'আরও';

    protected static ?int $navigationSort = 31;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(Role::Editor) ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        $n = rescue(fn () => IssueWatch::current()->filter(fn ($i) => IssueWatch::needs($i))->count(), 0, false);

        return $n ? \App\Support\Bn::num((string) $n) : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public function getViewData(): array
    {
        $current = IssueWatch::current();
        $past = Issue::where('status', 'published')->whereNotIn('id', $current->pluck('id'))->orderByDesc('slug')->limit(12)->get();

        return ['current' => $current, 'past' => $past];
    }

    /** The designer's proposed cover becomes the issue's cover. */
    public function useCover(int $id): void
    {
        abort_unless(auth()->user()?->hasRole(Role::Admin), 403);
        $i = Issue::findOrFail($id);
        abort_unless($i->proposed_cover_path, 404);
        $old = $i->cover_path;
        $i->forceFill(['cover_path' => $i->proposed_cover_path, 'proposed_cover_path' => null])->save();
        if ($old && $old !== $i->cover_path) {
            CoverImage::deleteFiles($old);
        }
        \App\Models\AuditLog::record('issue.cover_chosen', $i, ['by' => auth()->id()]);
        Notification::make()->title(__('প্রচ্ছদ বেছে নেওয়া হল'))->success()->send();
    }

    /** Ask the designer for a (new) cover, or the editor for the editorial draft: done after this page answers. */
    public function makeCover(int $id): void
    {
        abort_unless(auth()->user()?->hasRole(Role::Admin), 403);
        if ($why = Illustrator::blocked()) {
            Notification::make()->title(__('ডিজাইনার এখন কাজ করতে পারছেন না'))->body($why)->warning()->send();

            return;
        }
        dispatch(fn () => Illustrator::forIssue(Issue::findOrFail($id)))->afterResponse();
        Notification::make()->title(__('ডিজাইনার প্রচ্ছদ আঁকছেন; দু-তিন মিনিট পরে পাতাটি আবার খুলুন'))->success()->send();
    }

    public function makeEditorial(int $id): void
    {
        abort_unless(auth()->user()?->hasRole(Role::Admin), 403);
        if ($why = \App\Ai\Claude::blocked()) {
            Notification::make()->title(__('এআই এখন কাজ করতে পারছে না'))->body($why)->warning()->send();

            return;
        }
        $owner = auth()->user();
        dispatch(fn () => \App\Ai\EditorialWriter::draft(Issue::findOrFail($id), $owner))->afterResponse();
        Notification::make()->title(__('সম্পাদকীয়র খসড়া লেখা হচ্ছে; দু-তিন মিনিট পরে পাতাটি আবার খুলুন'))->success()->send();
    }

    public function rebuild(int $id): void
    {
        $i = Issue::findOrFail($id);
        if (! $i->liveEditorial()) {
            Notification::make()->title(__('আগে সম্পাদকীয় প্রকাশ করুন; প্রতিটি ই-বুকে সম্পাদকীয় থাকে'))->warning()->send();

            return;
        }
        IssueEbook::build($i);
        Notification::make()->title(__('ই-বুক নতুন করে তৈরি হল'))->success()->send();
    }

    public function download(int $id)
    {
        $i = Issue::findOrFail($id);
        abort_unless($i->ebook_path && Storage::disk('local')->exists($i->ebook_path), 404);

        return Storage::disk('local')->download($i->ebook_path, 'hatpakha-'.$i->slug.'.epub', ['Content-Type' => 'application/epub+zip']);
    }
}
