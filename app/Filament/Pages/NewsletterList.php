<?php

namespace App\Filament\Pages;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Owner, 2026-11-10 (D120): who gets the letters. Two lists: accounts (readers and writers) and sign-ups
 * without an account. Admins only (it shows e-mail addresses). Letters can be stopped for someone here;
 * nobody who switched letters off is ever switched back on from the desk.
 */
class NewsletterList extends Page implements HasTable
{
    use InteractsWithTable;
    use \App\Filament\Concerns\TranslatedPageLabels;

    protected string $view = 'filament.pages.newsletter-list';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $navigationLabel = 'চিঠির তালিকা';

    protected static ?string $title = 'চিঠির তালিকা';

    protected static ?int $navigationSort = 5;

    #[\Livewire\Attributes\Url]
    public string $source = 'accounts';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(Role::Admin) ?? false;
    }

    public function getSubheading(): ?string
    {
        $b = \App\Support\NewsletterSender::breakdown();
        $n = fn ($v) => \App\Support\Bn::num((string) $v);

        return __('চিঠি পাবেন').': '.$n($b['readers']).' '.__('জন পাঠক').' · '.$n($b['writers']).' '.__('জন লেখক').' · '
            .__('চিঠি বন্ধ রেখেছেন').' '.$n($b['opted_out']).' · '.__('স্প্যাম বলে চিহ্নিত (পাবেন না)').' '.$n($b['spam']);
    }

    public static function status(User $u): string
    {
        return match (true) {
            filled($u->spam_reason ?? null) => 'spam',
            (bool) $u->mail_opt_out => 'off',
            default => 'on',
        };
    }

    public function table(Table $table): Table
    {
        return $this->source === 'signups' ? $this->signups($table) : $this->accounts($table);
    }

    private function accounts(Table $table): Table
    {
        $label = ['on' => 'পাবেন', 'off' => 'বন্ধ রেখেছেন', 'spam' => 'স্প্যাম, পাবেন না'];

        return $table->query(User::query()->with('author:id,user_id,published_count'))->defaultSort('id')
            ->columns([
                TextColumn::make('name')->label(__('নাম'))->weight('bold')->searchable(['name', 'email'])->description(fn (User $record) => $record->email),
                TextColumn::make('role')->label(__('ধরন'))->badge()
                    ->formatStateUsing(fn ($state) => in_array($state, [Role::Writer, Role::TrustedWriter], true) ? __('লেখক') : ($state === Role::Reader ? __('পাঠক') : __('দল')))
                    ->color(fn ($state) => in_array($state, [Role::Writer, Role::TrustedWriter], true) ? 'info' : 'gray'),
                TextColumn::make('letters')->label(__('চিঠি'))->badge()->state(fn (User $record) => __($label[self::status($record)]))
                    ->color(fn (User $record) => ['on' => 'success', 'off' => 'gray', 'spam' => 'danger'][self::status($record)]),
                TextColumn::make('created_at')->label(__('যোগ দিয়েছেন'))->date('d/m/Y')->sortable(),
            ])
            ->filters([
                SelectFilter::make('letters')->label(__('চিঠি'))->options(array_map('__', $label))
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'on' => $query->whereNull('spam_reason')->where('mail_opt_out', false),
                        'off' => $query->whereNull('spam_reason')->where('mail_opt_out', true),
                        'spam' => $query->whereNotNull('spam_reason'),
                        default => $query,
                    }),
                SelectFilter::make('role')->label(__('ধরন'))->options(['writer' => __('লেখক'), 'reader' => __('পাঠক')])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'writer' => $query->whereIn('role', [Role::Writer->value, Role::TrustedWriter->value]),
                        'reader' => $query->where('role', Role::Reader->value),
                        default => $query,
                    }),
            ])
            ->recordActions([
                Action::make('stop')->label(__('চিঠি বন্ধ করুন'))->icon('heroicon-o-no-symbol')->color('gray')
                    ->visible(fn (User $record) => self::status($record) === 'on')->requiresConfirmation()
                    ->modalDescription(__('এই মানুষটি আর কোনও চিঠি পাবেন না। পরে চাইলে নিজের অ্যাকাউন্ট থেকে আবার চালু করতে পারবেন।'))
                    ->action(fn (User $record) => self::stopAccounts(collect([$record]))),
            ])
            ->toolbarActions([
                BulkAction::make('stopSelected')->label(__('বেছে নেওয়াদের চিঠি বন্ধ করুন'))->icon('heroicon-o-no-symbol')->requiresConfirmation()
                    ->action(fn (Collection $records) => self::stopAccounts($records))->deselectRecordsAfterCompletion(),
            ]);
    }

    private function signups(Table $table): Table
    {
        return $table->query(NewsletterSubscriber::query())->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('email')->label(__('ই-মেল'))->searchable()->weight('bold')->description(fn (NewsletterSubscriber $record) => $record->name),
                TextColumn::make('status')->label(__('চিঠি'))->badge()
                    ->state(fn (NewsletterSubscriber $record) => $record->suppressed ? __('বন্ধ') : ($record->status === 'subscribed' && $record->consented_at ? __('পাবেন') : __('নিশ্চিত করেননি')))
                    ->color(fn (NewsletterSubscriber $record) => $record->suppressed ? 'gray' : ($record->status === 'subscribed' && $record->consented_at ? 'success' : 'warning')),
                TextColumn::make('created_at')->label(__('যোগ দিয়েছেন'))->date('d/m/Y')->sortable(),
            ])
            ->recordActions([
                Action::make('stop')->label(__('চিঠি বন্ধ করুন'))->icon('heroicon-o-no-symbol')->color('gray')->requiresConfirmation()
                    ->visible(fn (NewsletterSubscriber $record) => ! $record->suppressed)
                    ->action(function (NewsletterSubscriber $record) {
                        $record->forceFill(['status' => 'unsubscribed', 'suppressed' => true])->save();
                        AuditLog::record('newsletter.stopped_by_desk', null, ['subscriber' => $record->id]);
                        Notification::make()->title(__('চিঠি বন্ধ করা হয়েছে'))->success()->send();
                    }),
            ]);
    }

    private static function stopAccounts(Collection $users): void
    {
        $ids = $users->pluck('id');
        User::whereIn('id', $ids)->update(['mail_opt_out' => true]);
        AuditLog::record('newsletter.stopped_by_desk', null, ['users' => $ids->count()]);
        Notification::make()->title(\App\Support\Bn::num((string) $ids->count()).__('জনের চিঠি বন্ধ করা হয়েছে'))->success()->send();
    }
}
