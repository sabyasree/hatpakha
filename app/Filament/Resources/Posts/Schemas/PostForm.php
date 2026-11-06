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
            Section::make(__('লেখার পাঠ'))->columnSpan(2)->schema([
                TextInput::make('title')->label(__('শিরোনাম'))->required()->maxLength(400),
                TextInput::make('slug')->label(__('ঠিকানা'))->required()->maxLength(380)
                    ->helperText(__('প্রকাশিত লেখার ঠিকানা বদলালে পুরনো ঠিকানা থেকে পাঠক নিজে থেকেই নতুনটিতে পৌঁছবেন।'))
                    ->unique(ignoreRecord: true),
                Textarea::make('excerpt')->label(__('সারাংশ'))->rows(2),
                RichEditor::make('body_html')->label(__('লেখার পাঠ'))->required()
                    ->toolbarButtons([['bold', 'italic', 'underline', 'strike', 'superscript', 'subscript'], ['h3', 'blockquote', 'bulletList', 'orderedList', 'horizontalRule'], ['alignStart', 'alignCenter', 'alignEnd'], ['link', 'clearFormatting', 'undo', 'redo']])
                    // Stored alignment is a ta-* class (D40); the editor reads it as text-align.
                    ->formatStateUsing(fn (?string $state) => \App\Support\HtmlSanitizer::alignmentForEditor($state)),
            ]),
            Group::make()->columnSpan(1)->schema([
                // What an editor needs to decide on a form submission, without leaving the page.
                Section::make(__('জমার তথ্য'))->visible(fn (?Post $record) => $record?->submitted_by !== null)->schema([
                    Placeholder::make('cover')->label(__('প্রচ্ছদের ছবি'))
                        ->content(fn (Post $record) => $record->featured_image_path
                            ? new \Illuminate\Support\HtmlString('<img src="'.e(asset($record->featured_image_path)).'" alt="" style="max-height:12rem;border-radius:.75rem">')
                            : __('নেই')),
                    Placeholder::make('writer')->label(__('লেখক অ্যাকাউন্ট'))
                        ->content(fn (Post $record) => ($record->submittedBy?->name ?? '—').' · '.($record->submittedBy?->email ?? '')),
                    Placeholder::make('writer_history')->label(__('এর আগে প্রকাশিত'))
                        ->content(fn (Post $record) => \App\Support\Bn::num((int) $record->authors->sum('published_count')).__('টি লেখা')),
                    Placeholder::make('words')->label(__('দৈর্ঘ্য'))
                        ->content(fn (Post $record) => \App\Support\Bn::num(count(preg_split('/\s+/u', trim(strip_tags((string) $record->body_html)), -1, PREG_SPLIT_NO_EMPTY))).__(' শব্দ')),
                    Placeholder::make('submitted')->label(__('জমা'))
                        ->content(fn (Post $record) => \App\Support\Bn::num((string) ($record->submitted_at ?? $record->updated_at)?->diffForHumans()) ?: '—'),
                    Placeholder::make('original')->label(__('মৌলিকতার ঘোষণা'))
                        ->content(fn (Post $record) => $record->originality_confirmed_at ? __('লেখক নিশ্চিত করেছেন') : __('নেই')),
                    Placeholder::make('ai')->label(__('এআই পরামর্শ'))
                        ->content(fn (Post $record) => $record->latestAiReview?->score !== null ? \App\Support\Bn::num($record->latestAiReview->score).__('/১০ (উপরে "এআই রিপোর্ট")') : __('নেই')),
                    Placeholder::make('last_note')->label(__('শেষ বার্তা লেখককে'))
                        ->content(fn (Post $record) => $record->review_note ?: '—'),
                ]),
                Section::make(__('প্রকাশ'))->schema([
                    Select::make('status')->label(__('অবস্থা'))->required()->default('draft')
                        ->options(\App\Support\Lang::t(\App\Filament\Resources\Posts\Tables\PostsTable::STATUS)),
                    DateTimePicker::make('published_at')->label(__('প্রকাশের সময়'))->native(false)->displayFormat('d/m/Y, h:i A')->seconds(false),
                    Select::make('section_id')->label(__('বিভাগ'))->relationship('section', 'name_bn')->preload(),
                    Select::make('authors')->label(__('লেখক'))->relationship('authors', 'display_name')->multiple()->searchable()->required(),
                    Select::make('issue_id')->label(__('সংখ্যা'))->relationship('issue', 'title'),
                    Select::make('format')->label(__('পড়ার ধরন'))->options(['poem' => __('কবিতা (লাইন ধরে)'), 'prose' => __('গদ্য')])->required()->default('prose'),
                    Select::make('language')->label(__('ভাষা'))->options(['bn' => __('বাংলা'), 'en' => __('ইংরেজি'), 'hi' => __('হিন্দি')])->required()->default('bn'),
                ]),
            ]),
            Section::make(__('গুগল ও শেয়ার'))->columnSpan(2)->collapsed()->schema([
                TextInput::make('seo_title')->label(__('গুগলের জন্য শিরোনাম'))->maxLength(70),
                Textarea::make('seo_description')->label(__('গুগলের জন্য বিবরণ'))->maxLength(160)->rows(2),
                Toggle::make('noindex')->label(__('গুগলে দেখাবে না')),
            ]),
        ]);
    }
}
