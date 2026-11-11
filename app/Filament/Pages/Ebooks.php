<?php

namespace App\Filament\Pages;

use App\Enums\Role;
use App\Models\Issue;
use App\Support\IssueEbook;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;

/**
 * Owner (2026-11-12): the issues' e-books in one place. Each is built automatically every night when its pieces change;
 * the owner opens the magazine to check it, downloads the EPUB if they like, and approves it for readers.
 */
class Ebooks extends Page
{
    use \App\Filament\Concerns\TranslatedPageLabels;

    protected string $view = 'filament.pages.ebooks';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?string $navigationLabel = 'ই-বুক';

    protected static ?string $title = 'সংখ্যার ই-বুক';

    protected static string|\UnitEnum|null $navigationGroup = 'আরও';

    protected static ?int $navigationSort = 31;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(Role::Editor) ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        $n = Issue::where('status', 'published')->whereNotNull('ebook_path')->get()->filter(fn ($i) => IssueEbook::waiting($i))->count();

        return $n ? \App\Support\Bn::num((string) $n) : null;
    }

    public function getViewData(): array
    {
        return ['issues' => Issue::where('status', 'published')->orderByDesc('slug')->limit(36)->get()];
    }

    public function approve(int $id): void
    {
        abort_unless(auth()->user()?->hasRole(Role::Admin), 403);
        IssueEbook::approve(Issue::findOrFail($id), auth()->user());
        Notification::make()->title(__('অনুমোদিত: পাঠকেরা এখন সংখ্যার পাতা থেকে ই-বুক নামাতে পারবেন'))->success()->send();
    }

    public function withdraw(int $id): void
    {
        abort_unless(auth()->user()?->hasRole(Role::Admin), 403);
        IssueEbook::withdraw(Issue::findOrFail($id));
        Notification::make()->title(__('ই-বুক পাঠকদের কাছ থেকে সরানো হল'))->success()->send();
    }

    public function rebuild(int $id): void
    {
        IssueEbook::build(Issue::findOrFail($id));
        Notification::make()->title(__('নতুন করে তৈরি হল; দেখে অনুমোদন দিন'))->success()->send();
    }

    public function download(int $id)
    {
        $i = Issue::findOrFail($id);
        abort_unless($i->ebook_path && Storage::disk('local')->exists($i->ebook_path), 404);

        return Storage::disk('local')->download($i->ebook_path, 'hatpakha-'.$i->slug.'.epub', ['Content-Type' => 'application/epub+zip']);
    }
}
