<?php

namespace App\Filament\Pages;

use App\Enums\Role;
use App\Models\EditorTask;
use App\Support\Bn;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * D257 (owner, 2026-11-23: "for writers' questions from the chat I need a separate section in the menu, where I can see
 * all the questions and answer"). Every question the helper could not answer (D52), waiting ones first, with the answer
 * kept beside it (D224). An answer goes to the writer by e-mail only when the editor presses "পাঠান".
 */
class WriterQuestions extends Page implements HasTable
{
    use InteractsWithTable;
    use \App\Filament\Concerns\TranslatedPageLabels;

    protected string $view = 'filament.pages.writer-questions';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $navigationLabel = 'লেখকদের প্রশ্ন';

    protected static ?string $title = 'লেখকদের প্রশ্ন';

    protected static ?string $slug = 'writer-questions';

    protected static ?int $navigationSort = 12;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(Role::Editor) ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        $n = EditorTask::open('question')->count();

        return $n ? Bn::num((string) $n) : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public function getSubheading(): ?string
    {
        return __('চ্যাটে লেখকেরা যা জিজ্ঞেস করেছেন আর সহায়ক উত্তর দিতে পারেনি। উত্তর লিখে “পাঠান” টিপলে লেখক ই-মেলে পাবেন।');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(EditorTask::query()->with('subject')->where('type', 'question'))
            ->defaultSort(fn ($query) => $query->orderByRaw("CASE WHEN status = 'open' THEN 0 ELSE 1 END")->orderByDesc('id'))
            ->columns([
                TextColumn::make('status')->label(__('অবস্থা'))->badge()
                    ->formatStateUsing(fn (string $state) => ['open' => __('উত্তর বাকি'), 'dismissed' => __('উত্তর লাগেনি')][$state] ?? __('উত্তর দেওয়া'))
                    ->color(fn (string $state) => $state === 'open' ? 'danger' : 'gray'),
                TextColumn::make('payload.name')->label(__('লেখক'))->description(fn (EditorTask $record) => $record->payload['email'] ?? null)->wrap(),
                TextColumn::make('payload.question')->label(__('প্রশ্ন'))->wrap()->lineClamp(4),
                TextColumn::make('payload.reply')->label(__('আপনার উত্তর'))->wrap()->lineClamp(3)->placeholder('—'),
                TextColumn::make('created_at')->label(__('কবে'))->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('অবস্থা'))->options(['open' => __('উত্তর বাকি'), 'done' => __('উত্তর দেওয়া'), 'dismissed' => __('উত্তর লাগেনি')]),
            ])
            ->recordActions([
                Action::make('answer')->label(fn (EditorTask $record) => filled($record->payload['reply'] ?? null) ? __('আবার উত্তর পাঠান') : __('উত্তর দিন'))
                    ->icon('heroicon-o-paper-airplane')->button()->size('sm')
                    ->color(fn (EditorTask $record) => $record->status === 'open' ? 'primary' : 'gray')
                    ->visible(fn (EditorTask $record) => $record->subject instanceof \App\Models\User)
                    ->modalHeading(__('লেখকের প্রশ্ন'))
                    ->fillForm(fn (EditorTask $record) => ['message' => $record->payload['reply'] ?? ''])
                    ->schema(fn (EditorTask $record) => [
                        Placeholder::make('q')->label(($record->payload['name'] ?? '').' · '.($record->payload['email'] ?? ''))->content($record->payload['question'] ?? ''),
                        Textarea::make('message')->label(__('উত্তর'))->required()->rows(7)->maxLength(2000),
                    ])
                    ->modalSubmitActionLabel(__('পাঠান'))
                    ->action(function (EditorTask $record, array $data) {
                        \App\Support\WriterAnswer::send($record, $data['message']);
                        Notification::make()->title(__('পাঠানো হয়েছে'))->success()->send();
                    }),
                Action::make('noNeed')->label(__('উত্তর লাগবে না'))->icon('heroicon-o-check')->size('sm')->color('gray')
                    ->visible(fn (EditorTask $record) => $record->status === 'open')
                    ->requiresConfirmation()->modalDescription(__('প্রশ্নটি তালিকায় থাকবে, লেখক কোনো ই-মেল পাবেন না।'))
                    ->action(fn (EditorTask $record) => $record->close('dismissed')),
            ])
            ->emptyStateHeading(__('এখনও কোনো প্রশ্ন আসেনি'));
    }
}
