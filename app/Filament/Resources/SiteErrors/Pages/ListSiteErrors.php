<?php

namespace App\Filament\Resources\SiteErrors\Pages;

use App\Filament\Resources\SiteErrors\SiteErrorResource;
use App\Models\SiteError;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListSiteErrors extends ListRecords
{
    protected static string $resource = SiteErrorResource::class;

    public function getSubheading(): ?string
    {
        return __('সাইটে কোনো পাতা বা কাজ ভেঙে গেলে তা এখানে ওঠে, একই ভুল একটিই সারিতে, কতবার হল তা সহ। নতুন কিছু দেখলে এই পাতার ছবি বা লেখাটুকু Claude-কে পাঠান।');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('seenAll')->label(__('সব দেখেছি'))->color('gray')->icon('heroicon-o-check')
                ->visible(fn () => SiteError::whereNull('seen_at')->exists())
                ->action(fn () => SiteError::whereNull('seen_at')->update(['seen_at' => now()])),
        ];
    }
}
