<?php

namespace App\Filament\Resources\Authors\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class AuthorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->default(null),
                TextInput::make('slug')
                    ->required(),
                TextInput::make('display_name')
                    ->required(),
                TextInput::make('display_name_bn')
                    ->default(null),
                Textarea::make('bio')
                    ->default(null)
                    ->columnSpanFull(),
                TextInput::make('avatar_path')
                    ->default(null),
                TextInput::make('cover_path')
                    ->default(null),
                TextInput::make('city')
                    ->default(null),
                Textarea::make('links')
                    ->default(null)
                    ->columnSpanFull(),
                Toggle::make('is_guest')
                    ->required(),
                Toggle::make('is_desk')
                    ->required(),
                DateTimePicker::make('claimed_at'),
                TextInput::make('legacy_wp_user_id')
                    ->numeric()
                    ->default(null),
                TextInput::make('legacy_coauthor_term_id')
                    ->numeric()
                    ->default(null),
                TextInput::make('published_count')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}
