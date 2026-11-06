<?php

namespace App\Filament\Resources\Pages\Schemas;

use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')->label(__('শিরোনাম'))->required()->maxLength(200),
                // The URL is fixed once created: old links and Google depend on it (docs/SEO.md §1).
                TextInput::make('slug')->label(__('ঠিকানা'))->prefix('/')->suffix('/')
                    ->required()->maxLength(200)->unique(ignoreRecord: true)
                    ->regex('/^[\p{L}\p{M}\p{N}-]+$/u')
                    ->disabledOn('edit')->helperText(__('একবার তৈরি হলে বদলানো যায় না।')),
                RichEditor::make('body_html')->label(__('লেখা'))->columnSpanFull()
                    ->toolbarButtons([['bold', 'italic', 'link'], ['h2', 'h3'], ['bulletList', 'orderedList', 'blockquote'], ['undo', 'redo']]),
                Section::make(__('প্রকাশ ও সার্চ'))->columns(2)->schema([
                    Select::make('status')->label(__('অবস্থা'))->options(['published' => __('প্রকাশিত'), 'draft' => __('খসড়া')])->required()->default('published'),
                    TextInput::make('sort')->label(__('ফুটারে ক্রম'))->numeric()->default(100),
                    Toggle::make('show_in_footer')->label(__('ফুটারে লিংক দেখাও')),
                    Toggle::make('noindex')->label(__('গুগলে দেখাবে না')),
                    TextInput::make('seo_title')->label(__('গুগলের জন্য শিরোনাম'))->maxLength(70),
                    TextInput::make('seo_description')->label(__('গুগলের জন্য বিবরণ'))->maxLength(160),
                ]),
            ]);
    }
}
