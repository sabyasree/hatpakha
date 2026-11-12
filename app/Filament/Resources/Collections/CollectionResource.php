<?php

namespace App\Filament\Resources\Collections;

use App\Enums\Role;
use App\Filament\Resources\Collections\Pages\EditCollection;
use App\Filament\Resources\Collections\Pages\ListCollections;
use App\Models\AuditLog;
use App\Models\Collection;
use App\Models\EditorTask;
use App\Models\Post;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** D97: themed collections. The archivist proposes; the owner orders, edits and approves (nothing is public before). */
class CollectionResource extends Resource
{
    use \App\Filament\Concerns\TranslatedResourceLabels;

    protected static ?string $model = Collection::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $modelLabel = 'সংকলন';

    protected static ?string $pluralModelLabel = 'সংকলন';

    protected static string|\UnitEnum|null $navigationGroup = 'আরও';

    protected static ?int $navigationSort = 20;

    public const MONTHS = [1 => 'জানুয়ারি', 2 => 'ফেব্রুয়ারি', 3 => 'মার্চ', 4 => 'এপ্রিল', 5 => 'মে', 6 => 'জুন', 7 => 'জুলাই', 8 => 'আগস্ট', 9 => 'সেপ্টেম্বর', 10 => 'অক্টোবর', 11 => 'নভেম্বর', 12 => 'ডিসেম্বর'];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(Role::Admin) ?? false;
    }

    public static function canCreate(): bool
    {
        return false;   // the archivist proposes; see "প্রস্তাব চাই"
    }

    public static function getNavigationBadge(): ?string
    {
        return ($n = Collection::where('status', 'proposed')->count()) ? (string) $n : null;
    }

    public static function form(Schema $schema): Schema
    {
        $postLabel = fn ($value) => ($p = Post::with('authors')->find($value)) ? $p->title.' — '.($p->authors->first()?->publicName() ?? '') : (string) $value;

        return $schema->columns(1)->components([
            // বর্ষসেরা (owner, 2026-11-12): the designer's cover, chosen with the button at the top.
            \Filament\Forms\Components\Placeholder::make('cover')->label(__('প্রচ্ছদ'))
                ->visible(fn (?Collection $record) => $record && ($record->cover_path || $record->proposed_cover_path))
                ->content(fn (Collection $record) => new \Illuminate\Support\HtmlString('<img src="'.e(asset(ltrim((string) ($record->cover_path ?: $record->proposed_cover_path), '/'))).'" alt="" style="width:180px;aspect-ratio:1/1.41;object-fit:cover;border-radius:.4rem">'
                    .(! $record->cover_path ? '<div style="font-size:.85rem;opacity:.75;margin-top:.3rem">'.e(__('ডিজাইনারের প্রস্তাব')).'</div>' : ''))),
            TextInput::make('title')->label(__('নাম'))->required()->maxLength(150),
            TextInput::make('slug')->label(__('ঠিকানা'))->prefix('/sonkolon/')->suffix('/')->maxLength(120)->regex('/^[a-z0-9-]+$/')
                ->unique(ignoreRecord: true)->disabled(fn (?Collection $record) => $record?->status === 'approved')
                ->helperText(__('প্রকাশের পর ঠিকানা বদলানো যায় না।')),
            Textarea::make('intro')->label(__('ভূমিকা'))->rows(4)->maxLength(1200),
            CheckboxList::make('months')->label(__('কোন মাসে প্রথম পাতায় দেখাবে'))->options(\App\Support\Lang::t(self::MONTHS))->columns(4)
                ->helperText(__('কিছু না বাছলে সারা বছর; তখন সবচেয়ে নতুনটি দেখায়।')),
            Repeater::make('post_ids')->label(__('লেখা (টেনে ক্রম বদলান)'))->simple(
                Select::make('id')->required()->searchable()
                    ->getSearchResultsUsing(fn (string $search) => Post::published()->where('title', 'like', '%'.$search.'%')->latest('published_at')->limit(30)->get()
                        ->mapWithKeys(fn (Post $p) => [$p->id => $p->title.' — '.($p->authors()->first()?->publicName() ?? '')]))
                    ->getOptionLabelUsing($postLabel),
            )->reorderable()->addActionLabel(__('লেখা যোগ করুন'))->minItems(1)->maxItems(40),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('title')->label(__('নাম'))->searchable(),
                TextColumn::make('status')->label(__('অবস্থা'))->badge()
                    ->formatStateUsing(fn ($state) => __(['proposed' => 'প্রস্তাব', 'approved' => 'প্রকাশিত', 'hidden' => 'লুকানো'][$state] ?? $state))
                    ->color(fn ($state) => ['proposed' => 'warning', 'approved' => 'success'][$state] ?? 'gray'),
                TextColumn::make('post_ids')->label(__('লেখা'))->formatStateUsing(fn ($state, Collection $record) => (string) count((array) $record->post_ids)),
                TextColumn::make('months')->label(__('মাস'))->formatStateUsing(fn ($state, Collection $record) => collect((array) $record->months)->map(fn ($m) => __(self::MONTHS[(int) $m] ?? ''))->implode(', ') ?: __('সারা বছর')),
            ])
            ->recordActions([
                Action::make('open')->label(__('পাতা দেখুন'))->icon('heroicon-o-eye')->color('gray')
                    ->visible(fn (Collection $record) => filled($record->slug))->url(fn (Collection $record) => $record->url(), shouldOpenInNewTab: true),
                EditAction::make()->label(__('সাজান')),
                Action::make('approve')->label(__('প্রকাশ করুন'))->icon('heroicon-o-check-badge')->color('success')->requiresConfirmation()
                    ->visible(fn (Collection $record) => $record->status !== 'approved')
                    ->action(fn (Collection $record) => self::approve($record)),
                Action::make('addSuggested')->label(__('প্রস্তাবিত নতুন লেখা যোগ করুন'))->icon('heroicon-o-plus')->color('warning')
                    ->visible(fn (Collection $record) => (bool) self::addTask($record))
                    ->modalDescription(fn (Collection $record) => Post::whereKey((array) (self::addTask($record)?->payload['add'] ?? []))->pluck('title')->implode(' · '))
                    ->action(function (Collection $record) {
                        $task = self::addTask($record);
                        $record->forceFill(['post_ids' => array_values(array_unique(array_merge(array_map('intval', (array) $record->post_ids), array_map('intval', (array) ($task->payload['add'] ?? [])))))])->save();
                        $task->close();
                        Notification::make()->title(__('যোগ হয়েছে'))->success()->send();
                    }),
                Action::make('hide')->label(__('লুকান'))->icon('heroicon-o-eye-slash')->color('gray')->requiresConfirmation()
                    ->visible(fn (Collection $record) => $record->status === 'approved')
                    ->action(fn (Collection $record) => $record->forceFill(['status' => 'hidden'])->save()),
                \App\Filament\Support\Deleting::row(),
            ])
            ->toolbarActions([\Filament\Actions\BulkActionGroup::make([\App\Filament\Support\Deleting::bulk(\App\Models\Collection::class)])]);
    }

    public static function approve(Collection $c): void
    {
        $c->forceFill(['status' => 'approved', 'approved_at' => $c->approved_at ?? now()])->save();
        if (! $c->cover_path && $c->proposed_cover_path) {   // the designer's proposal becomes the cover when published
            $c->forceFill(['cover_path' => $c->proposed_cover_path, 'proposed_cover_path' => null])->save();
        }
        EditorTask::open('collection')->where('subject_type', $c->getMorphClass())->where('subject_id', $c->id)->get()->each->close();
        AuditLog::record('collection.approved', null, ['collection' => $c->id]);
        Notification::make()->title(__('প্রকাশিত হয়েছে'))->body($c->url())->success()->send();
    }

    public static function addTask(Collection $c): ?EditorTask
    {
        return EditorTask::open('collection_add')->where('subject_type', $c->getMorphClass())->where('subject_id', $c->id)->latest()->first();
    }

    public static function getPages(): array
    {
        return ['index' => ListCollections::route('/'), 'edit' => EditCollection::route('/{record}/edit')];
    }
}
