<?php

namespace App\Filament\Resources\Collections\Pages;

use App\Filament\Resources\Collections\CollectionResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditCollection extends EditRecord
{
    protected static string $resource = CollectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')->label(__('প্রকাশ করুন'))->icon('heroicon-o-check-badge')->color('success')->requiresConfirmation()
                ->visible(fn () => $this->record->status !== 'approved')
                ->action(function () {
                    $this->save();
                    CollectionResource::approve($this->record);
                }),
            Action::make('open')->label(__('পাতা দেখুন'))->icon('heroicon-o-eye')->color('gray')
                ->visible(fn () => filled($this->record->slug))->url(fn () => $this->record->url(), shouldOpenInNewTab: true),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['post_ids'] = array_values(array_unique(array_map('intval', array_filter((array) ($data['post_ids'] ?? [])))));
        $data['title'] = strip_tags($data['title']);
        $data['intro'] = strip_tags((string) ($data['intro'] ?? ''));
        $data['months'] = array_values(array_map('intval', (array) ($data['months'] ?? [])));

        return $data;
    }
}
