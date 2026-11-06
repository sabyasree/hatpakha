<?php

namespace App\Filament\Resources\Redirects\Schemas;

use App\Support\Slug;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * Admin-only. Paths are stored the way LegacyController::fallback looks them up:
 * decoded, lower-case NFC, with a leading and trailing slash. Targets are on-site paths only
 * (no open redirects, docs/SECURITY.md).
 */
class RedirectForm
{
    public static function path(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $value = parse_url($value, PHP_URL_PATH) ?: $value;

        return '/'.trim(Slug::normalize($value), '/').'/';
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('from_path')->label('পুরনো ঠিকানা')->required()->maxLength(480)
                ->placeholder('/purono-lekha/')->regex('#^/[^\s]*$#')
                ->dehydrateStateUsing(fn ($state) => self::path($state))
                ->unique(ignoreRecord: true)
                ->helperText('শুধু পাথ, / দিয়ে শুরু। যেমন /2016/03/kobita/'),
            Select::make('status_code')->label('কী হবে')->required()->default(301)->live()
                ->options([301 => '৩০১: নতুন ঠিকানায় পাঠাও', 410 => '৪১০: লেখাটি সরানো হয়েছে']),
            TextInput::make('to_path')->label('নতুন ঠিকানা')->maxLength(480)
                ->placeholder('/notun-lekha/')
                ->regex('#^/(?!/)[^\s]*$#')
                ->required(fn (Get $get) => (int) $get('status_code') === 301)
                ->visible(fn (Get $get) => (int) $get('status_code') === 301)
                ->dehydrateStateUsing(fn ($state) => self::path($state))
                ->helperText('এই সাইটেরই কোনো পাতা, / দিয়ে শুরু।'),
        ]);
    }
}
