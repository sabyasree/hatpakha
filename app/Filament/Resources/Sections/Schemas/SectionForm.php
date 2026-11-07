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
            TextInput::make('name_bn')->label(__('নাম (বাংলা)'))->required()->maxLength(80),
            TextInput::make('name_en')->label(__('নাম (ইংরেজি)'))->maxLength(80),
            \Filament\Forms\Components\Textarea::make('about')->label(__('বিভাগের পরিচয়'))->rows(3)->maxLength(600)
                ->helperText(__('বিভাগের পাতায় শিরোনামের নিচে দেখায়; বিভাগ খালি থাকলে লেখকদের লিখতে ডাকে।')),
            // Section URLs are old WordPress category URLs: never change them (docs/SEO.md §1).
            TextInput::make('slug')->label(__('ঠিকানা'))->prefix('/category/')->suffix('/')->required()->maxLength(191)
                ->regex('/^[a-z0-9-]+$/')->unique(ignoreRecord: true)->disabledOn('edit')
                ->helperText(__('ছোট হাতের ইংরেজি অক্ষর, সংখ্যা ও হাইফেন। তৈরি হওয়ার পর বদলানো যায় না।')),
            Select::make('format')->label(__('পড়ার ধরন'))->required()->default('prose')
                ->options(['poem' => __('কবিতা (স্তবক)'), 'prose' => __('গদ্য (অনুচ্ছেদ)')]),
            Select::make('color')->label(__('রং'))->required()->default('#CB1B32')->options(\App\Support\Lang::t(self::COLOURS)),
            TextInput::make('sort')->label(__('ক্রম'))->numeric()->required()->default(100)->helperText(__('ছোট সংখ্যা আগে দেখায়।')),
            Select::make('nav_group')->label(__('মেনুতে কোন দলে'))->required()->default('literature')
                ->options(\App\Support\Lang::t(\App\Models\Section::GROUPS)),
            Toggle::make('in_top_bar')->label(__('উপরের মেনু-বারে দেখাবে'))
                ->helperText(__('বড় পর্দায় উপরে সরাসরি দেখায় (সবচেয়ে বেশি ৫টি)। বাকিগুলো "সব বিভাগ"-এ থাকে। কোনো লেখা প্রকাশ না হলে বিভাগটি মেনুতে আসে না।')),
            Toggle::make('is_active')->label(__('সাইটে দেখাবে'))->default(true),
        ]);
    }
}
