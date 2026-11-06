<?php

namespace App\Filament\Resources\Issues\Pages;

use App\Ai\IssuePlanner;
use App\Enums\Role;
use App\Filament\Resources\Issues\IssueResource;
use App\Filament\Resources\Posts\PostResource;
use App\Models\Issue;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Carbon;

class EditIssue extends EditRecord
{
    protected static string $resource = IssueResource::class;

    protected function getHeaderActions(): array
    {
        $isAdmin = fn () => auth()->user()?->hasRole(Role::Admin) ?? false;

        return [
            // D68: the issue as a book: EPUB for Google Play Books, print-ready page for PDF, and the listing pack.
            \Filament\Actions\ActionGroup::make([
                Action::make('epub')->label(__('ই-বুক (EPUB) নামান'))->icon('heroicon-o-arrow-down-tray')
                    ->action(function (Issue $record) {
                        abort_if(\App\Support\IssueBook::pieces($record)->isEmpty(), 404);
                        $path = \App\Support\IssueBook::epub($record);

                        return response()->download($path, 'hatpakha-'.$record->slug.'.epub', ['Content-Type' => 'application/epub+zip'])->deleteFileAfterSend();
                    }),
                Action::make('print')->label(__('ছাপার PDF বানান (ব্রাউজারে)'))->icon('heroicon-o-printer')
                    ->url(fn (Issue $record) => route('issue.print', $record))->openUrlInNewTab(),
                Action::make('listing')->label(__('Google Play Books-এর তথ্য'))->icon('heroicon-o-book-open')->slideOver()
                    ->fillForm(fn (Issue $record) => ['book_description' => $record->book_description, 'book_keywords' => $record->book_keywords])
                    ->schema(fn (Issue $record) => [
                        \Filament\Forms\Components\Placeholder::make('pack')->hiddenLabel()->content(new \Illuminate\Support\HtmlString(view('filament.book-listing', ['issue' => $record, 'pieces' => \App\Support\IssueBook::pieces($record)])->render())),
                        \Filament\Forms\Components\Textarea::make('book_description')->label(__('বইয়ের বিবরণ (দোকানে দেখা যাবে)'))->rows(7)->maxLength(4000),
                        \Filament\Forms\Components\TextInput::make('book_keywords')->label(__('খোঁজার শব্দ (কমা দিয়ে)'))->maxLength(300),
                    ])
                    ->extraModalFooterActions(fn (Action $action) => [$action->makeModalSubmitAction('draft', ['draft' => true])->label(__('কপিরাইটারকে দিয়ে লেখান'))->color('gray')])
                    ->modalSubmitActionLabel(__('রাখুন'))
                    ->action(function (Issue $record, array $data, array $arguments) {
                        if ($arguments['draft'] ?? false) {
                            $ok = \App\Support\IssueBook::draftListing($record);
                            Notification::make()->title($ok ? __('খসড়া তৈরি; আবার খুলে দেখুন') : __('এখন হলো না; এআই থামানো আছে কি না দেখুন'))->{$ok ? 'success' : 'warning'}()->send();

                            return;
                        }
                        $record->forceFill(['book_description' => strip_tags((string) $data['book_description']), 'book_keywords' => strip_tags((string) $data['book_keywords'])])->save();
                        Notification::make()->title(__('রাখা হয়েছে'))->success()->send();
                    }),
            ])->label(__('বই'))->icon('heroicon-o-book-open')->button()->color('gray')
                ->visible(fn () => $this->record->status === 'published'),
            // D42: the owner gives the final approval of the AI editor's issue.
            Action::make('approveIssue')->label(__('সংখ্যা অনুমোদন'))->icon('heroicon-o-check-badge')->color('success')
                ->visible(fn (Issue $record) => $isAdmin() && $record->status === 'proposed')
                ->modalHeading(fn (Issue $record) => __('অনুমোদন: ').$record->title)
                ->schema([
                    CheckboxList::make('posts')->label(__('লেখা (টিক তুলে দিলে বাদ যাবে)'))
                        ->options(fn (Issue $record) => \App\Models\Post::whereIn('id', (array) ($record->ai_plan['post_ids'] ?? []))->pluck('title', 'id')->all())
                        ->default(fn (Issue $record) => (array) ($record->ai_plan['post_ids'] ?? []))->columns(1)->bulkToggleable(),
                    DateTimePicker::make('at')->label(__('প্রকাশের দিন'))->seconds(false)->native(false)->displayFormat('d/m/Y, h:i A')->required()
                        ->default(fn (Issue $record) => max(now(), Carbon::parse($record->slug.'-01 09:00', 'Asia/Kolkata')->utc()))
                        ->helperText(__('উৎসব সংখ্যা উৎসবের দিনে, অন্য সংখ্যা মাসের ১ তারিখে (PLAN.md)।')),
                ])
                ->action(function (Issue $record, array $data) {
                    IssuePlanner::approve($record, auth()->user(), Carbon::parse($data['at']), array_map('intval', (array) $data['posts']));
                    $draft = $record->editorial && $record->editorial->status !== 'published';
                    Notification::make()->title(__('সংখ্যা অনুমোদিত'))->body($draft ? __('সম্পাদকীয়টি এখনও খসড়া: সম্পাদনা করে প্রকাশ করুন।') : null)->success()->send();
                    $this->refreshFormData(['status']);
                }),
            Action::make('editorial')->label(__('সম্পাদকীয় খসড়া'))->icon('heroicon-o-pencil')->color('gray')
                ->visible(fn (Issue $record) => $record->editorial_post_id !== null)
                ->url(fn (Issue $record) => PostResource::getUrl('edit', ['record' => $record->editorial_post_id])),
            Action::make('rePropose')->label(__('নতুন প্রস্তাব'))->icon('heroicon-o-arrow-path')->color('gray')
                ->visible(fn (Issue $record) => $isAdmin() && $record->status === 'proposed')
                ->requiresConfirmation()->modalDescription(__('এআই সম্পাদক আবার বেছে নেবে (সম্পাদকীয় খসড়াও নতুন হবে)।'))
                ->action(function (Issue $record) {
                    [$issue, $err] = IssuePlanner::propose(auth()->user(), Carbon::parse($record->slug.'-01'));
                    $issue ? Notification::make()->title(__('নতুন প্রস্তাব তৈরি'))->success()->send()
                           : Notification::make()->title(__('হলো না'))->body($err)->warning()->send();
                    $this->refreshFormData(['title', 'theme_key', 'kind']);
                }),
            DeleteAction::make()->visible($isAdmin),
        ];
    }
}
