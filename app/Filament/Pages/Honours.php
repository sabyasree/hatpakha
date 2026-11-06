<?php

namespace App\Filament\Pages;

use App\Ai\Honours as HonourJobs;
use App\Enums\Role;
use App\Models\Author;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * Honouring writers (D66, D76), all in one place: who is honoured now (and where readers see it), a shortlist
 * from the archive by the numbers, and the honour itself: a label, the editor's words (the AI can draft them),
 * an optional note to the writer, and a সম্মাননা শংসাপত্র.
 */
class Honours extends Page
{
    use \App\Filament\Concerns\TranslatedPageLabels;

    protected string $view = 'filament.pages.honours';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static ?string $navigationLabel = 'সম্মানিত লেখক';

    protected static ?string $title = 'সম্মানিত লেখক';

    protected static ?int $navigationSort = 5;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(Role::Admin) ?? false;
    }

    public function honourAction(): Action
    {
        return Action::make('honour')->label(__('সম্মান দিন'))->icon('heroicon-o-star')->size('sm')->color('warning')
            ->modalHeading(fn (array $arguments) => __('সম্মান দিন').': '.(Author::find($arguments['author'] ?? 0)?->publicName() ?? ''))
            ->modalDescription(__('লেখকের পাতায়, প্রতিটি লেখার নিচের লেখক-পরিচিতিতে আর প্রথম পাতার "সম্মানিত লেখক" অংশে দেখা যাবে। সঙ্গে একটি সম্মাননা শংসাপত্র তৈরি হবে।'))
            ->fillForm(function (array $arguments) {
                $a = Author::find($arguments['author'] ?? 0);
                $best = $a ? (HonourJobs::candidates(60)->firstWhere('author_id', $a->id)['best_id'] ?? $a->posts()->published()->latest('published_at')->value('posts.id')) : null;

                return ['label' => 'pick', 'praise' => $a?->honour_note, 'tell' => true, 'post' => $a?->honour_post_id ?? $best, 'quote' => $a?->honour_quote];
            })
            ->schema(fn (array $arguments) => [
                Select::make('label')->label(__('কোন সম্মান'))->required()->options(\App\Support\Lang::t(HonourJobs::LABELS)),
                Textarea::make('praise')->label(__('সম্পাদকের কথা (লেখক-পাতায় দেখা যাবে)'))->rows(5)->maxLength(800)
                    ->hintAction(Action::make('draft')->label(__('এআই-কে লিখতে বলুন'))->icon('heroicon-o-sparkles')
                        ->action(function ($set) use ($arguments) {
                            $text = ($a = Author::find($arguments['author'] ?? 0)) ? HonourJobs::draftPraise($a) : null;
                            $text ? $set('praise', $text) : Notification::make()->title(__('এখন লেখা গেল না; এআই থামানো আছে কি না দেখুন'))->warning()->send();
                        })),
                // D78: the home page spotlight shows one of their pieces and one line copied exactly from it.
                Select::make('post')->label(__('প্রথম পাতায় কোন লেখাটি দেখাবে'))->searchable()
                    ->options(fn () => \App\Models\Post::published()->whereHas('authors', fn ($q) => $q->whereKey($arguments['author'] ?? 0))->latest('published_at')->limit(200)->pluck('title', 'id')),
                Textarea::make('quote')->label(__('লেখা থেকে একটি লাইন (হুবহু)'))->rows(2)->maxLength(300)
                    ->helperText(__('লেখায় হুবহু না থাকলে দেখানো হবে না।'))
                    ->hintAction(Action::make('pickLine')->label(__('এআই-কে বাছতে বলুন'))->icon('heroicon-o-sparkles')
                        ->action(function ($get, $set) {
                            $line = ($p = \App\Models\Post::published()->find($get('post'))) ? HonourJobs::pickQuote($p) : null;
                            $line ? $set('quote', $line) : Notification::make()->title(__('এখন বাছা গেল না; আগে লেখাটি বাছুন, বা এআই থামানো আছে কি না দেখুন'))->warning()->send();
                        })),
                Toggle::make('tell')->label(__('লেখককে অভিনন্দনের ই-মেল পাঠান (অ্যাকাউন্ট থাকলে)')),
            ])
            ->modalSubmitActionLabel(__('সম্মান দিন'))
            ->action(function (array $data, array $arguments) {
                $author = Author::where('is_desk', false)->findOrFail($arguments['author'] ?? 0);
                HonourJobs::give($author, $data['label'], (string) $data['praise'], (bool) $data['tell'], $data['post'] ? (int) $data['post'] : null, $data['quote'] ?? null);
                if (filled($data['quote'] ?? null) && ! $author->fresh()->honour_quote) {
                    Notification::make()->title(__('লাইনটি লেখায় হুবহু পাওয়া যায়নি, তাই দেখানো হবে না।'))->warning()->send();
                }
                \App\Ai\Marketer::spotlight($author);
                Notification::make()->title(__('সম্মান দেওয়া হয়েছে'))->body(__('প্রথম পাতা আর লেখকের পাতায় দেখুন। মার্কেটিং পাতায় একটি পোস্টও তৈরি হচ্ছে।'))->success()->send();
            });
    }

    /** D77: the AI editor reads a sample of the shortlisted writers' work; the cost is shown first. */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('read')->label(__('এআই দিয়ে লেখা পড়ান'))->icon('heroicon-o-book-open')
                ->modalHeading(__('শীর্ষ লেখকদের লেখা পড়ানো'))
                ->modalDescription(function () {
                    $pool = HonourJobs::toRead(20);

                    return $pool->isEmpty()
                        ? __('সবার লেখা গত ৯০ দিনের মধ্যে পড়া হয়েছে। আবার পড়াতে চাইলে নিচে "আবার পড়ুক" চালু করুন।')
                        : __('এআই সম্পাদক :n জন লেখকের ৫টি করে লেখা (৩টি নতুন, ২টি পুরনো) পড়ে লেখার মান দেখবে। আনুমানিক খরচ $:cost। কয়েক মিনিট লাগবে।', ['n' => \App\Support\Bn::num((string) $pool->count()), 'cost' => HonourJobs::estimate($pool)]);
                })
                ->schema([Toggle::make('redo')->label(__('আগে পড়া লেখকদেরও আবার পড়ুক'))->helperText(__('খরচ বাড়বে।'))])
                ->modalSubmitActionLabel(__('পড়া শুরু করুন'))
                ->action(function (array $data) {
                    \App\Ai\TeamJobs::queue('editor', 'read_writers', null, (bool) ($data['redo'] ?? false));
                    Notification::make()->title(__('পড়া শুরু হচ্ছে; কয়েক মিনিট পরে এই পাতা আবার খুলুন।'))->success()->send();
                }),
        ];
    }

    public function unhonour(int $id): void
    {
        Author::findOrFail($id)->forceFill(['honour_label' => null, 'honour_note' => null, 'honoured_at' => null, 'honour_post_id' => null, 'honour_quote' => null])->save();
        \App\Models\AuditLog::record('author.unhonoured', null, ['author' => $id]);
        Notification::make()->title(__('সম্মান সরানো হয়েছে'))->send();
    }

    /** D78: milestones go on the home page only after the owner approves. */
    public function milestone(int $id, bool $approve): void
    {
        \App\Models\Milestone::findOrFail($id)->forceFill(['status' => $approve ? 'approved' : 'dismissed', 'approved_at' => $approve ? now() : null])->save();
        Notification::make()->title($approve ? __('প্রথম পাতায় দেখা যাবে') : __('সরানো হয়েছে'))->success()->send();
    }

    public function data(): array
    {
        return [
            'honoured' => Author::whereNotNull('honour_label')->latest('honoured_at')->get(),
            'candidates' => HonourJobs::candidates(12),
            'milestones' => \App\Models\Milestone::with('author', 'post')->whereIn('status', ['proposed', 'approved'])->where('reached_at', '>=', now()->subDays(60))->latest('reached_at')->get(),
            'readCount' => Author::whereNotNull('quality_at')->count(),
            'reading' => \App\Models\TeamRequest::where('job', 'read_writers')->whereIn('status', ['queued', 'running'])->exists(),
            'proposal' => \App\Models\EditorTask::open('honour')->latest()->first(),
        ];
    }
}
