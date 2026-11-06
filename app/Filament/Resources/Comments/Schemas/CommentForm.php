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
            TextInput::make('author_name')->label('পাঠকের নাম')->required()->maxLength(120),
            Select::make('status')->label('অবস্থা')->options(\App\Filament\Resources\Comments\Tables\CommentsTable::STATUS)->required(),
            Textarea::make('body')->label('মন্তব্য')->rows(5)->required()->columnSpanFull()->helperText('শুধু বানান বা আপত্তিকর শব্দ ঠিক করতে বদলান; পাঠকের বক্তব্য নয়।'),
            Toggle::make('feature_opt_out')->label('পাঠক "পাঠকের চিঠি"-তে দেখাতে চান না'),
        ]);
    }
}
