<?php

namespace App\Filament\Resources\WritingRules\Pages;

use App\Filament\Resources\WritingRules\WritingRuleResource;
use Filament\Resources\Pages\EditRecord;

class EditWritingRule extends EditRecord
{
    protected static string $resource = WritingRuleResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['lesson'] = trim(strip_tags((string) $data['lesson']));

        return $data;
    }
}
