<?php

namespace App\Filament\Resources\WritingRules\Pages;

use App\Filament\Resources\WritingRules\WritingRuleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWritingRules extends ListRecords
{
    protected static string $resource = WritingRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label(__('নিজের নির্দেশ যোগ করুন'))];
    }

    public function getSubheading(): ?string
    {
        return __('মানুষের মতামত থেকে আর আপনার নিজের লেখা নির্দেশ। এআই লেখকেরা প্রতিটি নতুন লেখায় চালু নির্দেশগুলো মেনে চলে; দুটি নির্দেশ পরস্পরবিরোধী হলে নতুনটি মানে।');
    }
}
