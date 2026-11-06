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
            ->fillForm(fn (array $arguments) => ['label' => 'pick', 'praise' => Author::find($arguments['author'] ?? 0)?->honour_note, 'tell' => true])
            ->schema(fn (array $arguments) => [
                Select::make('label')->label(__('কোন সম্মান'))->required()->options(\App\Support\Lang::t(HonourJobs::LABELS)),
                Textarea::make('praise')->label(__('সম্পাদকের কথা (লেখক-পাতায় দেখা যাবে)'))->rows(5)->maxLength(800)
                    ->hintAction(Action::make('draft')->label(__('এআই-কে লিখতে বলুন'))->icon('heroicon-o-sparkles')
                        ->action(function ($set) use ($arguments) {
                            $text = ($a = Author::find($arguments['author'] ?? 0)) ? HonourJobs::draftPraise($a) : null;
                            $text ? $set('praise', $text) : Notification::make()->title(__('এখন লেখা গেল না; এআই থামানো আছে কি না দেখুন'))->warning()->send();
                        })),
                Toggle::make('tell')->label(__('লেখককে অভিনন্দনের ই-মেল পাঠান (অ্যাকাউন্ট থাকলে)')),
            ])
            ->modalSubmitActionLabel(__('সম্মান দিন'))
            ->action(function (array $data, array $arguments) {
                $author = Author::where('is_desk', false)->findOrFail($arguments['author'] ?? 0);
                HonourJobs::give($author, $data['label'], (string) $data['praise'], (bool) $data['tell']);
                \App\Ai\Marketer::spotlight($author);
                Notification::make()->title(__('সম্মান দেওয়া হয়েছে'))->body(__('প্রথম পাতা আর লেখকের পাতায় দেখুন। মার্কেটিং পাতায় একটি পোস্টও তৈরি হচ্ছে।'))->success()->send();
            });
    }

    public function unhonour(int $id): void
    {
        Author::findOrFail($id)->forceFill(['honour_label' => null, 'honour_note' => null, 'honoured_at' => null])->save();
        \App\Models\AuditLog::record('author.unhonoured', null, ['author' => $id]);
        Notification::make()->title(__('সম্মান সরানো হয়েছে'))->send();
    }

    public function data(): array
    {
        return [
            'honoured' => Author::whereNotNull('honour_label')->latest('honoured_at')->get(),
            'candidates' => HonourJobs::candidates(12),
            'proposal' => \App\Models\EditorTask::open('honour')->latest()->first(),
        ];
    }
}
