<?php

namespace App\Filament\Resources\PieceReviews;

use App\Enums\Role;
use App\Filament\Resources\PieceReviews\Pages\ListPieceReviews;
use App\Filament\Resources\Posts\Pages\EditPost;
use App\Filament\Resources\Posts\PostResource;
use App\Models\AuditLog;
use App\Models\PieceReview;
use App\Support\Bn;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** D168: what human reviewers said, beside what the AI said. Editors and above read; the admin assigns and withdraws. */
class PieceReviewResource extends Resource
{
    use \App\Filament\Concerns\TranslatedResourceLabels;

    protected static ?string $model = PieceReview::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $modelLabel = 'যাচাইকারীর মতামত';

    protected static ?string $pluralModelLabel = 'যাচাইকারীর মতামত';

    protected static ?string $slug = 'piece-reviews';

    protected static ?int $navigationSort = 6;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(Role::Editor) ?? false;
    }

    public static function canCreate(): bool { return false; }

    public static function canEdit(Model $record): bool { return false; }

    public static function canDelete(Model $record): bool { return false; }

    public static function canDeleteAny(): bool { return false; }

    /** New feedback the desk has not opened yet. */
    public static function getNavigationBadge(): ?string
    {
        $n = PieceReview::where('status', 'done')->whereNull('desk_read_at')->count();

        return $n ? Bn::num((string) $n) : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        $verdictColor = fn (?string $v) => ['good' => 'success', 'changes' => 'warning', 'not_yet' => 'danger'][$v] ?? 'gray';

        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['post.latestAiReview', 'reviewer']))
            ->defaultSort(fn ($query) => $query->orderByRaw("case status when 'done' then 0 when 'assigned' then 1 else 2 end")
                ->orderByRaw('desk_read_at is not null')->orderByDesc('submitted_at')->orderByDesc('created_at'))
            ->emptyStateHeading(__('এখনও কাউকে যাচাইয়ে দেওয়া হয়নি'))
            ->emptyStateDescription(__('প্রথমে প্রশাসন → ব্যবহারকারী থেকে কারও অ্যাকাউন্টে "যাচাইকারী" চালু করুন, তারপর লেখার পাশে "যাচাইয়ে পাঠান" টিপুন।'))
            ->columns([
                TextColumn::make('post.title')->label(__('লেখা'))->wrap()->weight('bold')
                    ->description(fn (PieceReview $record) => $record->desk_read_at || $record->status !== 'done' ? null : __('● নতুন মতামত'))
                    ->url(fn (PieceReview $record) => $record->post_id ? PostResource::getUrl('edit', ['record' => $record->post_id]) : null),
                TextColumn::make('reviewer.name')->label(__('যাচাইকারী'))->wrap(),
                TextColumn::make('status')->label(__('অবস্থা'))->badge()
                    ->formatStateUsing(fn ($state) => __(PieceReview::STATUSES[$state] ?? $state))
                    ->color(fn ($state) => ['done' => 'success', 'assigned' => 'warning'][$state] ?? 'gray')
                    ->description(fn (PieceReview $record) => $record->isOpen() && $record->due_on ? __('শেষ তারিখ: ').Bn::date($record->due_on) : null),
                TextColumn::make('verdict')->label(__('মত'))->badge()->placeholder('—')
                    ->formatStateUsing(fn ($state) => __(PieceReview::VERDICTS[$state] ?? $state))->color(fn ($state) => $verdictColor($state)),
                TextColumn::make('rating')->label(__('নম্বর'))->placeholder('—')
                    ->formatStateUsing(fn ($state) => Bn::num((string) $state).__(' / ৫')),
                TextColumn::make('ai')->label(__('এআই'))->placeholder('—')
                    ->state(fn (PieceReview $record) => $record->post?->latestAiReview?->score ? Bn::num((string) $record->post->latestAiReview->score).__(' / ১০') : null),
                TextColumn::make('submitted_at')->label(__('কবে'))->since()->sortable()->placeholder(__('এখনও দেননি')),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('অবস্থা'))->options(\App\Support\Lang::t(PieceReview::STATUSES)),
                SelectFilter::make('verdict')->label(__('মত'))->options(\App\Support\Lang::t(PieceReview::VERDICTS)),
                Filter::make('unread')->label(__('শুধু নতুন মতামত'))->query(fn ($query) => $query->where('status', 'done')->whereNull('desk_read_at')),
                SelectFilter::make('post_id')->label(__('কোন লেখা'))->relationship('post', 'title')->searchable(),
                SelectFilter::make('reviewer_id')->label(__('যাচাইকারী'))->relationship('reviewer', 'name')->searchable(),
            ])
            ->recordActions([
                Action::make('read')->label(__('পড়ুন'))->icon('heroicon-o-eye')->color('gray')
                    ->visible(fn (PieceReview $record) => $record->status === 'done')
                    ->modalHeading(fn (PieceReview $record) => $record->post?->title)
                    ->modalDescription(fn (PieceReview $record) => __('যাচাইকারী: ').$record->reviewer?->name.' · '.$record->submitted_at?->copy()->setTimezone('Asia/Kolkata')->format('d M Y, h:i A'))
                    ->mountUsing(function (PieceReview $record) {
                        if (! $record->desk_read_at) {
                            $record->forceFill(['desk_read_at' => now()])->save();   // opened = read (menu badge)
                        }
                    })
                    ->modalContent(fn (PieceReview $record) => \App\Filament\Support\Safe::view('filament.piece-review', ['review' => $record, 'ai' => $record->post?->latestAiReview]))
                    ->modalWidth('5xl')->modalSubmitAction(false)->modalCancelActionLabel(__('বন্ধ করুন'))
                    ->extraModalFooterActions(fn (PieceReview $record) => array_filter([
                        $record->post && \App\Ai\Rewriter::canRewrite($record->post)
                            ? Action::make('useForAi')->label(__('এআই লেখায় কাজে লাগান'))->color('warning')
                                ->modalHeading(__('এই মতামত এআই লেখায় কাজে লাগান'))
                                ->modalDescription(__('লেখাটি আবার লেখানো, আর/অথবা পরের এআই লেখায় মেনে চলা। নতুন সংস্করণ আপনি দেখে তবেই বসাবেন।'))
                                ->fillForm(fn () => ['giver' => $record->reviewer?->name.__(' (যাচাইকারী)'), 'body' => self::feedbackText($record)])
                                ->schema(EditPost::feedbackSchema($record->post))
                                ->action(fn (array $data) => EditPost::saveFeedback($record->post, $data))
                            : null,
                        Action::make('openPiece')->label(__('লেখাটি খুলুন'))->color('gray')
                            ->url(PostResource::getUrl('edit', ['record' => $record->post_id])),
                    ])),
                Action::make('withdraw')->label(__('ফিরিয়ে নিন'))->icon('heroicon-o-arrow-uturn-left')->color('gray')
                    ->visible(fn (PieceReview $record) => $record->isOpen() && auth()->user()?->hasRole(Role::Admin))
                    ->requiresConfirmation()->modalDescription(__('যাচাইকারী আর লেখাটি দেখতে পাবেন না। পরে আবার পাঠাতে পারবেন।'))
                    ->action(function (PieceReview $record) {
                        $record->update(['status' => 'withdrawn']);
                        AuditLog::record('review.withdrawn', $record->post, ['reviewer' => $record->reviewer_id, 'by' => auth()->id()]);
                        Notification::make()->title(__('ফিরিয়ে নেওয়া হয়েছে'))->success()->send();
                    }),
            ])
            ->recordAction('read');
    }

    /** The reviewer's words as one text (for the AI rewrite / rules form). */
    public static function feedbackText(PieceReview $r): string
    {
        return trim(implode("\n\n", array_filter([
            $r->verdict ? 'মত: '.(PieceReview::VERDICTS[$r->verdict] ?? $r->verdict) : null,
            $r->liked ? 'ভালো লেগেছে: '.$r->liked : null,
            $r->improve ? 'আরও ভালো হতে পারে: '.$r->improve : null,
            $r->after_ai ? 'এআই-এর রিপোর্ট পড়ে: '.$r->after_ai : null,
        ])));
    }

    public static function getPages(): array
    {
        return ['index' => ListPieceReviews::route('/')];
    }
}
