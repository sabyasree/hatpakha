<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        // Admins can't delete their own account from here (avoids locking the desk out).
        return [
            // Staging sends no e-mail, so a new account can't click its link: the owner can mark it as checked.
            \Filament\Actions\Action::make('markVerified')->label(__('ই-মেল যাচাই হয়েছে ধরুন'))->icon('heroicon-o-check-badge')->color('gray')
                ->visible(fn () => auth()->user()?->hasRole(\App\Enums\Role::Admin) && ! $this->record->hasVerifiedEmail())
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->forceFill(['email_verified_at' => now()])->save();
                    \App\Models\AuditLog::record('user.email_marked_verified', $this->record, ['by' => auth()->id()]);
                    \Filament\Notifications\Notification::make()->title(__('যাচাই হয়েছে বলে ধরা হলো'))->success()->send();
                }),
            \Filament\Actions\Action::make('viewAs')->label(__('লেখকের চোখে দেখুন'))->icon('heroicon-o-eye')->color('gray')
                ->visible(fn () => auth()->user()?->hasRole(\App\Enums\Role::Admin) && ($u = $this->record) && ! $u->hasRole(\App\Enums\Role::Editor))
                ->requiresConfirmation()->modalHeading(__('এঁর চোখে সাইটটি দেখুন'))
                ->modalDescription(__('শুধু দেখা যাবে: কিছু বদলানো বা পাঠানো যাবে না। ৩০ মিনিট পরে বা "বন্ধ করুন" টিপলে শেষ হবে।'))
                ->action(fn () => ($to = \App\Http\Middleware\ViewAs::start($this->record)) ? $this->redirect($to) : null),
            DeleteAction::make()->hidden(fn () => $this->record->is(auth()->user())),
        ];
    }
}
