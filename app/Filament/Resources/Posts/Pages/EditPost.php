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
            Action::make('view')->label('সাইটে দেখুন')->icon('heroicon-o-arrow-top-right-on-square')->color('gray')
                ->url(fn (Post $record) => $record->url())->openUrlInNewTab()
                ->visible(fn (Post $record) => $record->status === 'published'),
            ...$review,
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
