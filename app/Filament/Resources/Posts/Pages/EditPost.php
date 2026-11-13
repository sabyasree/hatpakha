<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Enums\Role;
use App\Filament\Resources\Posts\PostResource;
use App\Filament\Resources\Posts\Tables\PostsTable;
use App\Models\Post;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\EditRecord;

class EditPost extends EditRecord
{
    protected static string $resource = PostResource::class;

    public function getTitle(): string
    {
        return $this->record->title;
    }

    protected function getHeaderActions(): array
    {
        // Deleting content needs the owner (CLAUDE.md §3): only admins see these; editors unpublish instead.
        $isAdmin = fn () => auth()->user()?->hasRole(Role::Admin) ?? false;

        // The same decision buttons as the queue, so an editor can read and decide in one place.
        // After a decision, go back to the queue for the next piece.
        $review = array_map(
            fn (Action $action) => in_array($action->getName(), ['approve', 'requestChanges', 'reject'], true)
                ? $action->after(fn () => $this->redirect(PostResource::getUrl('index')))
                : $action,
            PostsTable::reviewActions(),
        );

        return [
            Action::make('view')->label(__('সাইটে দেখুন'))->icon('heroicon-o-arrow-top-right-on-square')->color('gray')
                ->url(fn (Post $record) => $record->url())->openUrlInNewTab()
                ->visible(fn (Post $record) => $record->status === 'published'),
            ...$review,
            \App\Support\PieceReviews::assignAction(),   // D168: a human reviewer reads it
            // D171 (owner): every round with the writer, and what changed each time
            Action::make('history')->label(__('বদলের ইতিহাস'))->icon('heroicon-o-clock')->color('gray')
                ->modalHeading(fn (Post $record) => __('বদলের ইতিহাস').': '.$record->title)
                ->modalContent(fn (Post $record) => view('filament.post-history', ['post' => $record]))
                ->modalWidth('4xl')->modalSubmitAction(false)->modalCancelActionLabel(__('বন্ধ করুন')),
            Action::make('pieceReviews')->color('gray')->icon('heroicon-o-chat-bubble-bottom-center-text')
                ->label(fn (Post $record) => __('যাচাইকারীর মতামত').' ('.\App\Support\Bn::num((string) \App\Models\PieceReview::where('post_id', $record->id)->where('status', 'done')->count()).'/'.\App\Support\Bn::num((string) \App\Models\PieceReview::where('post_id', $record->id)->where('status', '!=', 'withdrawn')->count()).')')
                ->visible(fn (Post $record) => \App\Models\PieceReview::where('post_id', $record->id)->where('status', '!=', 'withdrawn')->exists())
                ->url(fn (Post $record) => \App\Filament\Resources\PieceReviews\PieceReviewResource::getUrl('index').'?post='.$record->id),
            // D113 (owner): a real person read this AI-written piece; their feedback rewrites it and/or guides the next ones.
            Action::make('humanFeedback')->label(__('মানুষের মতামত'))->icon('heroicon-o-chat-bubble-left-right')->color('warning')
                ->visible(fn (Post $record) => \App\Ai\Rewriter::canRewrite($record))
                ->modalHeading(__('কেউ লেখাটি পড়ে মতামত দিয়েছেন'))
                ->modalDescription(__('মতামত লিখুন, তারপর বাছুন: এই লেখাটিই আবার লেখানো, আর/অথবা পরের লেখাগুলোতে মেনে চলা। নতুন সংস্করণ আপনি দেখে তবেই বসাবেন।'))
                ->schema(fn (Post $record) => self::feedbackSchema($record))
                ->action(fn (Post $record, array $data) => self::saveFeedback($record, $data)),
            // The AI SEO expert (D46): run now, apply a waiting proposal, or undo its last pass.
            ActionGroup::make([
                Action::make('seoRun')->label(__('এসইও বিশেষজ্ঞকে দিয়ে করান'))->icon('heroicon-o-magnifying-glass')
                    ->visible(fn (Post $record) => $record->isLive() || $record->status === 'scheduled')
                    ->action(function (Post $record) {
                        $why = \App\Ai\SeoExpert::run($record);
                        $why ? \Filament\Notifications\Notification::make()->title(__('হলো না'))->body($why)->warning()->send()
                             : \Filament\Notifications\Notification::make()->title(__('এসইও তথ্য তৈরি'))->success()->send();
                        $this->refreshFormData(\App\Ai\SeoExpert::FIELDS);
                    }),
                Action::make('seoApply')->label(__('এসইও প্রস্তাব বসান'))->icon('heroicon-o-check')
                    ->visible(fn (Post $record) => filled($record->seo_proposal))
                    ->action(function (Post $record) {
                        $p = (array) $record->seo_proposal;
                        \App\Ai\SeoExpert::apply($record, array_intersect_key($p, array_flip(\App\Ai\SeoExpert::FIELDS)), (array) ($p['tags'] ?? []));
                        $this->refreshFormData(\App\Ai\SeoExpert::FIELDS);
                    }),
                Action::make('seoUndo')->label(__('এসইও-র শেষ বদল ফেরান'))->icon('heroicon-o-arrow-uturn-left')->requiresConfirmation()
                    ->visible(fn (Post $record) => $record->seo_done_at !== null)
                    ->action(function (Post $record) {
                        \App\Ai\SeoExpert::undo($record);
                        $this->refreshFormData(\App\Ai\SeoExpert::FIELDS);
                    }),
            ])->label(__('এসইও'))->icon('heroicon-o-magnifying-glass')->button()->color('gray'),
            // The AI editor's draft (e.g. the monthly editorial, D42): the owner edits above, then publishes.
            Action::make('publishDraft')->label(__('অনুমোদন ও প্রকাশ'))->icon('heroicon-o-check-badge')->color('success')
                ->visible(fn (Post $record) => $record->ai_drafted_at !== null && $record->status === 'draft' && (auth()->user()?->hasRole(\App\Enums\Role::Admin) ?? false))
                ->modalDescription(fn (Post $record) => $record->ai_note
                    ? __('আগে লেখাটি নিজের ভাষায় বদলে "সংরক্ষণ" করুন আর নোটের তথ্যগুলো যাচাই করুন।')
                    : __('আগে লেখাটি পড়ে প্রয়োজনমতো বদলে "সংরক্ষণ" করুন। প্রকাশ হবে আপনার নামে।'))
                ->schema(fn (Post $record) => array_filter([
                    \Filament\Forms\Components\DateTimePicker::make('at')->label(__('প্রকাশের সময়'))->default(now())->required()->seconds(false)->native(false)->displayFormat('d/m/Y, h:i A'),
                    // D95: a desk draft is published under the house byline or the owner's own name, never anyone else's
                    $record->ai_note && $record->authors()->where('is_desk', true)->exists() ? \Filament\Forms\Components\Radio::make('byline')->label(__('কার নামে'))->required()->default('desk')
                        ->options(['desk' => __('হাতপাখা ডেস্ক'), 'me' => __('আমার নামে (নিজে লিখে নিয়েছি)')]) : null,
                    $record->ai_note ? \Filament\Forms\Components\Checkbox::make('checked')->accepted()->validationMessages(['accepted' => __('আগে লেখাটি বদলে তথ্য যাচাই করুন।')])
                        ->label(__('লেখাটি নিজের মতো করে লিখেছি আর নোটের সব তথ্য যাচাই করেছি')) : null,
                ]))
                ->action(function (Post $record, array $data) {
                    $at = \Illuminate\Support\Carbon::parse($data['at']);
                    if ($record->ai_note && ($data['byline'] ?? 'desk') === 'me' && $record->authors()->where('is_desk', true)->exists()) {
                        $mine = \App\Models\Author::where('user_id', auth()->id())->first() ?? \App\Models\Author::create(['user_id' => auth()->id(), 'display_name' => auth()->user()->name, 'slug' => 'editor-'.auth()->id()]);
                        $record->authors()->sync([$mine->id => ['position' => 0]]);
                    }
                    if ($record->ai_note && str_starts_with((string) $record->slug, 'desk-')) {
                        $record->slug = \App\Support\Review::uniqueSlug(\App\Support\Transliterate::slug($record->title) ?: 'lekha-'.$record->id, $record->id);   // readable address from the final title (D14)
                    }
                    $record->forceFill(['status' => $at->isFuture() ? 'scheduled' : 'published', 'published_at' => $at, 'reviewed_by' => auth()->id(), 'reviewed_at' => now()])->save();
                    \App\Models\AuditLog::record('editorial.published', $record, ['by' => auth()->id()]);
                    \Filament\Notifications\Notification::make()->title(__('প্রকাশ করা হয়েছে'))->success()->send();
                    $this->redirect(PostResource::getUrl('edit', ['record' => $record]));
                }),
            // D71: the writer's certificate for this piece: issue (one per writer, e-mail with the link), open, withdraw.
            ActionGroup::make([
                Action::make('issueCertificate')->label(__('শংসাপত্র দিন / পাঠান'))->icon('heroicon-o-academic-cap')->color('success')
                    ->visible(fn (Post $record) => $record->isLive() && $record->isCertifiable())   // not for the desk's own pieces (2026-11-12)
                    ->modalHeading(__('এই লেখার প্রকাশনা শংসাপত্র'))
                    ->modalDescription(__('প্রত্যেক লেখকের জন্য একটি শংসাপত্র (আগে দেওয়া থাকলে সেটাই)। অ্যাকাউন্ট থাকলে লেখক ই-মেল পান আর "আমার লেখা"-তে দেখেন। অ্যাকাউন্ট না থাকলে নিচে ই-মেল দিন।'))
                    ->schema([\Filament\Forms\Components\TextInput::make('email')->label(__('ই-মেলেও পাঠান (ঐচ্ছিক)'))->email()->maxLength(190)])
                    ->modalSubmitActionLabel(__('শংসাপত্র দিন'))
                    ->action(fn (Post $record, array $data) => \App\Filament\Pages\Certificates::give($record, $data['email'] ?? null)),
                ...collect([0, 1, 2])->map(fn ($i) => Action::make('openCertificate'.$i)->icon('heroicon-o-document-text')
                    ->label(fn (Post $record) => __('শংসাপত্র দেখুন').': '.(self::certs($record)[$i]->author_name_snapshot ?? ''))
                    ->visible(fn (Post $record) => isset(self::certs($record)[$i]))
                    ->url(fn (Post $record) => self::certs($record)[$i]?->verifyUrl(), shouldOpenInNewTab: true))->all(),
                Action::make('revokeCertificate')->label(__('শংসাপত্র বাতিল করুন'))->icon('heroicon-o-no-symbol')->color('danger')
                    ->visible(fn (Post $record) => self::certs($record)->isNotEmpty())
                    ->requiresConfirmation()->modalDescription(__('যাচাইয়ের পাতায় লেখা থাকবে "বাতিল"। যেমন, পরে লেখাটি নকল প্রমাণিত হলে।'))
                    ->action(function (Post $record) {
                        \App\Models\Certificate::where('post_id', $record->id)->whereNull('revoked_at')->update(['revoked_at' => now()]);
                        \App\Models\AuditLog::record('certificate.revoked', $record);
                        \Filament\Notifications\Notification::make()->title(__('শংসাপত্র বাতিল হয়েছে'))->send();
                    }),
            ])->label(__('শংসাপত্র'))->icon('heroicon-o-academic-cap')->button()->color('gray')
                ->visible(fn () => $this->record->isLive()),
            // Editors can drop an unsuitable cover (the piece stays; the writer can upload another).
            // D60: draw (or redraw) the cover with the AI designer; the owner then puts it on, or drops it.
            ActionGroup::make([
                Action::make('drawCover')->icon('heroicon-o-paint-brush')
                    ->label(fn (Post $record) => $record->featured_image_path || $record->proposed_image_path ? __('নতুন ছবি আঁকান') : __('এআই দিয়ে ছবি আঁকান'))
                    ->modalHeading(__('গ্রাফিক ডিজাইনারকে দিয়ে প্রচ্ছদ আঁকান'))
                    ->modalDescription(__('লেখা পড়ে একটি ছবি আঁকা হবে (এক-দুই মিনিট লাগে)। তৈরি হলে এখানেই দেখে বসাবেন; আগের ছবি তার আগে বদলাবে না।'))
                    ->schema([\Filament\Forms\Components\Textarea::make('hint')->label(__('ছবির ভাবনা (ঐচ্ছিক)'))->rows(2)->maxLength(400)
                        ->placeholder(__('যেমন: বর্ষার সন্ধ্যায় নদীর ঘাট, একটা খালি নৌকা'))])
                    ->modalSubmitActionLabel(__('আঁকুন'))
                    ->action(function (Post $record, array $data) {
                        $why = \App\Ai\Illustrator::queue($record, $data['hint'] ?? null);
                        $why
                            ? \Filament\Notifications\Notification::make()->title(__('এখন আঁকা যাবে না: ').$why)->warning()->send()
                            : \Filament\Notifications\Notification::make()->title(__('আঁকার তালিকায় রাখা হলো। দুই-তিন মিনিট পরে পাতাটি আবার খুলুন।'))->success()->send();
                    }),
                Action::make('reviewDrawn')->label(__('আঁকা ছবি দেখুন'))->icon('heroicon-o-eye')->color('success')
                    ->visible(fn (Post $record) => (bool) $record->proposed_image_path)
                    ->modalHeading(__('এআই-এর আঁকা ছবি'))
                    ->modalContent(fn (Post $record) => new \Illuminate\Support\HtmlString('<img src="'.e(asset(ltrim((string) $record->proposed_image_path, '/'))).'" alt="" style="width:100%;border-radius:.75rem">'
                        .($record->featured_image_path ? '<p style="margin-top:.5rem;opacity:.75">'.e(__('বসালে এখনকার প্রচ্ছদের জায়গায় এটি বসবে।')).'</p>' : '')))
                    ->modalSubmitActionLabel(__('এটা বসান'))
                    ->extraModalFooterActions(fn (Action $action) => [
                        $action->makeModalSubmitAction('drop', ['drop' => true])->label(__('বাদ দিন'))->color('gray'),
                    ])
                    ->action(function (Post $record, array $arguments) {
                        if ($arguments['drop'] ?? false) {
                            \App\Support\CoverImage::discardProposed($record);
                            \Filament\Notifications\Notification::make()->title(__('বাদ দেওয়া হয়েছে'))->send();

                            return;
                        }
                        \App\Support\CoverImage::acceptProposed($record);
                        \App\Models\AuditLog::record('post.ai_images_approved', $record, ['count' => 1]);
                        $this->refreshFormData(['featured_image_path']);
                        \Filament\Notifications\Notification::make()->title(__('ছবি বসানো হয়েছে'))->success()->send();
                    }),
            ])->label(fn () => \App\Ai\Illustrator::drawing($this->record) ? __('ছবি আঁকা হচ্ছে…') : __('প্রচ্ছদ'))
                ->icon('heroicon-o-photo')->button()->color(fn () => $this->record->proposed_image_path ? 'success' : 'gray'),
            Action::make('removeCover')->label(__('প্রচ্ছদ সরান'))->icon('heroicon-o-photo')->color('gray')
                ->visible(fn (Post $record) => \App\Support\CoverImage::isOurs($record->featured_image_path))
                ->requiresConfirmation()->modalDescription(__('লেখকের দেওয়া প্রচ্ছদের ছবিটি মুছে যাবে। লেখাটি থাকবে।'))
                ->action(function (Post $record) {
                    \App\Support\CoverImage::remove($record);
                    \App\Models\AuditLog::record('post.cover_removed', $record);
                    $this->refreshFormData(['featured_image_path']);
                    \Filament\Notifications\Notification::make()->title(__('প্রচ্ছদ সরানো হয়েছে'))->success()->send();
                }),
            // Owner, 2026-11-09: a plain red "মুছুন" button, not hidden in a menu.
            \App\Filament\Support\Deleting::page(static::$resource),
            ...\App\Filament\Support\Deleting::trashActions(),
        ];
    }

