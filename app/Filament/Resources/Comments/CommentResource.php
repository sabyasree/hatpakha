<?php

namespace App\Filament\Resources\Comments;

use App\Filament\Resources\Comments\Pages\EditComment;
use App\Filament\Resources\Comments\Pages\ListComments;
use App\Filament\Resources\Comments\Schemas\CommentForm;
use App\Filament\Resources\Comments\Tables\CommentsTable;
use App\Models\Comment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CommentResource extends Resource
{
    use \App\Filament\Concerns\TranslatedResourceLabels;
    use \App\Filament\Concerns\AdminDeletesOnly;

    protected static ?string $model = Comment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $modelLabel = 'মন্তব্য';

    protected static ?string $pluralModelLabel = 'মন্তব্য';
    protected static string|\UnitEnum|null $navigationGroup = 'আপনার সিদ্ধান্ত';   // D279

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return CommentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CommentsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListComments::route('/'),
            'edit' => EditComment::route('/{record}/edit'),
        ];
    }

    /** Waiting work, shown as a counter in the menu. */
    public static function getNavigationBadgeTooltip(): ?string
    {
        return \App\Support\DeskInbox::join([[\App\Models\Comment::where('status', 'pending')->count(), 'টি মন্তব্য অপেক্ষায়'], [\App\Models\Comment::where('status', 'pending')->where('ai_verdict', 'ok')->count(), 'টি এআই-এর মতে ঠিক']]);   // D279: every number says what it counts
    }

    public static function getNavigationBadge(): ?string
    {
        $n = \App\Models\Comment::where('status', 'pending')->count();

        return $n ? \App\Support\Bn::num((string) $n) : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';   // D279: red = waiting for you, grey = for information
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
