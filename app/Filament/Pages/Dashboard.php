<?php

namespace App\Filament\Pages;

use App\Enums\Role;
use App\Filament\Resources\Comments\Tables\CommentsTable;
use App\Filament\Resources\Posts\PostResource;
use App\Models\AiUsage;
use App\Models\Comment;
use App\Models\Post;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;

/**
 * "আজকের কাজ": the one page an editor needs. Everything waiting for a person is listed here,
 * oldest first, with the action next to it. Nothing is decided automatically (CLAUDE.md §2).
 */
class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'আজকের কাজ';

    protected static ?string $navigationLabel = 'আজকের কাজ';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-inbox';

    protected string $view = 'filament.pages.today';

    public function getWidgets(): array
    {
        return [];
    }

    /** @return array<string, mixed> */
    public function today(): array
    {
        $isAdmin = auth()->user()?->hasRole(Role::Admin) ?? false;

        return [
            'submissions' => Post::with(['authors', 'section', 'latestAiReview'])
                ->where('status', 'pending')->whereNotNull('submitted_by')->orderBy('submitted_at')->limit(20)->get(),
            'comments' => Comment::with('post')->where('status', 'pending')->oldest()->limit(15)->get(),
            'commentsTotal' => Comment::where('status', 'pending')->count(),
            'withWriter' => Post::where('status', 'changes')->count(),
            'scheduled' => Post::where('status', 'scheduled')->where('published_at', '>', now())->orderBy('published_at')->limit(5)->get(),
            'publishedWeek' => Post::published()->where('published_at', '>=', now()->startOfWeek())->count(),
            'backlog' => Post::where('status', 'pending')->whereNull('submitted_by')->count(),
            'isAdmin' => $isAdmin,
            'auditOpen' => $isAdmin ? Post::whereIn('audit_label', ['merge', 'noindex', 'remove'])->whereNull('audit_decision')->count() : 0,
            'aiSpent' => $isAdmin ? AiUsage::spentThisMonthUsd() : 0,
            'aiEnabled' => (bool) config('ai.enabled'),
            'aiPaused' => \App\Ai\AiSwitch::paused(),
        ];
    }

    public function editUrl(Post $post): string
    {
        return PostResource::getUrl('edit', ['record' => $post]);
    }

    public function pauseAi(): void
    {
        \App\Ai\AiSwitch::set(true);
        Notification::make()->title('এআই থামানো হয়েছে')->success()->send();
    }

    public function resumeAi(): void
    {
        \App\Ai\AiSwitch::set(false);
        Notification::make()->title('এআই আবার চালু')->success()->send();
    }

    public function approveComment(int $id): void
    {
        $this->moderate($id, 'approved', 'মন্তব্য প্রকাশিত');
    }

    public function spamComment(int $id): void
    {
        $this->moderate($id, 'spam', 'স্প্যামে সরানো হয়েছে');
    }

    private function moderate(int $id, string $status, string $message): void
    {
        abort_unless(auth()->user()?->hasRole(Role::Editor), 403);
        $comment = Comment::where('status', 'pending')->find($id);
        if ($comment) {
            CommentsTable::setStatus($comment, $status);
            Notification::make()->title($message)->success()->send();
        }
    }
}
