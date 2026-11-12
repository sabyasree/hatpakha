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
            // বর্ষসেরা (owner, 2026-11-12): the collection as a magazine, its cover, and its news.
            Action::make('previewMagazine')->label(__('পত্রিকা কেমন দেখাবে'))->icon('heroicon-o-book-open')->color('gray')
                ->url(fn () => \App\Support\Url::to('/collection-preview/'.$this->record->id), shouldOpenInNewTab: true),
            Action::make('useCover')->label(__('ডিজাইনারের প্রচ্ছদ নিন'))->icon('heroicon-o-check')->color('gray')
                ->visible(fn () => filled($this->record->proposed_cover_path) && auth()->user()?->hasRole(\App\Enums\Role::Admin))
                ->action(function () {
                    $old = $this->record->cover_path;
                    $this->record->forceFill(['cover_path' => $this->record->proposed_cover_path, 'proposed_cover_path' => null])->save();
                    if ($old) {
                        \App\Support\CoverImage::deleteFiles($old);
                    }
                    \Filament\Notifications\Notification::make()->title(__('প্রচ্ছদ বেছে নেওয়া হল'))->success()->send();
                }),
            Action::make('drawCover')->label(fn () => $this->record->cover_path || $this->record->proposed_cover_path ? __('আরেকটি আঁকান') : __('প্রচ্ছদ আঁকান'))
                ->icon('heroicon-o-paint-brush')->color('gray')->visible(fn () => auth()->user()?->hasRole(\App\Enums\Role::Admin))
                ->action(function () {
                    if ($why = \App\Ai\Illustrator::blocked()) {
                        \Filament\Notifications\Notification::make()->title(__('ডিজাইনার এখন কাজ করতে পারছেন না'))->body($why)->warning()->send();

                        return;
                    }
                    $id = $this->record->id;
                    dispatch(fn () => \App\Ai\Illustrator::forCollection(\App\Models\Collection::findOrFail($id)))->afterResponse();
                    \Filament\Notifications\Notification::make()->title(__('ডিজাইনার প্রচ্ছদ আঁকছেন; দু-তিন মিনিট পরে পাতাটি আবার খুলুন'))->success()->send();
                }),
            Action::make('announce')->label(__('পাঠকদের ফোনে জানান'))->icon('heroicon-o-megaphone')
                ->visible(fn () => $this->record->status === 'approved' && ! \App\Support\CollectionNews::done($this->record, 'announced_at')
                    && \App\Support\WebPush::configured() && auth()->user()?->hasRole(\App\Enums\Role::Admin))
                ->requiresConfirmation()->modalDescription(fn () => __('সবার ফোনে যাবে').' ('.\App\Support\Bn::num((string) \App\Support\PushNotifier::audience('all')->count()).'): “'.$this->record->title.'”')
                ->action(function () {
                    $a = \App\Support\CollectionNews::announce($this->record, auth()->user());
                    \Filament\Notifications\Notification::make()->title($a ? \App\Support\Bn::num((string) $a->recipients).' '.__('টি ফোনে পাঠানো শুরু হবে (মিনিটে ২০০টি করে)') : __('আগেই পাঠানো হয়েছে, বা ফোনে খবর চালু নেই'))->send();
                }),
            Action::make('tellWriters')->label(fn () => __('লেখকদের জানান').' ('.\App\Support\Bn::num((string) \App\Support\CollectionNews::writers($this->record)->count()).')')
                ->icon('heroicon-o-envelope')
                ->visible(fn () => $this->record->status === 'approved' && ! \App\Support\CollectionNews::done($this->record, 'writers_told_at')
                    && auth()->user()?->hasRole(\App\Enums\Role::Admin) && \App\Support\CollectionNews::writers($this->record)->isNotEmpty())
                ->requiresConfirmation()->modalDescription(__('প্রত্যেক লেখক ই-মেল আর ফোনে জানবেন যে তাঁদের লেখা এই সংকলনে বাছাই হয়েছে। একবারই পাঠানো যায়।'))
                ->action(function () {
                    $n = \App\Support\CollectionNews::tellWriters($this->record, auth()->user());
                    \Filament\Notifications\Notification::make()->title(\App\Support\Bn::num((string) $n).' '.__('জন লেখককে জানানো হয়েছে'))->success()->send();
                }),
            Action::make('open')->label(__('পাতা দেখুন'))->icon('heroicon-o-eye')->color('gray')
                ->visible(fn () => filled($this->record->slug))->url(fn () => $this->record->url(), shouldOpenInNewTab: true),
            \App\Filament\Support\Deleting::page(static::$resource),
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
