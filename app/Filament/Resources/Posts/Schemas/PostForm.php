<?php

namespace App\Filament\Resources\Posts\Schemas;

use App\Models\Post;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/** Editor form. body_html is sanitised again on save by the Post model (docs/SECURITY.md §4). */
class PostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make('লেখা')->columnSpan(2)->schema([
                TextInput::make('title')->label('শিরোনাম')->required()->maxLength(400),
                TextInput::make('slug')->label('URL (slug)')->required()->maxLength(380)
                    ->helperText('প্রকাশের পরে বদলালে পুরনো URL থেকে 301 রিডাইরেক্ট দরকার।')
                    ->unique(ignoreRecord: true),
                Textarea::make('excerpt')->label('সারাংশ')->rows(2),
                RichEditor::make('body_html')->label('লেখা')->required()
                    ->toolbarButtons(['bold', 'italic', 'blockquote', 'h2', 'h3', 'bulletList', 'orderedList', 'link', 'undo', 'redo']),
            ]),
            Section::make('প্রকাশ')->columnSpan(1)->schema([
                Select::make('status')->label('অবস্থা')->required()->default('draft')
                    ->options(\App\Filament\Resources\Posts\Tables\PostsTable::STATUS),
                DateTimePicker::make('published_at')->label('প্রকাশের সময়'),
                Select::make('section_id')->label('বিভাগ')->relationship('section', 'name_bn')->preload(),
                Select::make('authors')->label('লেখক')->relationship('authors', 'display_name')->multiple()->searchable()->required(),
                Select::make('issue_id')->label('সংখ্যা')->relationship('issue', 'title'),
                Select::make('format')->label('পড়ার ধরন')->options(['poem' => 'কবিতা (লাইন ধরে)', 'prose' => 'গদ্য'])->required()->default('prose'),
                Select::make('language')->label('ভাষা')->options(['bn' => 'বাংলা', 'en' => 'English', 'hi' => 'हिन्दी'])->required()->default('bn'),
            ]),
            Section::make('SEO')->columnSpan(2)->collapsed()->schema([
                TextInput::make('seo_title')->label('SEO শিরোনাম')->maxLength(70),
                Textarea::make('seo_description')->label('SEO বর্ণনা')->maxLength(160)->rows(2),
                Toggle::make('noindex')->label('Google-এ দেখাবে না (noindex)'),
            ]),
        ]);
    }
}
