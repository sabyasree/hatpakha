<?php

namespace App\Filament\Resources\Sections\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SectionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('slug')
                    ->required(),
                TextInput::make('name_bn')
                    ->required(),
                TextInput::make('name_en')
                    ->default(null),
                TextInput::make('color')
                    ->required()
                    ->default('#CB1B32'),
                TextInput::make('format')
                    ->required()
                    ->default('prose'),
                TextInput::make('sort')
                    ->required()
                    ->numeric()
                    ->default(100),
                Toggle::make('is_active')
                    ->required(),
                TextInput::make('legacy_term_id')
                    ->numeric()
                    ->default(null),
            ]);
    }
}
