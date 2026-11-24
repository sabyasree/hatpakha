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

    /**
     * D280 (UX audit): the magazine answers a reader under their comment. The answer is published at once (the editor
     * wrote it), the comment it answers too, and the reader is told as for any reply (D252).
     */
    public static function reply(Comment $c, string $text): Comment
    {
        $top = $c->parent_id ? $c->parent : $c;
        if ($c->status !== 'approved') {
            self::setStatus($c, 'approved');
        }
        $r = Comment::create(['post_id' => $c->post_id, 'parent_id' => $top->id, 'user_id' => auth()->id(), 'author_name' => 'হাতপাখা',
            'body' => trim(strip_tags($text)), 'status' => 'approved']);
        AuditLog::record('comment.replied', $r, ['to' => $c->id]);

        return $r;
    }

    public static function replyAction(string $name = 'reply'): Action
    {
        return Action::make($name)->label(__('উত্তর দিন'))->icon('heroicon-o-chat-bubble-left')->color('gray')
            ->modalHeading(__('হাতপাখার পক্ষ থেকে উত্তর'))
            ->schema(fn ($record, array $arguments) => [
                \Filament\Forms\Components\Placeholder::make('c')->label(fn () => ($record ?? Comment::find($arguments['comment'] ?? 0))?->author_name)
                    ->content(fn () => ($record ?? Comment::find($arguments['comment'] ?? 0))?->body),
                \Filament\Forms\Components\Textarea::make('text')->label(__('উত্তর (মন্তব্যের নিচে “হাতপাখা” নামে দেখা যাবে)'))->required()->rows(4)->maxLength(2000),
            ])
            ->modalDescription(__('মন্তব্যটি অপেক্ষায় থাকলে সেটিও প্রকাশ হবে। পাঠককে উত্তরের খবর দেওয়া হবে।'))
            ->modalSubmitActionLabel(__('উত্তর প্রকাশ করুন'))
            ->action(function (array $data, $record, array $arguments) {
                $c = $record instanceof Comment ? $record : Comment::findOrFail((int) ($arguments['comment'] ?? 0));
                self::reply($c, (string) $data['text']);
                \Filament\Notifications\Notification::make()->title(__('উত্তর প্রকাশিত'))->success()->send();
            });
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
                self::replyAction()->visible(fn (Comment $record) => $record->status !== 'spam'),
                Action::make('post')->label(__('লেখাটি'))->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Comment $record) => $record->post?->url())->openUrlInNewTab()
                    ->visible(fn (Comment $record) => $record->post?->status === 'published'),
                EditAction::make(),
                \App\Filament\Support\Deleting::row(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    \App\Filament\Support\Deleting::bulk(\App\Models\Comment::class),
                    BulkAction::make('approve')->label(__('প্রকাশ করুন'))->icon('heroicon-o-check')
                        ->action(fn (Collection $records) => $records->each(fn ($c) => self::setStatus($c, 'approved'))),
                    BulkAction::make('spam')->label(__('স্প্যাম'))->icon('heroicon-o-no-symbol')
                        ->action(fn (Collection $records) => $records->each(fn ($c) => self::setStatus($c, 'spam'))),
                ]),
            ]);
    }
}
