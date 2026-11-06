<?php

namespace App\Filament\Resources\Comments\Tables;

use App\Models\AuditLog;
use App\Models\Comment;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/** Comment moderation: approve or mark as spam, one by one or in bulk. */
class CommentsTable
{
    public const STATUS = ['pending' => 'অপেক্ষায়', 'approved' => 'প্রকাশিত', 'spam' => 'স্প্যাম'];

    public static function setStatus(Comment $c, string $status): void
    {
        $c->forceFill(['status' => $status])->save();
        AuditLog::record("comment.{$status}", $c);
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('post'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('body')->label(__('মন্তব্য'))->limit(160)->wrap()->searchable()
                    ->description(fn (Comment $record) => $record->author_name.($record->user_id ? '' : __(' (অতিথি)')).' · '.($record->post?->title ?? '')),
                TextColumn::make('status')->label(__('অবস্থা'))->badge()->formatStateUsing(fn ($state) => __(self::STATUS[$state] ?? $state))
                    ->color(fn ($state) => ['pending' => 'warning', 'approved' => 'success', 'spam' => 'gray'][$state] ?? 'gray'),
                IconColumn::make('feature_opt_out')->label(__('চিঠিতে নয়'))->boolean()->toggleable(),
                TextColumn::make('created_at')->label(__('কবে'))->since()->sortable(),
            ])
            ->recordActions([
                Action::make('approve')->label(__('প্রকাশ'))->icon('heroicon-o-check')->color('success')
                    ->visible(fn (Comment $record) => $record->status !== 'approved')
                    ->action(fn (Comment $record) => self::setStatus($record, 'approved')),
                Action::make('spam')->label(__('স্প্যাম'))->icon('heroicon-o-no-symbol')->color('gray')
                    ->visible(fn (Comment $record) => $record->status !== 'spam')
                    ->action(fn (Comment $record) => self::setStatus($record, 'spam')),
                Action::make('post')->label(__('লেখাটি'))->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Comment $record) => $record->post?->url())->openUrlInNewTab()
                    ->visible(fn (Comment $record) => $record->post?->status === 'published'),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('approve')->label(__('প্রকাশ করুন'))->icon('heroicon-o-check')
                        ->action(fn (Collection $records) => $records->each(fn ($c) => self::setStatus($c, 'approved'))),
                    BulkAction::make('spam')->label(__('স্প্যাম'))->icon('heroicon-o-no-symbol')
                        ->action(fn (Collection $records) => $records->each(fn ($c) => self::setStatus($c, 'spam'))),
                ]),
            ]);
    }
}
