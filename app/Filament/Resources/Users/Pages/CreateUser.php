<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\Tables\UsersTable;
use App\Filament\Resources\Users\UserResource;
use App\Models\AuditLog;
use Filament\Resources\Pages\CreateRecord;

/** New accounts get no admin-chosen password: the person sets their own from an emailed link. */
class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function afterCreate(): void
    {
        $this->record->forceFill(['email_verified_at' => now()])->save();
        AuditLog::record('user.created', $this->record, ['by' => auth()->id(), 'role' => $this->record->role?->value ?? $this->record->role]);
        UsersTable::sendResetLink($this->record);
    }
}
