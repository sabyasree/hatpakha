<?php

namespace App\Filament\Resources\SentLetters;

use App\Enums\Role;
use App\Filament\Resources\SentLetters\Pages\ListSentLetters;
use App\Models\SentLetter;
use App\Support\WriterLetters;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** D161: every letter sent to a writer, exactly as it went out (read-only). Editors and above. */
class SentLetterResource extends Resource
{
    use \App\Filament\Concerns\TranslatedResourceLabels;

    protected static ?string $model = SentLetter::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaperAirplane;

    protected static ?string $modelLabel = 'পাঠানো চিঠি';

    protected static ?string $pluralModelLabel = 'পাঠানো চিঠি';

    protected static ?string $slug = 'sent-letters';

    protected static ?int $navigationSort = 7;

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
            ->modifyQueryUsing(fn ($query) => $query->with(['user', 'post']))
            ->defaultSort('sent_at', 'desc')
            ->emptyStateHeading(__('এখনও কোনও চিঠি যায়নি'))
            ->emptyStateDescription(__('লেখককে মনোনয়ন, বদল চাওয়া, এবার নয়, শংসাপত্র বা সম্পাদকের চিঠি পাঠালে এখানে হুবহু থাকবে।'))
            ->columns([
                TextColumn::make('sent_at')->label(__('কখন'))->dateTime('d M Y, h:i A')->sortable()
                    ->description(fn (SentLetter $record) => $record->sent_at?->diffForHumans()),
                TextColumn::make('kind')->label(__('কী চিঠি'))->badge()
                    ->formatStateUsing(fn ($state) => __(WriterLetters::KINDS[$state] ?? $state)),
                TextColumn::make('writer')->label(__('কাকে'))->wrap()
                    ->state(fn (SentLetter $record) => $record->user?->name ?? $record->to_email)
                    ->description(fn (SentLetter $record) => $record->to_email),
                TextColumn::make('post.title')->label(__('কোন লেখা'))->wrap()->placeholder('—')
                    ->url(fn (SentLetter $record) => $record->post_id ? \App\Filament\Resources\Posts\PostResource::getUrl('edit', ['record' => $record->post_id]) : null),
                TextColumn::make('subject')->label(__('বিষয়'))->wrap()->searchable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('kind')->label(__('কী চিঠি'))->options(\App\Support\Lang::t(WriterLetters::KINDS)),
                SelectFilter::make('post_id')->label(__('কোন লেখা'))->relationship('post', 'title')->searchable(),
            ])
            ->recordActions([
                Action::make('read')->label(__('পড়ুন'))->icon('heroicon-o-envelope-open')->color('gray')
                    ->modalHeading(fn (SentLetter $record) => $record->subject)
                    ->modalDescription(fn (SentLetter $record) => __('পাঠানো হয়েছে: ').$record->to_email.' · '.$record->sent_at?->copy()->setTimezone('Asia/Kolkata')->format('d M Y, h:i A'))
                    ->modalContent(fn (SentLetter $record) => view('filament.partials.mail-preview', ['html' => $record->html]))
                    ->modalWidth('3xl')->modalSubmitAction(false)->modalCancelActionLabel(__('চিঠি বন্ধ করুন')),
            ])
            ->recordAction('read');
    }

    public static function getPages(): array
    {
        return ['index' => ListSentLetters::route('/')];
    }
}
