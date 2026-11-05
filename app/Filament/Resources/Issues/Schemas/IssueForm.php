<?php

namespace App\Filament\Resources\Issues\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class IssueForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('slug')
                    ->required(),
                TextInput::make('title')
                    ->required(),
                TextInput::make('kind')
                    ->required()
                    ->default('monthly'),
                TextInput::make('theme_key')
                    ->default(null),
                Textarea::make('editor_note')
                    ->default(null)
                    ->columnSpanFull(),
                TextInput::make('cover_path')
                    ->default(null),
                TextInput::make('status')
                    ->required()
                    ->default('planning'),
                DateTimePicker::make('published_at'),
            ]);
    }
}
