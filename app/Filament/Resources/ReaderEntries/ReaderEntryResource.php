<?php

namespace App\Filament\Resources\ReaderEntries;

use App\Enums\Role;
use App\Filament\Resources\ReaderEntries\Pages\ListReaderEntries;
use App\Models\ReaderEntry;
use App\Models\ReaderPrompt;
use App\Support\Bn;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** D175: পাঠকের কলম in the desk. What readers sent, what the AI thinks, the picks; the owner publishes. */
class ReaderEntryResource extends Resource
{
    use \App\Filament\Concerns\TranslatedResourceLabels;

    protected static ?string $model = ReaderEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static ?string $modelLabel = 'পাঠকের কলম';

    protected static ?string $pluralModelLabel = 'পাঠকের কলম';

    protected static ?string $slug = 'reader-entries';

    protected static ?int $navigationSort = 6;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(Role::Editor) ?? false;
    }

    public static function canCreate(): bool { return false; }

    public static function canEdit(Model $record): bool { return false; }

    public static function canDelete(Model $record): bool { return false; }

    public static function canDeleteAny(): bool { return false; }

    /** A week waiting for the owner's "publish". */
    public static function getNavigationBadge(): ?string
    {
        return ReaderPrompt::where('status', 'closed')->whereHas('entries', fn ($q) => $q->where('status', 'picked'))->exists() ? '!' : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        $can = fn (ReaderEntry $r) => $r->prompt?->status !== 'published';

        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('prompt'))
            ->defaultSort(fn ($query) => $query->orderByDesc('reader_prompt_id')->orderByRaw("case status when 'picked' then 0 when 'ok' then 1 when 'new' then 2 when 'flagged' then 3 else 4 end")->orderByDesc('ai_score')->orderBy('id'))
            ->emptyStateHeading(__('এখনও কোনো লেখা আসেনি'))
            ->emptyStateDescription(__('প্রতি সোমবার নতুন বিষয় খোলে আর পাঠকদের ডাকার পোস্ট তৈরি হয়। পাঠকেরা /kolom/ পাতায় লেখেন।'))
            ->columns([
                TextColumn::make('body')->label(__('লেখা'))->wrap()->searchable()
                    ->description(fn (ReaderEntry $record) => '— '.$record->name.($record->anonymous ? ' ('.__('নাম গোপন চান').')' : '')),
                TextColumn::make('prompt.title')->label(__('বিষয়'))->wrap()->toggleable(),
                TextColumn::make('status')->label(__('অবস্থা'))->badge()
                    ->formatStateUsing(fn ($state) => __(ReaderEntry::STATUSES[$state] ?? $state))
                    ->color(fn ($state) => ['picked' => 'success', 'ok' => 'info', 'flagged' => 'danger', 'rejected' => 'gray'][$state] ?? 'warning'),
                TextColumn::make('ai_score')->label(__('এআই'))->placeholder('—')
                    ->formatStateUsing(fn ($state) => Bn::num((string) $state).__(' / ১০'))
                    ->description(fn (ReaderEntry $record) => $record->ai_note),
                TextColumn::make('created_at')->label(__('কবে'))->since()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('reader_prompt_id')->label(__('বিষয়'))->relationship('prompt', 'title'),
                SelectFilter::make('status')->label(__('অবস্থা'))->options(\App\Support\Lang::t(ReaderEntry::STATUSES)),
            ])
            ->recordActions([
                Action::make('pick')->label(__('বাছাইয়ে নিন'))->icon('heroicon-o-star')->color('success')
                    ->visible(fn (ReaderEntry $record) => $can($record) && $record->status !== 'picked')
                    ->action(fn (ReaderEntry $record) => $record->update(['status' => 'picked'])),
                Action::make('unpick')->label(__('বাছাই থেকে সরান'))->icon('heroicon-o-minus-circle')->color('gray')
                    ->visible(fn (ReaderEntry $record) => $can($record) && $record->status === 'picked')
                    ->action(fn (ReaderEntry $record) => $record->update(['status' => 'ok'])),
                Action::make('reject')->label(__('বাদ'))->icon('heroicon-o-x-mark')->color('danger')
                    ->visible(fn (ReaderEntry $record) => $record->status !== 'rejected')
                    ->requiresConfirmation(fn (ReaderEntry $record) => $record->prompt?->status === 'published')
                    ->modalDescription(__('লেখাটি পাতা থেকে সরে যাবে।'))
                    ->action(fn (ReaderEntry $record) => $record->update(['status' => 'rejected'])),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListReaderEntries::route('/')];
    }
}
