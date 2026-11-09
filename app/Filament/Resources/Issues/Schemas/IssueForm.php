<?php

namespace App\Filament\Resources\Issues\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class IssueForm
{
    /** The 12 cover themes from the design canvas (D9): 6 festival + 6 monthly. */
    public const THEMES = [
        'boi' => 'বইমেলা (উৎসব)', 'basanta' => 'বসন্ত (উৎসব)', 'noboborsho' => 'নববর্ষ (উৎসব)',
        'robindro' => 'রবীন্দ্র-নজরুল (উৎসব)', 'puja' => 'শারদীয়া (উৎসব)', 'dipaboli' => 'দীপাবলি (উৎসব)',
        'bhasha' => 'ভাষা (মাসিক)', 'grishmo' => 'গ্রীষ্ম (মাসিক)', 'barsha' => 'বর্ষা (মাসিক)',
        'srabon' => 'শ্রাবণ (মাসিক)', 'hemonto' => 'হেমন্ত (মাসিক)', 'borshosera' => 'বর্ষসেরা (মাসিক)',
    ];

    public const STATUS = ['proposed' => 'এআই-এর প্রস্তাব', 'planning' => 'পরিকল্পনা', 'published' => 'প্রকাশিত'];

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            // The AI editor's proposal (D42): what it chose and why. The owner approves from the top of the page.
            \Filament\Schemas\Components\Section::make(__('এআই সম্পাদকের প্রস্তাব'))->collapsible()->columnSpanFull()
                ->visible(fn ($record) => filled($record?->ai_plan))
                ->schema([
                    \Filament\Forms\Components\Placeholder::make('plan')->hiddenLabel()->content(fn ($record) => new \Illuminate\Support\HtmlString(
                        view('filament.issue-plan', ['issue' => $record])->render())),
                ]),
            // The graphic designer's issue cover (D51): AI artwork under our own Bengali masthead and title.
            \Filament\Schemas\Components\Section::make(__('সংখ্যার প্রচ্ছদ'))->collapsible()->columnSpanFull()->collapsible()
                ->visible(fn ($record) => $record && ($record->cover_path || $record->proposed_cover_path))
                ->schema([
                    \Filament\Forms\Components\Placeholder::make('cover')->hiddenLabel()->content(fn ($record) => new \Illuminate\Support\HtmlString(
                        view('filament.issue-cover', ['issue' => $record])->render())),
                ]),
            TextInput::make('title')->label(__('সংখ্যার নাম'))->required()->maxLength(120)->placeholder(__('যেমন: শারদীয়া ১৪৩৩')),
            TextInput::make('slug')->label(__('ঠিকানা'))->prefix('/sonkha/')->suffix('/')->required()->regex('/^\d{4}-\d{2}$/')
                ->unique(ignoreRecord: true)->helperText(__('বছর-মাস, যেমন 2026-10। প্রকাশের পর বদলাবেন না।'))
                ->disabled(fn ($record) => $record?->status === 'published'),
            Select::make('kind')->label(__('ধরন'))->options(['festival' => __('উৎসব সংখ্যা'), 'monthly' => __('মাসিক সংখ্যা')])->required()->default('monthly'),
            Select::make('theme_key')->label(__('প্রচ্ছদের থিম'))->options(\App\Support\Lang::t(self::THEMES))->required(),
            Select::make('status')->label(__('অবস্থা'))->options(\App\Support\Lang::t(self::STATUS))->required()->default('planning'),
            DateTimePicker::make('published_at')->label(__('প্রকাশের তারিখ'))->seconds(false),
            Textarea::make('editor_note')->label(__('সম্পাদকের কথা'))->rows(6)->maxLength(5000)->columnSpanFull()
                ->helperText(__('সংখ্যার শুরুতে ছাপা হয়। লেখা যোগ করতে: লেখা → খুলুন → "সংখ্যা" বেছে নিন।')),
        ]);
    }
}
