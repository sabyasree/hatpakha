<?php

namespace App\Filament\Resources\Posts\Tables;

use App\Enums\Role;
use App\Models\Post;
use App\Support\ContentAudit;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PostsTable
{
    public const STATUS = ['published' => 'প্রকাশিত', 'pending' => 'অপেক্ষায়', 'draft' => 'খসড়া', 'scheduled' => 'নির্ধারিত', 'archived' => 'আর্কাইভ', 'rejected' => 'বাতিল'];

    public static function configure(Table $table): Table
    {
        $isAdmin = fn () => auth()->user()?->hasRole(Role::Admin) ?? false;
        $actionable = fn (Post $p) => $p->audit_label && $p->audit_label !== 'keep' && ! $p->audit_decision;

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['authors', 'section']))
            ->defaultSort('published_at', 'desc')
            ->columns([
                TextColumn::make('title')->label('শিরোনাম')->searchable()->wrap()->limit(80)
                    ->description(fn (Post $p) => trim(($p->authors->first()?->publicName() ?? '').' · '.($p->section?->name_bn ?? ''), ' ·')),
                TextColumn::make('status')->label('অবস্থা')->badge()
                    ->formatStateUsing(fn ($state) => self::STATUS[$state] ?? $state)
                    ->color(fn ($state) => match ($state) { 'published' => 'success', 'pending' => 'warning', 'archived', 'rejected' => 'gray', default => 'info' }),
                TextColumn::make('published_at')->label('প্রকাশ')->date('j M Y')->sortable()->toggleable(),
                TextColumn::make('submitted_at')->label('জমা')->date('j M Y')->sortable()->toggleable(isToggledHiddenByDefault: true),
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
