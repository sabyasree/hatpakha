<?php

namespace App\Filament\Resources\PieceStats;

use App\Enums\Role;
use App\Filament\Resources\PieceStats\Pages\ListPieceStats;
use App\Models\Post;
use App\Support\Bn;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * D197 (owner, 2026-11-14: "there is no option to see the likes or visits for the pieces as an admin"): what readers did
 * with each published piece, in one sortable list. Counts only, from the site's own tables; no reader is named.
 */
class PieceStatResource extends Resource
{
    use \App\Filament\Concerns\TranslatedResourceLabels;

    protected static ?string $model = Post::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $modelLabel = 'লেখার হিসাব';

    protected static ?string $pluralModelLabel = 'লেখার হিসাব';

    protected static ?string $slug = 'piece-stats';

    protected static ?int $navigationSort = 4;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(Role::Editor) ?? false;
    }

    public static function canCreate(): bool { return false; }

    public static function canEdit(Model $record): bool { return false; }

    public static function canDelete(Model $record): bool { return false; }

    public static function canDeleteAny(): bool { return false; }

    public static function getEloquentQuery(): Builder
    {
        $views = fn (string $col, ?int $days = null) => DB::table('post_views')->whereColumn('post_views.post_id', 'posts.id')
            ->when($days, fn ($q) => $q->where('day', '>=', now()->subDays($days - 1)->toDateString()))->selectRaw("coalesce(sum({$col}), 0)");

        return Post::query()->where('status', 'published')->with('authors', 'section')
            ->select('posts.*')
            ->selectSub($views('views', 30), 'views_30')
            ->selectSub($views('views'), 'views_all')
            ->selectSub($views('search'), 'from_search')
            ->selectSub($views('social'), 'from_social')
            ->selectSub(DB::table('comments')->whereColumn('comments.post_id', 'posts.id')->where('status', 'approved')->selectRaw('count(*)'), 'comments_n')
            ->selectSub(DB::table('saved_posts')->whereColumn('saved_posts.post_id', 'posts.id')->selectRaw('count(*)'), 'saves_n')
            ->selectSub(DB::table('reading_history')->whereColumn('reading_history.post_id', 'posts.id')->where('progress', '>=', 90)->selectRaw('count(*)'), 'finished_n');
    }

    public static function table(Table $table): Table
    {
        $n = fn (string $name, string $label) => TextColumn::make($name)->label(__($label))->formatStateUsing(fn ($state) => Bn::num((string) (int) $state))->sortable()->alignEnd();

        return $table
            ->defaultSort('views_30', 'desc')
            ->emptyStateHeading(__('এখনও কোনো প্রকাশিত লেখা নেই'))
            ->columns([
                TextColumn::make('title')->label(__('লেখা'))->searchable()->wrap()->limit(70)->weight('bold')
                    ->description(fn (Post $record) => trim(($record->authors->first()?->publicName() ?? '').' · '.($record->section?->name_bn ?? '').' · '.Bn::num((string) $record->published_at?->timezone('Asia/Kolkata')->format('d/m/Y')), ' ·')),
                $n('views_30', '৩০ দিনে খোলা'),
                $n('views_all', 'মোট খোলা'),
                $n('likes_count', 'লাইক'),
                $n('comments_n', 'মতামত'),
                $n('saves_n', 'সেভ'),
                $n('finished_n', 'শেষ পর্যন্ত পড়েছেন')->toggleable(),
                $n('from_search', 'গুগল থেকে')->toggleable(isToggledHiddenByDefault: true),
                $n('from_social', 'সোশ্যাল থেকে')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('section_id')->label(__('বিভাগ'))->options(fn () => \App\Models\Section::orderBy('sort')->pluck('name_bn', 'id')),
                Filter::make('recent')->label(__('গত ৩০ দিনে প্রকাশিত'))->query(fn (Builder $query) => $query->where('published_at', '>=', now()->subDays(30))),
            ])
            ->recordActions([
                Action::make('open')->label(__('সাইটে দেখুন'))->icon('heroicon-o-arrow-top-right-on-square')->color('gray')->url(fn (Post $record) => $record->url())->openUrlInNewTab(),
                Action::make('desk')->label(__('ডেস্কে খুলুন'))->icon('heroicon-o-pencil-square')->color('gray')->url(fn (Post $record) => \App\Filament\Resources\Posts\PostResource::getUrl('edit', ['record' => $record])),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListPieceStats::route('/')];
    }
}