    /** Marks the post as edited here, so a later WordPress re-import never overwrites it. */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['edited_in_desk_at'] = now();

        return $data;
    }

    /** @return \Illuminate\Support\Collection<int, \App\Models\Certificate> valid certificates for this piece */
    private static function certs(Post $record): \Illuminate\Support\Collection
    {
        return \App\Models\Certificate::where('post_id', $record->id)->whereNull('revoked_at')->orderBy('id')->get();
    }

    /** D113 form, also used from /desk → যাচাইকারীর মতামত to put a reviewer's words to work on an AI piece (D168). */
    public static function feedbackSchema(Post $record): array
    {
        return [
            \Filament\Forms\Components\TextInput::make('giver')->label(__('কে পড়েছেন (নাম বা পরিচয়, ঐচ্ছিক)'))->maxLength(120),
            \Filament\Forms\Components\Textarea::make('body')->label(__('মতামত'))->required()->rows(5)->maxLength(4000),
            \Filament\Forms\Components\Toggle::make('apply_now')->label(__('এই লেখাটিই আবার লেখানো হোক'))->default(true),
            \Filament\Forms\Components\Toggle::make('apply_future')->label(__('পরের লেখাগুলোতে মেনে চলা হোক'))->default(true)->live(),
            \Filament\Forms\Components\Radio::make('scope')->label(__('কোন লেখায় মেনে চলবে'))->required(fn ($get) => (bool) $get('apply_future'))
                ->visible(fn ($get) => (bool) $get('apply_future'))
                ->options(array_filter([
                    'section' => $record->section ? __('শুধু ":name" বিভাগে', ['name' => $record->section->name_bn]) : null,
                    'serial' => $record->serial ? __('শুধু ":name" ধারাবাহিকের পরের পর্বে', ['name' => $record->serial->title]) : null,
                    'all' => __('সব এআই লেখায়'),
                ]))->default($record->serial_id ? 'serial' : 'section'),
        ];
    }

    public static function saveFeedback(Post $record, array $data): void
    {
        if (! $data['apply_now'] && ! $data['apply_future']) {
            \Filament\Notifications\Notification::make()->title(__('কোনোটি বাছা হয়নি'))->body(__('আবার লেখানো বা পরের লেখায় মেনে চলা, অন্তত একটি বাছুন।'))->warning()->send();

            return;
        }
        \App\Models\WritingFeedback::create([
            'post_id' => $record->id, 'giver' => trim((string) ($data['giver'] ?? '')) ?: null, 'body' => trim(strip_tags((string) $data['body'])),
            'apply_now' => (bool) $data['apply_now'], 'apply_future' => (bool) $data['apply_future'],
            'scope' => $data['apply_future'] ? ($data['scope'] ?? 'section') : null,
            'section_id' => $record->section_id, 'serial_id' => $record->serial_id,
            'rewrite_status' => $data['apply_now'] ? 'queued' : null, 'created_by' => auth()->id(),
        ]);
        \App\Models\AuditLog::record('post.human_feedback', $record);
        \Filament\Notifications\Notification::make()->title(__('মতামত রাখা হয়েছে'))
            ->body($data['apply_now'] ? __('কয়েক মিনিটে এই পাতায় নতুন সংস্করণ আসবে; দেখে তবেই বসাবেন।') : __('পরের লেখা থেকে মেনে চলা হবে। "লেখার নির্দেশিকা" পাতায় দেখতে ও বদলাতে পারবেন।'))->success()->send();
    }
}
