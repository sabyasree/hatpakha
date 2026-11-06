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
                TextInput::make('title')->label('শিরোনাম')->required()->maxLength(200),
                // The URL is fixed once created: old links and Google depend on it (docs/SEO.md §1).
                TextInput::make('slug')->label('ঠিকানা (URL)')->prefix('/')->suffix('/')
                    ->required()->maxLength(200)->unique(ignoreRecord: true)
                    ->regex('/^[\p{L}\p{M}\p{N}-]+$/u')
                    ->disabledOn('edit')->helperText('একবার তৈরি হলে বদলানো যায় না।'),
                RichEditor::make('body_html')->label('লেখা')->columnSpanFull()
                    ->toolbarButtons([['bold', 'italic', 'link'], ['h2', 'h3'], ['bulletList', 'orderedList', 'blockquote'], ['undo', 'redo']]),
                Section::make('প্রকাশ ও সার্চ')->columns(2)->schema([
                    Select::make('status')->label('অবস্থা')->options(['published' => 'প্রকাশিত', 'draft' => 'খসড়া'])->required()->default('published'),
                    TextInput::make('sort')->label('ফুটারে ক্রম')->numeric()->default(100),
                    Toggle::make('show_in_footer')->label('ফুটারে লিংক দেখাও'),
                    Toggle::make('noindex')->label('Google-এ দেখাবে না (noindex)'),
                    TextInput::make('seo_title')->label('SEO শিরোনাম')->maxLength(70),
                    TextInput::make('seo_description')->label('SEO বিবরণ')->maxLength(160),
                ]),
            ]);
    }
}
