<?php

namespace App\Filament\Resources\Newsletters\Pages;

use App\Filament\Resources\Newsletters\NewsletterResource;
use Filament\Resources\Pages\CreateRecord;

class CreateNewsletter extends CreateRecord
{
    protected static string $resource = NewsletterResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return ['items' => NewsletterResource::resolveItems($data['items'] ?? []), 'source' => 'owner', 'status' => 'draft', 'created_by' => auth()->id()] + $data;
    }

    protected function getRedirectUrl(): string
    {
        return NewsletterResource::getUrl('edit', ['record' => $this->record]);
    }
}
