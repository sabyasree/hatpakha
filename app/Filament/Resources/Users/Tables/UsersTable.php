<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\Role;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Password;

class UsersTable
{
    public static function sendResetLink(User $user): bool
    {
        return Password::broker()->sendResetLink(['email' => $user->email]) === Password::RESET_LINK_SENT;
    }

    public static function configure(Table $table): Table
    {
        $roleValue = fn ($state) => $state instanceof Role ? $state->value : (string) $state;

        return $table
            ->defaultSort('last_login_at', 'desc')
            ->columns([
                TextColumn::make('name')->label(__('নাম'))->weight('bold')->searchable(['name', 'email'])
                    ->description(fn (User $record) => $record->email),
                TextColumn::make('role')->label(__('ভূমিকা'))->badge()
                    ->formatStateUsing(fn ($state) => UserForm::roles()[$roleValue($state)] ?? $roleValue($state))
                    ->color(fn ($state) => in_array($roleValue($state), ['editor', 'chief_editor', 'admin'], true) ? 'danger' : 'gray'),
                IconColumn::make('is_reviewer')->label(__('যাচাইকারী'))->boolean()->trueColor('success')->falseIcon('')->toggleable(),   // D168
                IconColumn::make('twofa')->label(__('২-ধাপ'))->boolean()
                    ->state(fn (User $record) => filled($record->app_authentication_secret) || $record->hasEmailAuthentication()),
                TextColumn::make('legacy_role')->label(__('পুরনো ভূমিকা'))->placeholder('—')->toggleable(),
                TextColumn::make('spam_reason')->label(__('স্প্যাম'))->badge()->color('warning')->placeholder('—')->toggleable(),
                TextColumn::make('last_login_at')->label(__('শেষ লগইন'))->since()->sortable()->placeholder(__('কখনও না')),
                TextColumn::make('created_at')->label(__('যোগ দিয়েছেন'))->date('d M Y')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('role')->label(__('ভূমিকা'))->options(UserForm::roles()),
                SelectFilter::make('desk')->label(__('ডেস্ক খুলতে পারেন'))->options(['yes' => __('হ্যাঁ')])
                    ->query(fn ($query, array $data) => ($data['value'] ?? null) === 'yes'
                        ? $query->whereIn('role', ['editor', 'chief_editor', 'admin']) : $query),
                \Filament\Tables\Filters\Filter::make('reviewers')->label(__('শুধু যাচাইকারী'))->query(fn ($query) => $query->where('is_reviewer', true)),
                TernaryFilter::make('spam_reason')->label(__('স্প্যাম চিহ্নিত'))->nullable(),
                \Filament\Tables\Filters\Filter::make('gameBlocked')->label(__('খেলার তালিকা থেকে বাদ'))->query(fn ($query) => $query->whereNotNull('game_blocked_at')),
                // D244: which way a person signs in
                SelectFilter::make('signin')->label(__('কীভাবে সাইন ইন করেন'))->options(['google' => __('Google দিয়ে'), 'password' => __('শুধু ই-মেল আর পাসওয়ার্ডে')])
                    ->query(fn ($query, array $data) => match ($data['value'] ?? null) {
                        'google' => $query->whereNotNull('google_id'), 'password' => $query->whereNull('google_id'), default => $query,
                    }),
            ])
            ->recordActions([
                Action::make('resetLink')->label(__('পাসওয়ার্ড লিংক'))->icon('heroicon-o-key')->color('gray')
                    ->requiresConfirmation()->modalDescription(fn (User $record) => __(':email ঠিকানায় নতুন পাসওয়ার্ড বানানোর লিংক যাবে।', ['email' => $record->email]))
                    ->action(function (User $record) {
                        $ok = self::sendResetLink($record);
                        \App\Models\AuditLog::record('user.reset_link_sent', $record, ['by' => auth()->id()]);
                        Notification::make()->title($ok ? __('লিংক পাঠানো হয়েছে') : __('এখনই পাঠানো গেল না, একটু পরে চেষ্টা করুন'))
                            ->{$ok ? 'success' : 'warning'}()->send();
                    }),
                self::gameBlock(),
                EditAction::make(),
                \App\Filament\Support\Deleting::row(),
            ])
            ->toolbarActions([\Filament\Actions\BulkActionGroup::make([\App\Filament\Support\Deleting::bulk(\App\Models\User::class)])]);
    }

    /**
     * D245 (owner: "a desk button to remove a suspicious score"): keeps a player off the games' boards and rolls, and
     * puts them back. They can still play; nothing is deleted unless the box is ticked. On the list and the edit page.
     */
    public static function gameBlock(): Action
    {
        return Action::make('gameBlock')->icon('heroicon-o-no-symbol')->color('gray')
                    ->label(fn (User $record) => $record->game_blocked_at ? __('খেলার তালিকায় ফেরান') : __('খেলার তালিকা থেকে বাদ'))
                    ->visible(fn (User $record) => auth()->user()?->hasRole(\App\Enums\Role::Admin) && ($record->game_blocked_at || \Illuminate\Support\Facades\DB::table('game_scores')->where('user_id', $record->id)->exists()))
                    ->modalHeading(fn (User $record) => $record->game_blocked_at ? __(':name-কে খেলার তালিকায় ফেরাবেন?', ['name' => $record->name]) : __(':name-কে খেলার তালিকা থেকে বাদ দেবেন?', ['name' => $record->name]))
                    ->modalDescription(function (User $record) {
                        $mine = \App\Support\GameScores::board('month', $record, 0)['mine'];

                        return $record->game_blocked_at ? __('তাঁর নাম আবার তালিকায় দেখা যাবে, খেতাবও পেতে পারবেন।')
                            : __('এ মাসে: :p পয়েন্ট, :d দিন। বাদ দিলে তাঁর নাম কোনো তালিকায় থাকবে না, খেতাবও পাবেন না। তিনি খেলতে পারবেন, কিছু মোছা হবে না। পরে ফেরানো যায়।', ['p' => \App\Support\Bn::num(number_format($mine['points'] ?? 0)), 'd' => \App\Support\Bn::num((string) ($mine['days'] ?? 0))]);
                    })
                    ->schema(fn (User $record) => $record->game_blocked_at || ! \Illuminate\Support\Facades\DB::table('game_titles')->where('user_id', $record->id)->exists() ? [] : [
                        \Filament\Forms\Components\Checkbox::make('titles')->label(__('আগে জেতা খেতাবও তুলে নিন (ফেরানো যাবে না)')),
                    ])
                    ->modalSubmitActionLabel(fn (User $record) => $record->game_blocked_at ? __('ফেরান') : __('বাদ দিন'))
                    ->action(function (User $record, array $data) {
                        $blocking = ! $record->game_blocked_at;
                        $record->forceFill(['game_blocked_at' => $blocking ? now() : null])->save();
                        $removed = $blocking && ($data['titles'] ?? false) ? \Illuminate\Support\Facades\DB::table('game_titles')->where('user_id', $record->id)->delete() : 0;
                        \App\Support\GameScores::forget();
                        \Illuminate\Support\Facades\Cache::forget('hp.titles.marks');
                        \App\Models\AuditLog::record($blocking ? 'game.player_blocked' : 'game.player_restored', $record, ['by' => auth()->id(), 'titles_removed' => $removed]);
                        Notification::make()->title($blocking ? __('খেলার তালিকা থেকে বাদ দেওয়া হল') : __('খেলার তালিকায় ফেরানো হল'))->success()->send();
                    });
    }
}
