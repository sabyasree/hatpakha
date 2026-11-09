<?php

namespace App\Filament\Resources\WritingRules\Pages;

use App\Filament\Resources\WritingRules\WritingRuleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateWritingRule extends CreateRecord
{
    protected static string $resource = WritingRuleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['lesson'] = trim(strip_tags((string) $data['lesson']));

        return $data + ['body' => $data['lesson'], 'apply_future' => true, 'apply_now' => false, 'created_by' => auth()->id()];
    }
}
