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
                TextColumn::make('name')->label('নাম')->weight('bold')->searchable(['name', 'email'])
                    ->description(fn (User $record) => $record->email),
                TextColumn::make('role')->label('ভূমিকা')->badge()
                    ->formatStateUsing(fn ($state) => UserForm::roles()[$roleValue($state)] ?? $roleValue($state))
                    ->color(fn ($state) => in_array($roleValue($state), ['editor', 'chief_editor', 'admin'], true) ? 'danger' : 'gray'),
                IconColumn::make('twofa')->label('২-ধাপ')->boolean()
                    ->state(fn (User $record) => filled($record->app_authentication_secret) || $record->hasEmailAuthentication()),
                TextColumn::make('legacy_role')->label('পুরনো ভূমিকা')->placeholder('—')->toggleable(),
                TextColumn::make('spam_reason')->label('স্প্যাম')->badge()->color('warning')->placeholder('—')->toggleable(),
                TextColumn::make('last_login_at')->label('শেষ লগইন')->since()->sortable()->placeholder('কখনও না'),
                TextColumn::make('created_at')->label('যোগ দিয়েছেন')->date('d M Y')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('role')->label('ভূমিকা')->options(UserForm::roles()),
                SelectFilter::make('desk')->label('ডেস্ক খুলতে পারেন')->options(['yes' => 'হ্যাঁ'])
                    ->query(fn ($query, array $data) => ($data['value'] ?? null) === 'yes'
                        ? $query->whereIn('role', ['editor', 'chief_editor', 'admin']) : $query),
                TernaryFilter::make('spam_reason')->label('স্প্যাম চিহ্নিত')->nullable(),
            ])
            ->recordActions([
                Action::make('resetLink')->label('পাসওয়ার্ড লিংক')->icon('heroicon-o-key')->color('gray')
                    ->requiresConfirmation()->modalDescription(fn (User $record) => "{$record->email} ঠিকানায় নতুন পাসওয়ার্ড বানানোর লিংক যাবে।")
                    ->action(function (User $record) {
                        $ok = self::sendResetLink($record);
                        \App\Models\AuditLog::record('user.reset_link_sent', $record, ['by' => auth()->id()]);
                        Notification::make()->title($ok ? 'লিংক পাঠানো হয়েছে' : 'এখনই পাঠানো গেল না, একটু পরে চেষ্টা করুন')
                            ->{$ok ? 'success' : 'warning'}()->send();
                    }),
                EditAction::make(),
            ]);
    }
}
