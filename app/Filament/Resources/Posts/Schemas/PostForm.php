<?php

namespace App\Filament\Resources\Posts\Schemas;

use App\Models\Post;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
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
                TextInput::make('slug')->label('ঠিকানা')->required()->maxLength(380)
                    ->helperText('প্রকাশিত লেখার ঠিকানা বদলালে পুরনো ঠিকানা থেকে পাঠক নিজে থেকেই নতুনটিতে পৌঁছবেন।')
                    ->unique(ignoreRecord: true),
                Textarea::make('excerpt')->label('সারাংশ')->rows(2),
                RichEditor::make('body_html')->label('লেখা')->required()
                    ->toolbarButtons([['bold', 'italic', 'underline', 'strike', 'superscript', 'subscript'], ['h3', 'blockquote', 'bulletList', 'orderedList', 'horizontalRule'], ['alignStart', 'alignCenter', 'alignEnd'], ['link', 'clearFormatting', 'undo', 'redo']])
                    // Stored alignment is a ta-* class (D40); the editor reads it as text-align.
                    ->formatStateUsing(fn (?string $state) => \App\Support\HtmlSanitizer::alignmentForEditor($state)),
            ]),
            Group::make()->columnSpan(1)->schema([
                // What an editor needs to decide on a form submission, without leaving the page.
                Section::make('জমার তথ্য')->visible(fn (?Post $record) => $record?->submitted_by !== null)->schema([
                    Placeholder::make('cover')->label('প্রচ্ছদের ছবি')
                        ->content(fn (Post $record) => $record->featured_image_path
                            ? new \Illuminate\Support\HtmlString('<img src="'.e(asset($record->featured_image_path)).'" alt="" style="max-height:12rem;border-radius:.75rem">')
                            : 'নেই'),
                    Placeholder::make('writer')->label('লেখক অ্যাকাউন্ট')
                        ->content(fn (Post $record) => ($record->submittedBy?->name ?? '—').' · '.($record->submittedBy?->email ?? '')),
                    Placeholder::make('writer_history')->label('এর আগে প্রকাশিত')
                        ->content(fn (Post $record) => \App\Support\Bn::num((int) $record->authors->sum('published_count')).'টি লেখা'),
                    Placeholder::make('words')->label('দৈর্ঘ্য')
                        ->content(fn (Post $record) => \App\Support\Bn::num(count(preg_split('/\s+/u', trim(strip_tags((string) $record->body_html)), -1, PREG_SPLIT_NO_EMPTY))).' শব্দ'),
                    Placeholder::make('submitted')->label('জমা')
                        ->content(fn (Post $record) => \App\Support\Bn::num((string) ($record->submitted_at ?? $record->updated_at)?->diffForHumans()) ?: '—'),
                    Placeholder::make('original')->label('মৌলিকতার ঘোষণা')
                        ->content(fn (Post $record) => $record->originality_confirmed_at ? 'লেখক নিশ্চিত করেছেন' : 'নেই'),
                    Placeholder::make('ai')->label('এআই পরামর্শ')
                        ->content(fn (Post $record) => $record->latestAiReview?->score !== null ? \App\Support\Bn::num($record->latestAiReview->score).'/১০ (উপরে "এআই রিপোর্ট")' : 'নেই'),
                    Placeholder::make('last_note')->label('শেষ বার্তা লেখককে')
                        ->content(fn (Post $record) => $record->review_note ?: '—'),
                ]),
                Section::make('প্রকাশ')->schema([
                    Select::make('status')->label('অবস্থা')->required()->default('draft')
                        ->options(\App\Filament\Resources\Posts\Tables\PostsTable::STATUS),
                    DateTimePicker::make('published_at')->label('প্রকাশের সময়')->native(false)->displayFormat('d/m/Y, h:i A')->seconds(false),
                    Select::make('section_id')->label('বিভাগ')->relationship('section', 'name_bn')->preload(),
                    Select::make('authors')->label('লেখক')->relationship('authors', 'display_name')->multiple()->searchable()->required(),
                    Select::make('issue_id')->label('সংখ্যা')->relationship('issue', 'title'),
                    Select::make('format')->label('পড়ার ধরন')->options(['poem' => 'কবিতা (লাইন ধরে)', 'prose' => 'গদ্য'])->required()->default('prose'),
                    Select::make('language')->label('ভাষা')->options(['bn' => 'বাংলা', 'en' => 'ইংরেজি', 'hi' => 'হিন্দি'])->required()->default('bn'),
                ]),
            ]),
            Section::make('গুগল ও শেয়ার')->columnSpan(2)->collapsed()->schema([
                TextInput::make('seo_title')->label('গুগলের জন্য শিরোনাম')->maxLength(70),
                Textarea::make('seo_description')->label('গুগলের জন্য বিবরণ')->maxLength(160)->rows(2),
                Toggle::make('noindex')->label('গুগলে দেখাবে না'),
            ]),
        ]);
    }
}
