<?php

namespace App\Filament\Resources\Comments\Pages;

use App\Filament\Resources\Comments\CommentResource;
use App\Models\Comment;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListComments extends ListRecords
{
    protected static string $resource = CommentResource::class;

    protected function getHeaderActions(): array
    {
        return []; // comments come from readers, not from the desk
    }

    public function getTabs(): array
    {
        return [
            'pending' => Tab::make('অপেক্ষায়')->badge(fn () => Comment::where('status', 'pending')->count() ?: null)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'pending')),
            'approved' => Tab::make('প্রকাশিত')->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'approved')),
            'spam' => Tab::make('স্প্যাম')->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'spam')),
            'all' => Tab::make('সব'),
        ];
    }
}
