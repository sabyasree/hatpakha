<?php

namespace App\Filament\Resources\PieceReviews\Pages;

use App\Filament\Resources\PieceReviews\PieceReviewResource;
use Filament\Resources\Pages\ListRecords;

class ListPieceReviews extends ListRecords
{
    protected static string $resource = PieceReviewResource::class;

    /** ?post=ID (from a piece's page) shows only that piece's reviews. */
    public function mount(): void
    {
        parent::mount();
        if ($post = (int) request()->query('post')) {
            $this->tableFilters['post_id']['value'] = $post;
        }
    }
}
