<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Enums\Role;
use App\Filament\Resources\Posts\PostResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditPost extends EditRecord
{
    protected static string $resource = PostResource::class;

    protected function getHeaderActions(): array
    {
        // Deleting content needs the owner (CLAUDE.md §3): only admins see these; editors unpublish instead.
        $isAdmin = fn () => auth()->user()?->hasRole(Role::Admin) ?? false;

        return [
            DeleteAction::make()->visible($isAdmin),
            ForceDeleteAction::make()->visible($isAdmin),
            RestoreAction::make()->visible($isAdmin),
        ];
    }

    /** Marks the post as edited here, so a later WordPress re-import never overwrites it. */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['edited_in_desk_at'] = now();

        return $data;
    }
}
