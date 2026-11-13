<?php

namespace App\Filament\Resources\WordReports;

use App\Enums\Role;
use App\Filament\Resources\WordReports\Pages\ListWordReports;
use App\Models\WordReport;
use App\Support\WordReports;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** D179: what players reported in the games and what was done about it by itself; the desk can undo a change. */
class WordReportResource extends Resource
{
    use \App\Filament\Concerns\TranslatedResourceLabels;

    protected static ?string $model = WordReport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static ?string $modelLabel = 'শব্দের অভিযোগ';

    protected static ?string $pluralModelLabel = 'শব্দের অভিযোগ';

    protected static ?string $slug = 'word-reports';

    protected static ?int $navigationSort = 8;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(Role::Editor) ?? false;
    }

    public static function canCreate(): bool { return false; }

    public static function canEdit(Model $record): bool { return false; }

    public static function canDelete(Model $record): bool { return false; }

    public static function canDeleteAny(): bool { return false; }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->emptyStateHeading(__('এখনও কেউ কোনো শব্দে ভুল জানাননি'))
            ->emptyStateDescription(__('খেলার শেষে খেলোয়াড়েরা শব্দ, সূত্র বা ধাঁধায় ভুল জানাতে পারেন। এআই সম্পাদক ঘণ্টায় একবার দেখে নিজে থেকে ঠিক করে বা সরিয়ে দেয়; এখানে তার হিসেব থাকে।'))
            ->columns([
                TextColumn::make('word')->label(__('শব্দ'))->weight('bold')->searchable()
                    ->description(fn (WordReport $record) => $record->game === 'riddle' ? __('ধাঁধা') : __('শব্দ-খেলা ও শব্দজব্দ')),
                TextColumn::make('reason')->label(__('কী ভুল'))->formatStateUsing(fn ($state) => __(WordReports::REASONS[$state] ?? $state))
                    ->description(fn (WordReport $record) => $record->note)->wrap(),
                TextColumn::make('status')->label(__('কী হল'))->badge()
                    ->formatStateUsing(fn ($state) => __(WordReports::STATUSES[$state] ?? $state))
                    ->color(fn ($state) => ['fixed' => 'success', 'removed' => 'danger', 'kept' => 'gray'][$state] ?? 'warning')
                    ->description(fn (WordReport $record) => $record->outcome)->wrap(),
                TextColumn::make('created_at')->label(__('কবে'))->since(),
            ])
            ->filters([SelectFilter::make('status')->label(__('কী হল'))->options(\App\Support\Lang::t(WordReports::STATUSES))])
            ->recordActions([
                Action::make('undo')->label(__('আগের মতো করুন'))->icon('heroicon-o-arrow-uturn-left')->color('gray')
                    ->visible(fn (WordReport $record) => in_array($record->status, ['fixed', 'removed'], true) && auth()->user()?->hasRole(Role::Admin))
                    ->requiresConfirmation()->modalDescription(__('শব্দটি আর তার সূত্র আগের মতো হয়ে খেলায় ফিরবে।'))
                    ->action(function (WordReport $record) {
                        WordReports::undo($record->game, $record->word);
                        Notification::make()->title(__('আগের মতো করা হয়েছে'))->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListWordReports::route('/')];
    }
}
