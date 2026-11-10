<?php

namespace App\Filament\Pages;

use App\Enums\Role;
use App\Filament\Resources\Posts\PostResource;
use App\Models\Post;
use App\Notifications\RelaunchLetter;
use App\Support\RelaunchLetters as Letters;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * D104: the apology letter to writers of the old unanswered submissions. The owner edits and approves the text
 * once; sending then starts by itself on hatpakha.com, 20 an hour. Answers are shown here.
 */
class RelaunchLetters extends Page
{
    use \App\Filament\Concerns\TranslatedPageLabels;

    protected string $view = 'filament.pages.relaunch-letters';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelopeOpen;

    protected static string|\UnitEnum|null $navigationGroup = 'আরও';

    protected static ?string $navigationLabel = 'পুরনো লেখকদের চিঠি';

    protected static ?string $title = 'পুরনো লেখকদের চিঠি';

    protected static ?int $navigationSort = 22;

    public string $subject = '';

    public string $body = '';

    /** Screening list filter: ask | skip | unread. */
    public string $show = 'ask';

    public int $limit = 60;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(Role::Admin) ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        $yes = Post::where('status', 'pending')->whereNull('submitted_by')->where('relaunch_status', 'yes')->count();

        return $yes ? (string) $yes : null;
    }

    public function mount(): void
    {
        ['subject' => $this->subject, 'body' => $this->body] = Letters::letter();
    }

    public function saveLetter(): void
    {
        $this->validate(['subject' => 'required|string|min:5|max:200', 'body' => 'required|string|min:40|max:5000']);
        Letters::save($this->subject, $this->body);
        Notification::make()->title(__('চিঠি রাখা হয়েছে'))->body(__('পাঠানো শুরু হবে আপনি আবার অনুমোদন দিলে।'))->success()->send();
    }

    public function approve(): void
    {
        Letters::save($this->subject, $this->body);
        Letters::approve(auth()->user());
        Notification::make()->title(__('চিঠি অনুমোদিত'))->body(__('hatpakha.com-এ চালু হলে পাঠানো নিজে থেকে শুরু হবে, ঘণ্টায় ২০টি করে।'))->success()->send();
    }

    public function setWorth(int $id, string $worth): void
    {
        Letters::setWorth(Letters::pieces()->findOrFail($id), $worth);
    }

    public function screenNow(): void
    {
        \App\Ai\TeamJobs::queue('editor', 'screen');
        Notification::make()->title(__('এআই দল কাজটি নিয়েছে'))->body(__('কয়েক মিনিটে এখানে দেখা যাবে।'))->success()->send();
    }

    public function showList(string $show): void
    {
        $this->show = in_array($show, ['ask', 'skip', 'unread'], true) ? $show : 'ask';
        $this->limit = 60;
    }

    public function data(): array
    {
        $sample = Letters::due()->with('user')->first() ?? \App\Models\Author::whereNotNull('user_id')->with('user')->first();
        $titles = $sample ? $sample->posts()->whereIn('posts.id', Letters::pieces()->select('id'))->orderBy('submitted_at')->pluck('title')->all() : [];
        $preview = $sample?->user ? (new RelaunchLetter(['subject' => $this->subject, 'body' => $this->body], $titles ?: ['…'], '#'))->toMail($sample->user)->render() : null;

        return [
            'stats' => Letters::stats(),
            'waiting' => Letters::waitingFor(),
            'approved' => Letters::approved(),
            'preview' => $preview,
            'list' => Letters::pieces()->with('authors')
                ->when($this->show === 'unread', fn ($q) => $q->whereNull('relaunch_worth'), fn ($q) => $q->where('relaunch_worth', $this->show))
                ->orderByDesc('relaunch_score')->orderByDesc('submitted_at')->limit($this->limit)->get(),
            'yes' => Letters::pieces()->with('authors')->where('relaunch_status', 'yes')->orderBy('relaunch_answered_at')->limit(50)->get(),
            'editUrl' => fn (Post $p) => PostResource::getUrl('edit', ['record' => $p]),
        ];
    }
}
