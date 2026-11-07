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
                // D95: an AI draft's desk-only note (facts to check, photo ideas). Never shown to readers.
                Section::make(__('এআই খসড়ার নোট'))->visible(fn (?Post $record) => filled($record?->ai_note))->schema([
                    Placeholder::make('ai_note_text')->hiddenLabel()->content(fn (Post $record) => new \Illuminate\Support\HtmlString('<div style="white-space:pre-wrap;line-height:1.7">'.e($record->ai_note).'</div>')),
                ]),
                // What an editor needs to decide on a form submission, without leaving the page.
                Section::make(__('জমার তথ্য'))->visible(fn (?Post $record) => $record?->submitted_by !== null)->schema([
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
                // D64: the cover, and any picture the designer has drawn for it, always visible on the edit page.
                Section::make(__('প্রচ্ছদ'))->visible(fn (?Post $record) => $record !== null)->schema([
                    Placeholder::make('cover_now')->hiddenLabel()->content(fn (Post $record) => new \Illuminate\Support\HtmlString(
                        view('filament.post-cover', ['post' => $record])->render())),
                ])->footerActions([
                    \Filament\Actions\Action::make('useDrawn')->label(__('এটা বসান'))->icon('heroicon-o-check')->color('success')->size('sm')
                        ->visible(fn (?Post $record) => (bool) $record?->proposed_image_path)
                        ->action(function (Post $record, $livewire) {
                            \App\Support\CoverImage::acceptProposed($record);
                            \App\Models\AuditLog::record('post.ai_images_approved', $record, ['count' => 1]);
                            $livewire->refreshFormData(['featured_image_path']);
                            \Filament\Notifications\Notification::make()->title(__('ছবি বসানো হয়েছে'))->success()->send();
                        }),
                    \Filament\Actions\Action::make('dropDrawn')->label(__('বাদ দিন'))->icon('heroicon-o-x-mark')->color('gray')->size('sm')
                        ->visible(fn (?Post $record) => (bool) $record?->proposed_image_path)
                        ->action(function (Post $record) {
                            \App\Support\CoverImage::discardProposed($record);
                            \Filament\Notifications\Notification::make()->title(__('বাদ দেওয়া হয়েছে। ভুল হলে "ফিরিয়ে আনুন" টিপুন।'))->send();
                        }),
                    // D70: undo a drop, or go back to the cover that was replaced.
                    \Filament\Actions\Action::make('restoreSpare')->icon('heroicon-o-arrow-uturn-left')->color('gray')->size('sm')
                        ->label(fn (?Post $record) => ($record ? \App\Support\CoverImage::spare($record)['kind'] ?? '' : '') === 'dropped' ? __('বাদ দেওয়া ছবি ফিরিয়ে আনুন') : __('আগের প্রচ্ছদে ফিরুন'))
                        ->visible(fn (?Post $record) => $record && \App\Support\CoverImage::spare($record))
                        ->action(function (Post $record, $livewire) {
                            \App\Support\CoverImage::restoreSpare($record);
                            \App\Models\AuditLog::record('post.cover_restored', $record);
                            $livewire->refreshFormData(['featured_image_path']);
                            \Filament\Notifications\Notification::make()->title(__('ফিরিয়ে আনা হয়েছে'))->success()->send();
                        }),
                ]),
                // D73: the certificate, as the writer sees it (published pieces).
                Section::make(__('শংসাপত্র'))->visible(fn (?Post $record) => (bool) $record?->isLive() && $record->authors()->where('is_desk', false)->exists())
                    ->collapsible()->schema([
                        Placeholder::make('cert_now')->hiddenLabel()->content(function (Post $record) {
                            $c = \App\Models\Certificate::with('post.issue')->where('post_id', $record->id)->where('kind', 'publication')->whereNull('revoked_at')->first();
                            $sample = $c ?? new \App\Models\Certificate(['kind' => 'publication', 'post_id' => $record->id, 'author_name_snapshot' => $record->authors->first()?->publicName(),
                                'title_snapshot' => $record->title, 'issue_label' => $record->issue?->status === 'published' ? $record->issue->title : null]);
                            $sample->setRelation('post', $record);
                            $note = $c ? '<div style="font-size:.85rem;margin-top:.5rem"><a href="'.e($c->verifyUrl()).'" target="_blank" style="text-decoration:underline;font-weight:600">'.e(__('শংসাপত্রের পাতা খুলুন')).'</a> · '.e($c->code).'</div>'
                                : '<div style="font-size:.85rem;opacity:.75;margin-top:.5rem">'.e(__('এখনও দেওয়া হয়নি; এটি নমুনা। উপরের "শংসাপত্র" মেনু থেকে দিন।')).'</div>';

                            return new \Illuminate\Support\HtmlString(view('filament.partials.cert-preview', ['certificate' => $sample])->render().$note);
                        }),
                    ]),
                // D67: the writer's own reading: listen, then approve or remove.
                Section::make(__('লেখকের পাঠ'))->visible(fn (?Post $record) => (bool) $record?->audio_path)->schema([
                    Placeholder::make('audio_now')->hiddenLabel()->content(fn (Post $record) => new \Illuminate\Support\HtmlString(
                        '<audio controls preload="none" src="'.e($record->audio_path).'" style="width:100%"></audio><div style="font-size:.85rem;opacity:.75;margin-top:.35rem">'
                        .e($record->audio_status === 'approved' ? __('লেখার পাতায় চলছে।') : __('অনুমোদনের অপেক্ষায়। শুনে ঠিক থাকলে অনুমোদন দিন।')).'</div>')),
                ])->footerActions([
                    \Filament\Actions\Action::make('approveAudio')->label(__('অনুমোদন দিন'))->icon('heroicon-o-check')->color('success')->size('sm')
                        ->visible(fn (?Post $record) => $record?->audio_status === 'pending')
                        ->action(fn (Post $record) => \App\Support\AudioReading::approve($record)),
                    \Filament\Actions\Action::make('removeAudio')->label(__('পাঠ সরান'))->icon('heroicon-o-trash')->color('gray')->size('sm')->requiresConfirmation()
                        ->action(fn (Post $record) => \App\Support\AudioReading::remove($record)),
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
            Section::make(__('গুগল ও শেয়ার'))->columnSpan(2)->collapsible()->schema([
                TextInput::make('seo_title')->label(__('গুগলের জন্য শিরোনাম'))->maxLength(70),
                Textarea::make('seo_description')->label(__('গুগলের জন্য বিবরণ'))->maxLength(160)->rows(2),
                Toggle::make('noindex')->label(__('গুগলে দেখাবে না')),
                TextInput::make('seo_keyphrase')->label(__('মূল শব্দগুচ্ছ'))->maxLength(120),
                Textarea::make('share_text')->label(__('হোয়াটসঅ্যাপ / ফেসবুকের লেখা'))->maxLength(300)->rows(2),
                TextInput::make('featured_image_alt')->label(__('ছবির বর্ণনা (চোখে দেখতে না পাওয়া পাঠকের জন্য)'))->maxLength(200),
                Placeholder::make('related')->label(__('আরও পড়ুন (এসইও বিশেষজ্ঞের বাছাই)'))
                    ->content(fn (?Post $record) => $record && $record->related_ids ? Post::whereIn('id', $record->related_ids)->pluck('title')->implode(' · ') : '—'),
                Placeholder::make('seo_done')->label(__('এসইও বিশেষজ্ঞ'))
                    ->content(fn (?Post $record) => $record?->seo_proposal ? __('প্রস্তাব অপেক্ষায় (উপরে "এসইও প্রস্তাব বসান")') : ($record?->seo_done_at ? __('কাজ হয়েছে').' · '.\App\Support\Bn::num((string) $record->seo_done_at->diffForHumans()) : __('এখনও হয়নি'))),
]),
        ]);
    }
}
