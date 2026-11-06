<?php

namespace App\Filament\Resources\Authors\Pages;

use App\Filament\Resources\Authors\AuthorResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAuthor extends EditRecord
{
    protected static string $resource = AuthorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // D66: honour a writer (badge on their pages, praise on their writer page, home page); remove it.
            \Filament\Actions\Action::make('honour')->label(__('সম্মান দিন'))->icon('heroicon-o-star')->color('warning')
                ->visible(fn () => auth()->user()?->hasRole(\App\Enums\Role::Admin) && ! $this->record->is_desk)
                ->fillForm(fn () => ['label' => 'pick', 'praise' => $this->record->honour_note, 'tell' => true])
                ->schema([
                    \Filament\Forms\Components\Select::make('label')->label(__('কোন সম্মান'))->required()->options(\App\Support\Lang::t(\App\Ai\Honours::LABELS)),
                    \Filament\Forms\Components\Textarea::make('praise')->label(__('সম্পাদকের কথা (লেখক-পাতায় দেখা যাবে)'))->rows(4)->maxLength(800),
                    \Filament\Forms\Components\Toggle::make('tell')->label(__('লেখককে অভিনন্দনের ই-মেল পাঠান')),
                ])
                ->action(function (array $data) {
                    \App\Ai\Honours::give($this->record, $data['label'], (string) $data['praise'], (bool) $data['tell']);
                    $this->refreshFormData(['honour_label', 'honour_note']);
                    \Filament\Notifications\Notification::make()->title(__('সম্মান দেওয়া হয়েছে'))->success()->send();
                }),
            \Filament\Actions\Action::make('unhonour')->label(__('সম্মান সরান'))->icon('heroicon-o-x-mark')->color('gray')->requiresConfirmation()
                ->visible(fn () => auth()->user()?->hasRole(\App\Enums\Role::Admin) && $this->record->honour_label)
                ->action(fn () => $this->record->forceFill(['honour_label' => null, 'honour_note' => null, 'honoured_at' => null])->save()),
            DeleteAction::make(),
        ];
    }
}
