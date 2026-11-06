<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Enums\Role;
use App\Filament\Resources\Posts\PostResource;
use App\Filament\Resources\Posts\Tables\PostsTable;
use App\Models\Post;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
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
            // The AI editor's draft (e.g. the monthly editorial, D42): the owner edits above, then publishes.
            Action::make('publishDraft')->label(__('অনুমোদন ও প্রকাশ'))->icon('heroicon-o-check-badge')->color('success')
                ->visible(fn (Post $record) => $record->ai_drafted_at !== null && $record->status === 'draft' && (auth()->user()?->hasRole(\App\Enums\Role::Admin) ?? false))
                ->modalDescription(__('আগে লেখাটি পড়ে প্রয়োজনমতো বদলে "সংরক্ষণ" করুন। প্রকাশ হবে আপনার নামে।'))
                ->schema([\Filament\Forms\Components\DateTimePicker::make('at')->label(__('প্রকাশের সময়'))->default(now())->required()->seconds(false)->native(false)->displayFormat('d/m/Y, h:i A')])
                ->action(function (Post $record, array $data) {
                    $at = \Illuminate\Support\Carbon::parse($data['at']);
                    $record->forceFill(['status' => $at->isFuture() ? 'scheduled' : 'published', 'published_at' => $at, 'reviewed_by' => auth()->id(), 'reviewed_at' => now()])->save();
                    \App\Models\AuditLog::record('editorial.published', $record, ['by' => auth()->id()]);
                    \Filament\Notifications\Notification::make()->title(__('প্রকাশ করা হয়েছে'))->success()->send();
                    $this->redirect(PostResource::getUrl('edit', ['record' => $record]));
                }),
            // Editors can drop an unsuitable cover (the piece stays; the writer can upload another).
            Action::make('removeCover')->label(__('প্রচ্ছদ সরান'))->icon('heroicon-o-photo')->color('gray')
                ->visible(fn (Post $record) => str_starts_with((string) $record->featured_image_path, 'storage/covers/'))
                ->requiresConfirmation()->modalDescription(__('লেখকের দেওয়া প্রচ্ছদের ছবিটি মুছে যাবে। লেখাটি থাকবে।'))
                ->action(function (Post $record) {
                    \App\Support\CoverImage::remove($record);
                    \App\Models\AuditLog::record('post.cover_removed', $record);
                    $this->refreshFormData(['featured_image_path']);
                    \Filament\Notifications\Notification::make()->title(__('প্রচ্ছদ সরানো হয়েছে'))->success()->send();
                }),
            ActionGroup::make([
                DeleteAction::make()->visible($isAdmin),
                ForceDeleteAction::make()->visible($isAdmin),
                RestoreAction::make()->visible($isAdmin),
            ])->visible($isAdmin),
        ];
    }

    /** Marks the post as edited here, so a later WordPress re-import never overwrites it. */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['edited_in_desk_at'] = now();

        return $data;
    }
}
