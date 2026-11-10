<?php

namespace App\Filament\Resources\Serials\Pages;

use App\Filament\Resources\Serials\SerialResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditSerial extends EditRecord
{
    protected static string $resource = SerialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('start')->label(__('ধারাবাহিক শুরু করুন'))->icon('heroicon-o-play')->color('success')->requiresConfirmation()
                ->modalDescription(__('ধারাবাহিকের পাতা চালু হবে। প্রতিটি পর্ব তবুও আপনি নিজে দেখে প্রকাশ করবেন; লেখক এক সপ্তাহ আগে পরের পর্বের খসড়া রাখবেন।'))
                ->visible(fn () => in_array($this->record->status, ['proposed', 'paused'], true))
                ->action(function () {
                    $this->save();
                    SerialResource::start($this->record);
                    Notification::make()->title(__('ধারাবাহিক চালু হয়েছে'))->success()->send();
                }),
            Action::make('next')->label(__('পরের পর্ব লেখান'))->icon('heroicon-o-pencil-square')->color('gray')
                ->visible(fn () => in_array($this->record->status, ['proposed', 'active'], true) && $this->record->writer)
                ->action(function () {
                    \App\Ai\TeamJobs::queue($this->record->writer, 'next');
                    Notification::make()->title(__('এআই দল কাজটি নিয়েছে'))->body(__('কয়েক মিনিটে এখানে দেখা যাবে।'))->success()->send();
                }),
            Action::make('pause')->label(__('থামান'))->icon('heroicon-o-pause')->color('gray')->requiresConfirmation()
                ->visible(fn () => $this->record->status === 'active')
                ->action(fn () => $this->record->forceFill(['status' => 'paused'])->save()),
            Action::make('finish')->label(__('শেষ হয়েছে'))->icon('heroicon-o-flag')->color('gray')->requiresConfirmation()
                ->modalDescription(__('শেষ পর্ব প্রকাশের পরে টিপুন। পাতায় "সমাপ্ত" দেখাবে, নতুন পর্ব আর লেখা হবে না।'))
                ->visible(fn () => in_array($this->record->status, ['active', 'paused'], true))
                ->action(fn () => $this->record->forceFill(['status' => 'finished'])->save()),
            Action::make('open')->label(__('পাতা দেখুন'))->icon('heroicon-o-eye')->color('gray')
                ->visible(fn () => $this->record->isPublic())->url(fn () => $this->record->url(), shouldOpenInNewTab: true),
            \App\Filament\Support\Deleting::page(static::$resource),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['title'] = strip_tags($data['title']);
        $data['logline'] = strip_tags((string) ($data['logline'] ?? ''));

        return $data;
    }
}
