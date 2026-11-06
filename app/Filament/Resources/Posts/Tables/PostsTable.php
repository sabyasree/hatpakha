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
use App\Support\Review;
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
    public const STATUS = ['published' => 'প্রকাশিত', 'pending' => 'অপেক্ষায়', 'changes' => 'বদল চাওয়া হয়েছে', 'draft' => 'খসড়া', 'scheduled' => 'নির্ধারিত', 'archived' => 'আর্কাইভ', 'rejected' => 'বাতিল'];

    public static function configure(Table $table): Table
    {
        $isAdmin = fn () => auth()->user()?->hasRole(Role::Admin) ?? false;
        $actionable = fn (Post $p) => $p->audit_label && $p->audit_label !== 'keep' && ! $p->audit_decision;

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['authors', 'section', 'latestAiReview']))
            ->defaultSort('published_at', 'desc')
            ->columns([
                TextColumn::make('title')->label('শিরোনাম')->searchable()->wrap()->limit(80)
                    ->description(fn (Post $p) => trim(($p->authors->first()?->publicName() ?? '').' · '.($p->section?->name_bn ?? ''), ' ·')),
                TextColumn::make('status')->label('অবস্থা')->badge()
                    ->formatStateUsing(fn ($state) => self::STATUS[$state] ?? $state)
                    ->color(fn ($state) => match ($state) { 'published' => 'success', 'pending' => 'warning', 'archived', 'rejected' => 'gray', default => 'info' }),
                TextColumn::make('published_at')->label('প্রকাশ')->date('j M Y')->sortable()->toggleable(),
                TextColumn::make('submitted_at')->label('জমা')->date('j M Y')->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('latestAiReview.score')->label('AI')->badge()->placeholder('—')
                    ->formatStateUsing(fn ($state) => \App\Support\Bn::num((string) $state).'/১০')
                    ->color(fn ($state) => $state >= 7 ? 'success' : ($state >= 5 ? 'warning' : 'gray'))
                    ->tooltip(fn (Post $record) => $record->latestAiReview?->report['summary'] ?? null)->toggleable(),
                TextColumn::make('audit_label')->label('অডিট')->badge()
                    ->formatStateUsing(fn ($state, Post $p) => (ContentAudit::LABELS[$state] ?? $state).($p->audit_decision ? ' ✓' : ''))
                    ->color(fn ($state) => match ($state) { 'remove' => 'danger', 'noindex' => 'warning', 'merge' => 'info', 'improve' => 'gray', default => 'success' })
                    ->tooltip(fn (Post $p) => implode(' · ', $p->audit_reasons ?? []))->toggleable(),
                TextColumn::make('backlog_tier')->label('স্তর')->badge()->placeholder('—')
                    ->color(fn ($state) => match ($state) { 'A' => 'success', 'B' => 'gray', default => 'info' })
                    ->tooltip(fn (Post $p) => $p->backlog_tier ? ContentAudit::TIERS[$p->backlog_tier].' · '.implode(' · ', $p->audit_reasons ?? []) : null)->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('অবস্থা')->options(self::STATUS),
                SelectFilter::make('section_id')->label('বিভাগ')->options(fn () => \App\Models\Section::orderBy('sort')->pluck('name_bn', 'id')->all()),
                SelectFilter::make('language')->label('ভাষা')->options(['bn' => 'বাংলা', 'en' => 'English', 'hi' => 'हिन्दी/Urdu']),
                SelectFilter::make('audit_label')->label('অডিট সুপারিশ')->options(ContentAudit::LABELS),
                TernaryFilter::make('audit_open')->label('অডিট সিদ্ধান্ত')
                    ->trueLabel('বাকি আছে')->falseLabel('নেওয়া হয়েছে')
                    ->queries(true: fn (Builder $query) => $query->whereNull('audit_decision')->whereNotNull('audit_label')->where('audit_label', '!=', 'keep'),
                        false: fn (Builder $query) => $query->whereNotNull('audit_decision')),
                SelectFilter::make('backlog_tier')->label('অপেক্ষার স্তর')->options(ContentAudit::TIERS),
                TrashedFilter::make()->visible($isAdmin),
            ])
            ->recordActions([
                // Review decisions (3c): a person always decides; the writer gets one kind email.
                Action::make('approve')->label('মনোনীত')->icon('heroicon-o-check-badge')->color('success')
                    ->visible(fn (Post $record) => $record->status === 'pending')
                    ->modalHeading(fn (Post $record) => 'মনোনীত: '.$record->title)
                    ->schema([
                        DateTimePicker::make('at')->label('প্রকাশের সময়')->default(now())->required()->seconds(false)
                            ->helperText('ভবিষ্যতের সময় দিলে সেদিন নিজে থেকেই প্রকাশিত হবে।'),
                        TextInput::make('slug')->label('ঠিকানা (URL)')->prefix('/')->suffix('/')->maxLength(120)
                            ->default(fn (Post $record) => Transliterate::slug($record->title))
                            ->regex('/^[a-z0-9]+(-[a-z0-9]+)*$/')->helperText('ইংরেজি ছোট অক্ষর, সংখ্যা আর হাইফেন।'),
                        Textarea::make('note')->label('লেখককে কিছু বলবেন? (ঐচ্ছিক)')->rows(2)->maxLength(1000),
                    ])
                    ->action(function (Post $record, array $data) {
                        Review::approve($record, auth()->user(), \Illuminate\Support\Carbon::parse($data['at']), $data['slug'] ?? null, $data['note'] ?? null);
                        Notification::make()->title('মনোনীত হয়েছে, লেখককে জানানো হয়েছে')->success()->send();
                    }),
                Action::make('requestChanges')->label('বদল চাই')->icon('heroicon-o-pencil-square')->color('warning')
                    ->visible(fn (Post $record) => $record->status === 'pending')
                    ->modalHeading(fn (Post $record) => 'বদল চাই: '.$record->title)
                    ->schema([
                        Select::make('template')->label('তৈরি বার্তা থেকে শুরু করুন')->options(array_combine(array_keys(Review::CHANGE_TEMPLATES), array_keys(Review::CHANGE_TEMPLATES)))
                            ->live()->afterStateUpdated(fn ($state, Set $set) => $set('note', Review::CHANGE_TEMPLATES[$state] ?? '')),
                        Textarea::make('note')->label('লেখককে বার্তা')->required()->rows(5)->maxLength(2000)->helperText('সদয় ও নির্দিষ্ট থাকুন: কী ভালো লেগেছে, কী বদলালে ভালো হয়।'),
                    ])
                    ->action(function (Post $record, array $data) {
                        Review::requestChanges($record, auth()->user(), $data['note']);
                        Notification::make()->title('লেখককে জানানো হয়েছে')->success()->send();
                    }),
                Action::make('reject')->label('বাতিল')->icon('heroicon-o-x-circle')->color('gray')
                    ->visible(fn (Post $record) => $record->status === 'pending')
                    ->modalHeading(fn (Post $record) => 'এবার নয়: '.$record->title)
                    ->schema([
                        Select::make('template')->label('তৈরি বার্তা')->options(array_combine(array_keys(Review::REJECT_TEMPLATES), array_keys(Review::REJECT_TEMPLATES)))
                            ->live()->afterStateUpdated(fn ($state, Set $set) => $set('note', Review::REJECT_TEMPLATES[$state] ?? '')),
                        Textarea::make('note')->label('লেখককে বার্তা')->required()->rows(5)->maxLength(2000),
                    ])
                    ->action(function (Post $record, array $data) {
                        Review::reject($record, auth()->user(), $data['note']);
                        Notification::make()->title('লেখককে জানানো হয়েছে')->send();
                    }),
                // AI report (3d): advice for editors only; the decision stays with the buttons above.
                Action::make('aiReport')->label('AI রিপোর্ট')->icon('heroicon-o-sparkles')->color('info')
                    ->visible(fn (Post $record) => $record->latestAiReview !== null)
                    ->modalHeading(fn (Post $record) => 'AI রিপোর্ট: '.$record->title)
                    ->modalContent(fn (Post $record) => view('filament.ai-report', ['review' => $record->latestAiReview]))
                    ->modalSubmitAction(false)->modalCancelActionLabel('বন্ধ'),
                Action::make('runAi')->label('AI দিয়ে পড়ান')->icon('heroicon-o-arrow-path')->color('gray')
                    ->visible(fn (Post $record) => in_array($record->status, ['pending', 'changes'], true))
                    ->requiresConfirmation()->modalDescription('AI একটি পরামর্শ-রিপোর্ট লিখবে (খরচ লগে থাকবে)। সিদ্ধান্ত আপনারই।')
                    ->action(function (Post $record) {
                        $review = app(\App\Ai\ReviewService::class)->run($record);
                        $review->status === 'done'
                            ? Notification::make()->title('AI রিপোর্ট তৈরি: '.$review->score.'/১০')->success()->send()
                            : Notification::make()->title('রিপোর্ট হয়নি')->body($review->error)->warning()->send();
                    }),
                EditAction::make(),
                Action::make('view')->label('দেখুন')->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Post $p) => $p->url())->openUrlInNewTab()->visible(fn (Post $p) => $p->status === 'published'),
                // Applying audit suggestions changes what readers/Google see → admin only (CLAUDE.md §3).
                Action::make('applyAudit')->label('সুপারিশ মানুন')->icon('heroicon-o-check')->color('warning')
                    ->visible(fn (Post $p) => $isAdmin() && $actionable($p))
                    ->requiresConfirmation()
                    ->modalHeading(fn (Post $p) => ContentAudit::LABELS[$p->audit_label].': '.$p->title)
                    ->modalDescription(fn (Post $p) => self::effect($p).' কারণ: '.implode(' · ', $p->audit_reasons ?? []))
                    ->action(function (Post $p) {
                        ContentAudit::apply($p, auth()->user());
                        Notification::make()->title('সুপারিশ কার্যকর হয়েছে')->success()->send();
                    }),
                Action::make('dismissAudit')->label('রাখুন')->icon('heroicon-o-x-mark')->color('gray')
                    ->visible(fn (Post $p) => $isAdmin() && $actionable($p))
                    ->action(fn (Post $p) => ContentAudit::dismiss($p, auth()->user())),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('applyAudit')->label('বেছে নেওয়া লেখায় সুপারিশ মানুন')->icon('heroicon-o-check')->color('warning')
                        ->requiresConfirmation()
                        ->modalDescription('প্রতিটি লেখায় তার নিজের অডিট সুপারিশ কার্যকর হবে (Google থেকে লুকানো, বা আর্কাইভ করে 410/301)। সব বদল লগে থাকে এবং ফেরানো যায়।')
                        ->action(function (Collection $records) {
                            $n = 0;
                            foreach ($records as $p) {
                                if ($p->audit_label && $p->audit_label !== 'keep' && ! $p->audit_decision) {
                                    ContentAudit::apply($p, auth()->user());
                                    $n++;
                                }
                            }
                            Notification::make()->title("{$n}টি লেখায় সুপারিশ কার্যকর হয়েছে")->success()->send();
                        }),
                    BulkAction::make('dismissAudit')->label('বেছে নেওয়া লেখা রাখুন')->icon('heroicon-o-x-mark')
                        ->action(fn (Collection $records) => $records->each(fn ($p) => ContentAudit::dismiss($p, auth()->user()))),
                ])->visible($isAdmin),
            ]);
    }

    /** Plain-language effect of applying a suggestion, shown before the admin confirms. */
    public static function effect(Post $p): string
    {
        return match ($p->audit_label) {
            'noindex' => 'লেখাটি সাইটে থাকবে, কিন্তু Google-এ দেখাবে না।',
            'remove' => 'লেখাটি আর্কাইভ হবে, আর তার ঠিকানা "410 — আর নেই" দেখাবে। পরে ফেরানো যায়।',
            'merge' => 'লেখাটি আর্কাইভ হবে, আর তার ঠিকানা মূল লেখায় (#'.$p->audit_merge_into_id.') চলে যাবে (301)।',
            default => 'শুধু সিদ্ধান্ত নথিভুক্ত হবে; লেখায় কোনও বদল হবে না।',
        };
    }
}
