<?php

namespace App\Filament\Resources\ContactMessages;

use App\Enums\Role;
use App\Filament\Resources\ContactMessages\Pages\ListContactMessages;
use App\Models\AuditLog;
use App\Models\ContactMessage;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** D89: messages from the contact and advertising forms. Read, reply from your own e-mail, mark done. */
class ContactMessageResource extends Resource
{
    use \App\Filament\Concerns\TranslatedResourceLabels;

    protected static ?string $model = ContactMessage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;

    protected static ?string $modelLabel = 'বার্তা';

    protected static ?string $pluralModelLabel = 'বার্তা';

    protected static string|\UnitEnum|null $navigationGroup = null;   // top level, next to comments

    protected static ?int $navigationSort = 21;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(Role::Editor) ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        return ($n = ContactMessage::where('status', 'new')->count()) ? (string) $n : null;
    }

    public static function table(Table $table): Table
    {
        $mark = fn (string $name, string $label, string $icon, string $status, string $color = 'gray') => Action::make($name)->label(__($label))->icon($icon)->color($color)
            ->visible(fn (ContactMessage $record) => $record->status !== $status)
            ->action(function (ContactMessage $record) use ($status) {
                $record->forceFill(['status' => $status, 'handled_by' => auth()->id(), 'handled_at' => now()])->save();
                AuditLog::record('contact.'.$status, $record);
            });

        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('handler'))
            ->columns([
                TextColumn::make('status')->label(__('অবস্থা'))->badge()
                    ->formatStateUsing(fn ($state) => __(['new' => 'নতুন', 'replied' => 'উত্তর দেওয়া', 'closed' => 'হয়ে গেছে', 'spam' => 'স্প্যাম'][$state] ?? $state))
                    ->color(fn ($state) => ['new' => 'danger', 'replied' => 'success', 'spam' => 'gray'][$state] ?? 'info'),
                TextColumn::make('kind')->label(__('ফর্ম'))->formatStateUsing(fn ($state) => __(['advertise' => 'বিজ্ঞাপন', 'change' => 'লেখায় বদল'][$state] ?? 'যোগাযোগ')),
                TextColumn::make('topic')->label(__('বিষয়'))->formatStateUsing(fn ($state, ContactMessage $record) => __($record->topicLabel())),
                TextColumn::make('name')->label(__('নাম'))->searchable()->description(fn (ContactMessage $record) => $record->organisation),
                TextColumn::make('body')->label(__('বার্তা'))->limit(70)->searchable()->wrap(),
                TextColumn::make('created_at')->label(__('কবে'))->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('অবস্থা'))->default('new')
                    ->options(['new' => __('নতুন'), 'replied' => __('উত্তর দেওয়া'), 'closed' => __('হয়ে গেছে'), 'spam' => __('স্প্যাম')]),
                SelectFilter::make('kind')->label(__('ফর্ম'))->options(['contact' => __('যোগাযোগ'), 'advertise' => __('বিজ্ঞাপন'), 'change' => __('লেখায় বদল')]),
            ])
            ->recordAction('read')
            ->recordActions([
                Action::make('read')->label(__('পড়ুন'))->icon('heroicon-o-envelope-open')
                    ->modalHeading(fn (ContactMessage $record) => __($record->topicLabel()))
                    ->modalContent(fn (ContactMessage $record) => \App\Filament\Support\Safe::view('filament.partials.contact-message', ['m' => $record]))
                    ->modalSubmitAction(false)->modalCancelActionLabel(__('বন্ধ')),
                Action::make('openPiece')->label(__('লেখাটি খুলুন'))->icon('heroicon-o-pencil-square')->color('gray')
                    ->visible(fn (ContactMessage $record) => (bool) $record->post())
                    ->url(fn (ContactMessage $record) => \App\Filament\Resources\Posts\PostResource::getUrl('edit', ['record' => $record->post()])),
                Action::make('reply')->label(__('ই-মেলে উত্তর'))->icon('heroicon-o-paper-airplane')->color('primary')
                    ->url(fn (ContactMessage $record) => 'mailto:'.rawurlencode($record->email).'?subject='.rawurlencode('Re: হাতপাখা — '.$record->topicLabel()), shouldOpenInNewTab: true),
                $mark('replied', 'উত্তর দিয়েছি', 'heroicon-o-check', 'replied', 'success'),
                $mark('closed', 'হয়ে গেছে', 'heroicon-o-archive-box', 'closed'),
                $mark('spam', 'স্প্যাম', 'heroicon-o-no-symbol', 'spam'),
                \App\Filament\Support\Deleting::row(),
            ])
            ->toolbarActions([\Filament\Actions\BulkActionGroup::make([\App\Filament\Support\Deleting::bulk(\App\Models\ContactMessage::class)])]);
    }

    public static function getPages(): array
    {
        return ['index' => ListContactMessages::route('/')];
    }
}
