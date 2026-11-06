<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Filament\Resources\Pages\PageResource;
use Filament\Resources\Pages\EditRecord;

class EditPage extends EditRecord
{
    protected static string $resource = PageResource::class;

    // No delete button: pages are unpublished (খসড়া) instead; deleting content needs the owner (CLAUDE.md §3).
    protected function getHeaderActions(): array
    {
        return [];
    }

    /** Marks the page as edited here, so a refresh from database/content/pages never overwrites it. */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['edited_in_desk_at'] = now();

        return $data;
    }
}
