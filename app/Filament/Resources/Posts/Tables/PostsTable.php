<?php

namespace App\Filament\Resources\Posts\Tables;

use App\Enums\Role;
use App\Models\Post;
use App\Support\ContentAudit;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Set;
use App\Ai\EditorPackage;
use App\Support\Bn;
use App\Support\Review;
use App\Support\Schedule;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Toggle;
use App\Support\Transliterate;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PostsTable
{
    /** One label per Post::STATUSES entry (a test checks they stay in sync). */
    public const STATUS = ['published' => 'প্রকাশিত', 'pending' => 'অপেক্ষায়', 'changes' => 'বদল চাওয়া হয়েছে', 'confirm' => 'লেখকের সম্মতির অপেক্ষায়', 'draft' => 'খসড়া', 'scheduled' => 'নির্ধারিত', 'archived' => 'আর্কাইভ', 'rejected' => 'বাতিল'];

    public static function configure(Table $table): Table
    {
        $isAdmin = fn () => auth()->user()?->hasRole(Role::Admin) ?? false;
        $actionable = fn (Post $p) => $p->audit_label && $p->audit_label !== 'keep' && ! $p->audit_decision;

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['authors', 'section', 'latestAiReview']))
            ->defaultSort('published_at', 'desc')
            ->columns([
                TextColumn::make('title')->label(__('শিরোনাম'))->searchable()->wrap()->limit(80)
                    ->description(fn (Post $p) => trim(($p->status === 'draft' && $p->ai_drafted_at ? __('এআই খসড়া').' · ' : '')
                        .($p->authors->first()?->publicName() ?? '').' · '.($p->section?->name_bn ?? ''), ' ·')),
                TextColumn::make('status')->label(__('অবস্থা'))->badge()
                    ->formatStateUsing(fn ($state) => __(self::STATUS[$state] ?? $state))
                    ->color(fn ($state) => match ($state) { 'published' => 'success', 'pending' => 'warning', 'archived', 'rejected' => 'gray', default => 'info' }),
                TextColumn::make('published_at')->label(__('প্রকাশ'))->date('j M Y')->sortable()->toggleable(),
                TextColumn::make('submitted_at')->label(__('জমা'))->date('j M Y')->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('latestAiReview.score')->label(__('এআই'))->badge()->placeholder('—')
                    ->formatStateUsing(fn ($state) => \App\Support\Bn::num((string) $state).__('/১০'))
                    ->color(fn ($state) => $state >= 7 ? 'success' : ($state >= 5 ? 'warning' : 'gray'))
                    ->tooltip(fn (Post $record) => $record->latestAiReview?->report['summary'] ?? null)->toggleable(),
                TextColumn::make('audit_label')->label(__('অডিট'))->badge()
                    ->formatStateUsing(fn ($state, Post $p) => __(ContentAudit::LABELS[$state] ?? $state).($p->audit_decision ? ' ✓' : ''))
                    ->color(fn ($state) => match ($state) { 'remove' => 'danger', 'noindex' => 'warning', 'merge' => 'info', 'improve' => 'gray', default => 'success' })
                    ->tooltip(fn (Post $p) => implode(' · ', $p->audit_reasons ?? []))->toggleable(),
                TextColumn::make('backlog_tier')->label(__('স্তর'))->badge()->placeholder('—')
                    ->color(fn ($state) => match ($state) { 'A' => 'success', 'B' => 'gray', default => 'info' })
                    ->tooltip(fn (Post $p) => $p->backlog_tier ? __(ContentAudit::TIERS[$p->backlog_tier]).' · '.implode(' · ', $p->audit_reasons ?? []) : null)->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('অবস্থা'))->options(\App\Support\Lang::t(self::STATUS)),
                SelectFilter::make('section_id')->label(__('বিভাগ'))->options(fn () => \App\Models\Section::orderBy('sort')->pluck('name_bn', 'id')->all()),
                SelectFilter::make('language')->label(__('ভাষা'))->options(['bn' => __('বাংলা'), 'en' => __('ইংরেজি'), 'hi' => __('হিন্দি/উর্দু')]),
                SelectFilter::make('audit_label')->label(__('অডিট সুপারিশ'))->options(\App\Support\Lang::t(ContentAudit::LABELS)),
                TernaryFilter::make('audit_open')->label(__('অডিট সিদ্ধান্ত'))
                    ->trueLabel(__('বাকি আছে'))->falseLabel(__('নেওয়া হয়েছে'))
                    ->queries(true: fn (Builder $query) => $query->whereNull('audit_decision')->whereNotNull('audit_label')->where('audit_label', '!=', 'keep'),
                        false: fn (Builder $query) => $query->whereNotNull('audit_decision')),
                SelectFilter::make('backlog_tier')->label(__('অপেক্ষার স্তর'))->options(\App\Support\Lang::t(ContentAudit::TIERS)),
                TrashedFilter::make()->visible($isAdmin),
            ])
            ->recordActions([
                ...self::reviewActions(),
                EditAction::make(),
                Action::make('view')->label(__('দেখুন'))->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Post $p) => $p->url())->openUrlInNewTab()->visible(fn (Post $p) => $p->status === 'published'),
                // Applying audit suggestions changes what readers/Google see → admin only (CLAUDE.md §3).
                Action::make('applyAudit')->label(__('সুপারিশ মানুন'))->icon('heroicon-o-check')->color('warning')
                    ->visible(fn (Post $p) => $isAdmin() && $actionable($p))
                    ->requiresConfirmation()
                    ->modalHeading(fn (Post $p) => __(ContentAudit::LABELS[$p->audit_label]).': '.$p->title)
                    ->modalDescription(fn (Post $p) => self::effect($p).__(' কারণ: ').implode(' · ', $p->audit_reasons ?? []))
                    ->action(function (Post $p) {
                        ContentAudit::apply($p, auth()->user());
                        Notification::make()->title(__('সুপারিশ কার্যকর হয়েছে'))->success()->send();
                    }),
                Action::make('dismissAudit')->label(__('রাখুন'))->icon('heroicon-o-x-mark')->color('gray')
                    ->visible(fn (Post $p) => $isAdmin() && $actionable($p))
                    ->action(fn (Post $p) => ContentAudit::dismiss($p, auth()->user())),
                \App\Filament\Support\Deleting::row(),
                ...\App\Filament\Support\Deleting::trashActions(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    \App\Filament\Support\Deleting::bulk(\App\Models\Post::class),
                    ...\App\Filament\Support\Deleting::trashBulk(),
                    // D60: draw covers for several pieces at once (proposals; approve on ছবি অনুমোদন).
                    BulkAction::make('drawCovers')->label(__('এআই দিয়ে ছবি আঁকান'))->icon('heroicon-o-paint-brush')
                        ->requiresConfirmation()->modalDescription(__('একবারে ১০টি পর্যন্ত। ছবিগুলো তৈরি হলে "ছবি অনুমোদন" পাতায় দেখে বসাবেন।'))
                        ->action(function (\Illuminate\Support\Collection $records) {
                            $why = null;
                            foreach ($records->take(10) as $r) {
                                $why ??= \App\Ai\Illustrator::queue($r);
                            }
                            $why
                                ? Notification::make()->title(__('এখন আঁকা যাবে না: ').$why)->warning()->send()
                                : Notification::make()->title(__('আঁকা শুরু হয়েছে; কয়েক মিনিট পরে "ছবি অনুমোদন" দেখুন।'))->success()->send();
                        })->deselectRecordsAfterCompletion(),
                    // D73: certificates for several published pieces at once (old ones too); writers with accounts are told.
                    BulkAction::make('certificates')->label(__('শংসাপত্র দিন'))->icon('heroicon-o-academic-cap')
                        ->visible(fn () => auth()->user()?->hasRole(\App\Enums\Role::Admin))
                        ->requiresConfirmation()->modalDescription(__('বেছে নেওয়া প্রকাশিত লেখাগুলোর প্রত্যেক লেখক শংসাপত্র পাবেন (আগে দেওয়া থাকলে নতুন হবে না)। অ্যাকাউন্ট থাকলে ই-মেল যাবে।'))
                        ->action(function (\Illuminate\Support\Collection $records) {
                            $n = $records->filter(fn ($p) => $p->isLive())->take(50)->sum(fn ($p) => count(\App\Models\Certificate::issueFor($p)));
                            \App\Models\AuditLog::record('certificate.issued_bulk', null, ['count' => $n]);
                            Notification::make()->title(\App\Support\Bn::num((string) $n).__('টি নতুন শংসাপত্র দেওয়া হয়েছে'))->success()->send();
                        })->deselectRecordsAfterCompletion(),
                    BulkAction::make('applyAudit')->label(__('বেছে নেওয়া লেখায় সুপারিশ মানুন'))->icon('heroicon-o-check')->color('warning')
                        ->requiresConfirmation()
                        ->modalDescription(__('প্রতিটি লেখায় তার নিজের অডিট সুপারিশ কার্যকর হবে (গুগল থেকে লুকানো, বা আর্কাইভ করে ৪১০/৩০১)। সব বদল লগে থাকে এবং ফেরানো যায়।'))
                        ->action(function (Collection $records) {
                            $n = 0;
                            foreach ($records as $p) {
                                if ($p->audit_label && $p->audit_label !== 'keep' && ! $p->audit_decision) {
                                    ContentAudit::apply($p, auth()->user());
                                    $n++;
                                }
                            }
                            Notification::make()->title(__(':nটি লেখায় সুপারিশ কার্যকর হয়েছে', ['n' => $n]))->success()->send();
                        }),
                    BulkAction::make('dismissAudit')->label(__('বেছে নেওয়া লেখা রাখুন'))->icon('heroicon-o-x-mark')
                        ->action(fn (Collection $records) => $records->each(fn ($p) => ContentAudit::dismiss($p, auth()->user()))),
                ])->visible($isAdmin),
            ]);
    }

    /** Plain-language effect of applying a suggestion, shown before the admin confirms. */
    /**
     * Review decisions (3c) + AI report (3d), shared by the queue table and the post edit page.
     * A person always decides; the writer gets one kind email.
     *
     * @return array<Action>
     */
    public static function reviewActions(): array
    {
        return [
            // Review decisions (3c): a person always decides; the writer gets one kind email.
            Action::make('approve')->label(__('মনোনীত'))->icon('heroicon-o-check-badge')->color('success')
                ->visible(fn (Post $record) => $record->status === 'pending')
                ->modalHeading(fn (Post $record) => __('মনোনীত: ').$record->title)->modalSubmitActionLabel(__('মনোনীত করুন'))
                ->modalDescription(fn (Post $record) => self::whatHappens($record, 'approved'))
                ->schema([
                    // The AI editor's copy-edits (D42): only spelling/punctuation/spacing/typos are ticked by default.
                    CheckboxList::make('edits')->label(__('এআই-এর সংশোধন (টিক দেওয়াগুলো লেখায় বসবে)'))
                        ->options(fn (Post $record) => EditorPackage::editOptions($record))
                        ->default(fn (Post $record) => EditorPackage::safeIndexes($record))
                        ->bulkToggleable()->columns(1)
                        ->helperText(__('"শব্দ বদল" টিক দিলে লেখক আগে দেখে সম্মতি দেবেন, তারপর প্রকাশ হবে।'))
                        ->visible(fn (Post $record) => EditorPackage::edits($record) !== []),
                    // Proofreader (D50): a typo fix in the title, and its note for the editor.
                    Toggle::make('title_fix')->label(fn (Post $record) => __('শিরোনাম ঠিক করুন: ').($record->proof['title_fix'] ?? ''))->default(true)
                        ->visible(fn (Post $record) => ($record->proof['title_fix'] ?? '') !== ''),
                    \Filament\Forms\Components\Placeholder::make('proof_note')->label(__('প্রুফরিডারের মন্তব্য'))
                        ->content(fn (Post $record) => $record->proof['note'] ?? '')
                        ->visible(fn (Post $record) => ($record->proof['note'] ?? '') !== ''),
                    \Filament\Forms\Components\Placeholder::make('plagiarism')->label(__('মৌলিকতা যাচাই'))
                        ->content(fn (Post $record) => \App\Ai\PlagiarismCheck::summary($record))
                        ->visible(fn (Post $record) => \App\Ai\PlagiarismCheck::summary($record) !== ''),
                    Toggle::make('use_ai_image')->label(__('এআই-এর আঁকা প্রচ্ছদ ব্যবহার করুন'))->default(true)
                        ->helperText(fn (Post $record) => $record->proposed_image_path ? new \Illuminate\Support\HtmlString('<img src="'.e(asset($record->proposed_image_path)).'" alt="" style="margin-top:.5rem;max-height:10rem;border-radius:.75rem">') : null)
                        ->visible(fn (Post $record) => $record->proposed_image_path && ! $record->featured_image_path),
                    Toggle::make('metadata')->label(__('এআই-এর ট্যাগ, এক লাইনের পরিচিতি আর গুগলের তথ্য বসান'))->default(true)
                        ->visible(fn (Post $record) => EditorPackage::report($record) !== null),
                    DateTimePicker::make('at')->label(__('প্রকাশের সময়'))->default(fn () => Schedule::nextSlot())->required()->seconds(false)->native(false)->displayFormat('d/m/Y, h:i A')
                        ->helperText(__('পরের খালি দিন (সোম/বুধ/শুক্র সকাল ৯টা) দেওয়া আছে। এখনই চাইলে আজকের সময় দিন।')),
                    TextInput::make('slug')->label(__('ঠিকানা'))->prefix('/')->suffix('/')->maxLength(120)
                        ->default(fn (Post $record) => Transliterate::slug($record->title))
                        ->regex('/^[a-z0-9]+(-[a-z0-9]+)*$/')->helperText(__('ইংরেজি ছোট অক্ষর, সংখ্যা আর হাইফেন।')),
                    Textarea::make('note')->label(__('লেখককে বার্তা (ঐচ্ছিক)'))->rows(3)->maxLength(1500)->live(onBlur: true)
                        ->default(fn (Post $record) => (EditorPackage::report($record)['decision'] ?? null) === 'publish' ? EditorPackage::report($record)['note_to_writer'] : null),
                    self::emailPreview('approved'),
                ])
                ->action(function (Post $record, array $data) {
                    if ($data['use_ai_image'] ?? false) {
                        \App\Support\CoverImage::acceptProposed($record);
                    }
                    $result = EditorPackage::approve($record, auth()->user(), \Illuminate\Support\Carbon::parse($data['at']), $data['slug'] ?? null,
                        $data['note'] ?? null, (array) ($data['edits'] ?? []), (bool) ($data['metadata'] ?? false), (bool) ($data['title_fix'] ?? false));
                    Notification::make()->title($result === 'confirm' ? __('লেখকের সম্মতি চাওয়া হয়েছে; সম্মতি দিলেই প্রকাশ হবে') : __('মনোনীত হয়েছে, লেখককে জানানো হয়েছে'))->success()->send();
                }),
            Action::make('requestChanges')->label(__('বদল চাই'))->icon('heroicon-o-pencil-square')->color('warning')
                ->visible(fn (Post $record) => $record->status === 'pending')
                ->modalHeading(fn (Post $record) => __('বদল চাই: ').$record->title)->modalSubmitActionLabel(__('লেখককে পাঠান'))
                ->modalDescription(fn (Post $record) => self::whatHappens($record, 'changes'))
                ->schema([
                    Textarea::make('note')->label(__('লেখককে বার্তা'))->required()->rows(10)->maxLength(4000)->live(onBlur: true)
                        ->helperText(__('এআই-এর লেখা চিঠি বসানো আছে। যা খুশি বদলান; নিচে পুরো ই-মেলটি দেখুন।'))
                        ->default(fn (Post $record) => Review::changesMessage($record)),
                    Select::make('template')->label(__('এআই-এর খসড়ার বদলে তৈরি বার্তা নিতে চাইলে'))->placeholder(__('দরকার নেই'))
                        ->options(array_combine(array_keys(Review::CHANGE_TEMPLATES), array_map('__', array_keys(Review::CHANGE_TEMPLATES))))
                        ->live()->afterStateUpdated(fn ($state, Set $set) => $state ? $set('note', Review::CHANGE_TEMPLATES[$state] ?? '') : null),
                    self::emailPreview('changes'),
                    CheckboxList::make('edits')->label(__('ছোট সংশোধনগুলো এখনই লেখায় বসান'))
                        ->helperText(__('বানান, যতিচিহ্ন, ফাঁক আর টাইপো। লেখক সংশোধিত লেখাটি পাবেন আর সেখান থেকেই বদলাবেন। শব্দ বদল এখানে নেই।'))
                        ->options(fn (Post $record) => array_intersect_key(EditorPackage::editOptions($record), array_flip(EditorPackage::safeIndexes($record))))
                        ->default(fn (Post $record) => EditorPackage::safeIndexes($record))
                        ->bulkToggleable()->columns(1)
                        ->visible(fn (Post $record) => EditorPackage::safeIndexes($record) !== []),
                ])
                ->action(function (Post $record, array $data) {
                    $fixed = EditorPackage::applySafeEdits($record, (array) ($data['edits'] ?? []));
                    Review::requestChanges($record->refresh(), auth()->user(), $data['note']);
                    Notification::make()->title(__('লেখককে ই-মেল পাঠানো হয়েছে'))
                        ->body($fixed ? Bn::num((string) $fixed).__('টি ছোট সংশোধন লেখায় বসানো হয়েছে।') : null)->success()->send();
                }),
            Action::make('reject')->label(__('বাতিল'))->icon('heroicon-o-x-circle')->color('gray')
                ->visible(fn (Post $record) => $record->status === 'pending')
                ->modalHeading(fn (Post $record) => __('এবার নয়: ').$record->title)->modalSubmitActionLabel(__('লেখককে পাঠান'))
                ->modalDescription(fn (Post $record) => self::whatHappens($record, 'rejected'))
                ->schema([
                    Textarea::make('note')->label(__('লেখককে বার্তা'))->required()->rows(5)->maxLength(2000)->live(onBlur: true)
                        ->default(fn (Post $record) => (EditorPackage::report($record)['decision'] ?? null) === 'decline' ? EditorPackage::report($record)['note_to_writer'] : null),
                    Select::make('template')->label(__('তৈরি বার্তা নিতে চাইলে'))->placeholder(__('দরকার নেই'))
                        ->options(array_combine(array_keys(Review::REJECT_TEMPLATES), array_map('__', array_keys(Review::REJECT_TEMPLATES))))
                        ->live()->afterStateUpdated(fn ($state, Set $set) => $state ? $set('note', Review::REJECT_TEMPLATES[$state] ?? '') : null),
                    self::emailPreview('rejected'),
                ])
                ->action(function (Post $record, array $data) {
                    Review::reject($record, auth()->user(), $data['note']);
                    Notification::make()->title(__('লেখককে ই-মেল পাঠানো হয়েছে'))->send();
                }),
            // AI report (3d): advice for editors only; the decision stays with a person. The footer goes straight on
            // to the three decisions, the AI's choice first (owner, 2026-11-12: "whats next?").
            Action::make('aiReport')->label(__('এআই রিপোর্ট'))->icon('heroicon-o-sparkles')->color('info')
                ->visible(fn (Post $record) => $record->latestAiReview !== null)
                ->modalHeading(fn (Post $record) => __('এআই রিপোর্ট: ').$record->title)
                ->modalContent(fn (Post $record) => view('filament.ai-report', ['review' => $record->latestAiReview]))
                ->modalSubmitAction(false)->modalCancelActionLabel(__('বন্ধ করুন'))
                ->extraModalFooterActions(fn (Action $action, Post $record) => $record->status !== 'pending' ? [] : array_map(
                    fn (string $next) => $action->makeModalSubmitAction('go_'.$next, ['next' => $next])
                        ->label(__(self::DECISIONS[$next][0]))
                        ->color(self::recommended($record) === $next ? self::DECISIONS[$next][1] : 'gray'),
                    self::decisionOrder($record),
                ))
                ->action(function (Action $action, array $arguments, $livewire) {
                    if (in_array($next = $arguments['next'] ?? null, array_keys(self::DECISIONS), true)) {
                        $context = $livewire->mountedActions[array_key_last($livewire->mountedActions)]['context'] ?? [];
                        $livewire->replaceMountedAction($next, [], $context);
                        $action->halt();
                    }
                }),
            Action::make('runAi')->label(__('এআই দিয়ে পড়ান'))->icon('heroicon-o-arrow-path')->color('gray')
                ->visible(fn (Post $record) => in_array($record->status, ['pending', 'changes'], true))
                ->requiresConfirmation()->modalDescription(__('এআই একটি পরামর্শ-রিপোর্ট লিখবে (খরচ লগে থাকবে)। সিদ্ধান্ত আপনারই।'))
                ->action(function (Post $record) {
                    $review = app(\App\Ai\ReviewService::class)->run($record);
                    $review->status === 'done'
                        ? Notification::make()->title(__('এআই রিপোর্ট তৈরি: ').$review->score.__('/১০'))->success()->send()
                        : Notification::make()->title(__('রিপোর্ট হয়নি'))->body($review->error)->warning()->send();
                }),
        ];
    }

    /** The three decisions: button label, colour when it is the AI's choice. */
    public const DECISIONS = [
        'approve' => ['মনোনীত করুন', 'success'],
        'requestChanges' => ['বদল চাই', 'warning'],
        'reject' => ['এবার নয়', 'danger'],
    ];

    /** The decision the AI editor recommends, as an action name, or null. */
    public static function recommended(Post $p): ?string
    {
        return ['publish' => 'approve', 'changes' => 'requestChanges', 'decline' => 'reject'][EditorPackage::report($p)['decision'] ?? ''] ?? null;
    }

    /** @return list<string> the AI's choice first */
    public static function decisionOrder(Post $p): array
    {
        $all = array_keys(self::DECISIONS);
        $first = self::recommended($p);

        return $first ? array_values(array_unique([$first, ...$all])) : $all;
    }

    /**
     * The writer's e-mail inside the decision dialog, exactly as it will be sent, with the message as typed
     * (owner, 2026-11-12: "what email will go? where is the review before sending").
     */
    public static function emailPreview(string $decision): \Filament\Forms\Components\Placeholder
    {
        return \Filament\Forms\Components\Placeholder::make('email_preview')
            ->label(__('লেখক ঠিক এই ই-মেলটি পাবেন'))
            ->visible(fn (Post $record) => $record->submittedBy !== null)
            ->content(function (Post $record, $get) use ($decision) {
                $copy = clone $record;
                $copy->review_note = (string) $get('note');
                if ($decision === 'approved' && $get('at')) {
                    $copy->published_at = rescue(fn () => \Illuminate\Support\Carbon::parse($get('at')), now(), false);
                }
                $mail = (new \App\Notifications\SubmissionDecision($copy, $decision))->toMail($record->submittedBy);

                return new \Illuminate\Support\HtmlString('<p style="margin:0 0 .4rem;font-size:.9rem"><b>'.e(__('বিষয়:')).'</b> '.e((string) $mail->subject).'</p>'
                    .view('filament.partials.mail-preview', ['html' => (string) $mail->render(), 'height' => '28rem'])->render());
            });
    }

    /** Plain words for the dialog: what this button does, and who gets an e-mail. */
    public static function whatHappens(Post $p, string $decision): string
    {
        $who = $p->submittedBy ? __('লেখক (').$p->submittedBy->email.__(') ই-মেল পাবেন') : __('লেখকের অ্যাকাউন্ট নেই, তাই কোনও ই-মেল যাবে না');

        return match ($decision) {
            'changes' => __('লেখাটি লেখকের কাছে ফিরে যাবে। ').$who.__('; ই-মেলটি নিচে হুবহু দেখানো আছে। “লেখককে পাঠান” না চাপা পর্যন্ত কিছু যায় না। লেখক ঠিক করে (বা না বদলেই) আবার জমা দিলে লেখাটি আবার “অনুমোদনের অপেক্ষায়” আসবে, এআই আবার পড়বে।'),
            'approved' => __('লেখাটি নিচের দিনে-সময়ে সাইটে প্রকাশ হবে। ').$who.__('। টিক দেওয়া সংশোধনগুলো বসবে; “শব্দ বদল” টিক দিলে লেখক আগে দেখে সম্মতি দেবেন, তারপর প্রকাশ।'),
            'rejected' => __('লেখাটি এবার নেওয়া হবে না, সাইটে উঠবে না। ').$who.__('; ই-মেলটি নিচে হুবহু দেখানো আছে।'),
            default => '',
        };
    }

    public static function effect(Post $p): string
    {
        return match ($p->audit_label) {
            'noindex' => __('লেখাটি সাইটে থাকবে, কিন্তু গুগল-এ দেখাবে না।'),
            'remove' => __('লেখাটি আর্কাইভ হবে, আর তার ঠিকানা "410 — আর নেই" দেখাবে। পরে ফেরানো যায়।'),
            'merge' => __('লেখাটি আর্কাইভ হবে, আর তার ঠিকানা মূল লেখায় (#').$p->audit_merge_into_id.__(') চলে যাবে (৩০১)।'),
            default => __('শুধু সিদ্ধান্ত নথিভুক্ত হবে; লেখায় কোনও বদল হবে না।'),
        };
    }
}
