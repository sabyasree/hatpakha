<?php

namespace App\Filament\Resources\SentLetters\Pages;

use App\Filament\Resources\SentLetters\SentLetterResource;
use Filament\Resources\Pages\ListRecords;

class ListSentLetters extends ListRecords
{
    protected static string $resource = SentLetterResource::class;

    /** ?post=ID (from a piece's page) shows only that piece's letters. */
    public function mount(): void
    {
        parent::mount();
        if ($post = (int) request()->query('post')) {
            $this->tableFilters['post_id']['value'] = $post;
        }
    }
}
