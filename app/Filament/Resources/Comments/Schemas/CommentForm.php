<?php

namespace App\Filament\Resources\Comments\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CommentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('author_name')->label(__('পাঠকের নাম'))->required()->maxLength(120),
            Select::make('status')->label(__('অবস্থা'))->options(\App\Support\Lang::t(\App\Filament\Resources\Comments\Tables\CommentsTable::STATUS))->required(),
            Textarea::make('body')->label(__('মন্তব্য'))->rows(5)->required()->columnSpanFull()->helperText(__('শুধু বানান বা আপত্তিকর শব্দ ঠিক করতে বদলান; পাঠকের বক্তব্য নয়।')),
            Toggle::make('feature_opt_out')->label(__('পাঠক "পাঠকের চিঠি"-তে দেখাতে চান না')),
        ]);
    }
}
