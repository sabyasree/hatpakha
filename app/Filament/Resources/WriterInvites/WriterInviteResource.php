<?php

namespace App\Filament\Resources\WriterInvites;

use App\Enums\Role;
use App\Filament\Resources\WriterInvites\Pages\ListWriterInvites;
use App\Models\WriterInvite;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * D188: the personal invitations to the regular writers for the ফিরে আসা সংখ্যা. The team writes and sends them by
 * itself; here the owner reads each letter, changes or stops one before it goes, and sees who answered and who wrote.
 */
class WriterInviteResource extends Resource
{
    use \App\Filament\Concerns\TranslatedResourceLabels;

    protected static ?string $model = WriterInvite::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelopeOpen;

    protected static ?string $modelLabel = 'লেখকদের আমন্ত্রণ';

    protected static ?string $pluralModelLabel = 'লেখকদের আমন্ত্রণ';

    protected static ?string $slug = 'writer-invites';

    protected static ?int $navigationSort = 9;

    public const STATUSES = ['drafted' => 'পাঠানোর অপেক্ষায়', 'held' => 'থামানো', 'sent' => 'পাঠানো হয়েছে', 'failed' => 'যায়নি'];

    public const ANSWERS = ['yes' => 'লিখব', 'later' => 'পরে লিখব', 'no' => 'চিঠি চান না'];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(Role::Editor) ?? false;
    }

    /** Letters written and still waiting to go: the number in the menu says there is something to read. */
    public static function getNavigationBadge(): ?string
    {
        return ($n = WriterInvite::where('status', 'drafted')->count()) ? (string) $n : null;
    }

    public static function canCreate(): bool { return false; }

    public static function canEdit(Model $record): bool { return false; }

    public static function canDelete(Model $record): bool { return false; }

    public static function canDeleteAny(): bool { return false; }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('author', 'post', 'piece'))
            ->defaultSort('id', 'desc')
            ->emptyStateHeading(__('এখনও কোনো চিঠি লেখা হয়নি'))
            ->emptyStateDescription(__('দল কয়েক মিনিট পরপর কয়েকটি করে চিঠি লেখে। প্রতিটি চিঠি পাঠানোর আগে বারো ঘণ্টা এখানে থাকে: পড়ে দেখুন, বদলান বা থামান।'))
            ->columns([
                TextColumn::make('author.display_name')->label(__('লেখক'))->formatStateUsing(fn (WriterInvite $record) => $record->author?->publicName())->weight('bold')
                    ->description(fn (WriterInvite $record) => $record->post ? __('যে লেখার কথা চিঠিতে').': '.$record->post->title : null)->wrap(),
                TextColumn::make('subject')->label(__('চিঠি'))->description(fn (WriterInvite $record) => \Illuminate\Support\Str::limit($record->body, 160))->wrap(),
                TextColumn::make('status')->label(__('অবস্থা'))->badge()->formatStateUsing(fn ($state) => __(self::STATUSES[$state] ?? $state))
                    ->color(fn ($state) => ['sent' => 'success', 'held' => 'gray', 'failed' => 'danger'][$state] ?? 'warning')
                    ->description(fn (WriterInvite $record) => match (true) {
                        $record->status === 'drafted' && $record->send_after !== null => __('যাবে').' '.\App\Support\Bn::num($record->send_after->timezone('Asia/Kolkata')->format('d/m H:i')).' '.__('এর পরে'),
                        $record->status === 'sent' => \App\Support\Bn::num($record->sent_at->timezone('Asia/Kolkata')->format('d/m H:i')).($record->reminded_at ? ' · '.__('একবার মনে করানো হয়েছে') : ''),
                        default => null,
                    }),
                TextColumn::make('answer')->label(__('উত্তর'))->badge()->placeholder('—')->formatStateUsing(fn ($state) => __(self::ANSWERS[$state] ?? $state))
                    ->color(fn ($state) => ['yes' => 'success', 'no' => 'danger'][$state] ?? 'gray')
                    ->description(fn (WriterInvite $record) => $record->piece ? __('লেখা এসেছে').': '.$record->piece->title : null)->wrap(),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('অবস্থা'))->options(\App\Support\Lang::t(self::STATUSES)),
                SelectFilter::make('answer')->label(__('উত্তর'))->options(\App\Support\Lang::t(self::ANSWERS)),
            ])
            ->recordActions([
                Action::make('read')->label(fn (WriterInvite $record) => in_array($record->status, ['drafted', 'held'], true) ? __('পড়ুন ও বদলান') : __('চিঠিটি পড়ুন'))->icon('heroicon-o-eye')->color('gray')
                    ->fillForm(fn (WriterInvite $record) => ['subject' => $record->subject, 'body' => $record->body])
                    ->schema(fn (WriterInvite $record) => [
                        TextInput::make('subject')->label(__('বিষয়'))->required()->maxLength(150)->disabled(! in_array($record->status, ['drafted', 'held'], true)),
                        Textarea::make('body')->label(__('চিঠি'))->rows(14)->required()->maxLength(4000)->disabled(! in_array($record->status, ['drafted', 'held'], true))
                            ->helperText(__('শুরুর “প্রিয় …,” আর শেষের সই নিজে থেকে বসে।')),
                    ])
                    ->modalSubmitActionLabel(__('সেভ করুন'))
                    ->action(function (WriterInvite $record, array $data) {
                        if (in_array($record->status, ['drafted', 'held'], true)) {
                            $record->forceFill(['subject' => $data['subject'], 'body' => $data['body']])->save();
                            Notification::make()->title(__('সেভ হয়েছে'))->success()->send();
                        }
                    }),
                Action::make('hold')->label(__('থামান'))->icon('heroicon-o-pause')->color('gray')
                    ->visible(fn (WriterInvite $record) => $record->status === 'drafted')
                    ->action(fn (WriterInvite $record) => $record->forceFill(['status' => 'held'])->save()),
                Action::make('release')->label(__('আবার চালু'))->icon('heroicon-o-play')->color('gray')
                    ->visible(fn (WriterInvite $record) => $record->status === 'held')
                    ->action(fn (WriterInvite $record) => $record->forceFill(['status' => 'drafted', 'send_after' => now()])->save()),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListWriterInvites::route('/')];
    }
}
