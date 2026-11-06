<?php

namespace App\Filament\Resources\Sections\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

/** Admin-only. Colours are limited to the logo palette (docs/DESIGN.md). */
class SectionForm
{
    /** Section colours: palette colours that carry white text (docs/DESIGN.md). */
    public const COLOURS = [
        '#CB1B32' => 'লাল', '#B0162B' => 'গাঢ় লাল', '#7A1022' => 'মেরুন',
        '#13212C' => 'নেভি', '#12304A' => 'রাতের আকাশ', '#1A86C2' => 'গাঢ় আকাশি',
        '#2DAAE2' => 'আকাশি', '#E39A6A' => 'গাঢ় পীচ',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name_bn')->label('নাম (বাংলা)')->required()->maxLength(80),
            TextInput::make('name_en')->label('নাম (ইংরেজি)')->maxLength(80),
            // Section URLs are old WordPress category URLs: never change them (docs/SEO.md §1).
            TextInput::make('slug')->label('ঠিকানা')->prefix('/category/')->suffix('/')->required()->maxLength(191)
                ->regex('/^[a-z0-9-]+$/')->unique(ignoreRecord: true)->disabledOn('edit')
                ->helperText('ছোট হাতের ইংরেজি অক্ষর, সংখ্যা ও হাইফেন। তৈরি হওয়ার পর বদলানো যায় না।'),
            Select::make('format')->label('পড়ার ধরন')->required()->default('prose')
                ->options(['poem' => 'কবিতা (স্তবক)', 'prose' => 'গদ্য (অনুচ্ছেদ)']),
            Select::make('color')->label('রং')->required()->default('#CB1B32')->options(self::COLOURS),
            TextInput::make('sort')->label('ক্রম')->numeric()->required()->default(100)->helperText('ছোট সংখ্যা আগে দেখায়।'),
            Toggle::make('is_active')->label('সাইটে দেখাবে')->default(true),
        ]);
    }
}
