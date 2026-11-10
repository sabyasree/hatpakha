<?php

namespace App\Filament\Resources\Contests\Pages;

use App\Filament\Resources\Contests\ContestResource;
use App\Models\AuditLog;
use Filament\Resources\Pages\CreateRecord;

class CreateContest extends CreateRecord
{
    protected static string $resource = ContestResource::class;

    protected function afterCreate(): void
    {
        AuditLog::record('contest.created', null, ['contest' => $this->record->id]);
    }
}
