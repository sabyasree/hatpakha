<?php

namespace App\Filament\Resources\SiteErrors;

use App\Enums\Role;
use App\Filament\Resources\SiteErrors\Pages\ListSiteErrors;
use App\Models\SiteError;
use App\Support\Bn;
use App\Support\SiteErrors;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** D191: errors the site ran into, for the owner to see the same day (and to pass on for fixing). Admins only. */
class SiteErrorResource extends Resource
{
    use \App\Filament\Concerns\TranslatedResourceLabels;

    protected static ?string $model = SiteError::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static ?string $modelLabel = 'সাইটের ত্রুটি';

    protected static ?string $pluralModelLabel = 'সাইটের ত্রুটি';

    protected static ?string $slug = 'site-errors';

    protected static ?int $navigationSort = 99;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(Role::Admin) ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        return ($n = SiteErrors::unseen()) ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function canCreate(): bool { return false; }

    public static function canEdit(Model $record): bool { return false; }

    public static function canDelete(Model $record): bool { return false; }

    public static function canDeleteAny(): bool { return false; }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('last_at', 'desc')
            ->emptyStateHeading(__('কোনো ত্রুটি নেই'))
            ->emptyStateDescription(__('গত তিরিশ দিনে সাইট কোনো ভুলে পড়েনি, বা পড়লেও তা এখানে ওঠার মতো ছিল না।'))
            ->columns([
                TextColumn::make('area')->label(__('কোথায়'))->formatStateUsing(fn (SiteError $record) => SiteErrors::where($record))->badge()
                    ->color(fn ($state) => ['desk' => 'warning', 'job' => 'gray'][$state] ?? 'danger')
                    ->description(fn (SiteError $record) => $record->path),
                TextColumn::make('message')->label(__('কী হয়েছিল'))->wrap()->weight(fn (SiteError $record) => $record->seen_at ? null : 'bold')
                    ->description(fn (SiteError $record) => $record->what.' · '.$record->place),
                TextColumn::make('count')->label(__('কতবার'))->formatStateUsing(fn ($state) => Bn::num((string) $state))->sortable(),
                TextColumn::make('last_at')->label(__('শেষ কবে'))->since()->sortable()
                    ->description(fn (SiteError $record) => __('প্রথম').': '.Bn::num($record->first_at->timezone('Asia/Kolkata')->format('d/m H:i'))),
            ])
            ->filters([SelectFilter::make('area')->label(__('কোথায়'))->options(['desk' => __('ডেস্কে'), 'site' => __('সাইটে'), 'job' => __('পেছনের কাজে')])])
            ->recordActions([
                Action::make('seen')->label(__('দেখেছি'))->icon('heroicon-o-check')->color('gray')
                    ->visible(fn (SiteError $record) => $record->seen_at === null)
                    ->action(fn (SiteError $record) => $record->forceFill(['seen_at' => now()])->save()),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListSiteErrors::route('/')];
    }
}
