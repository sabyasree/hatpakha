<?php

namespace App\Filament\Resources\Contests;

use App\Filament\Resources\Contests\Pages\CreateContest;
use App\Filament\Resources\Contests\Pages\EditContest;
use App\Filament\Resources\Contests\Pages\ListContests;
use App\Models\Contest;
use App\Models\Section;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Writing contests (D53). The contest manager prepares; the owner opens, judges and announces. */
class ContestResource extends Resource
{
    use \App\Filament\Concerns\TranslatedResourceLabels;
    use \App\Filament\Concerns\AdminDeletesOnly;

    protected static ?string $model = Contest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrophy;

    protected static ?string $modelLabel = 'প্রতিযোগিতা';

    protected static ?string $pluralModelLabel = 'প্রতিযোগিতা';

    protected static string|\UnitEnum|null $navigationGroup = 'আরও';

    protected static ?int $navigationSort = 32;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->label(__('প্রতিযোগিতার নাম'))->required()->maxLength(150),
            TextInput::make('theme')->label(__('বিষয়'))->maxLength(200),
            Select::make('section_id')->label(__('বিভাগ (ঐচ্ছিক)'))->options(fn () => Section::orderBy('name_bn')->pluck('name_bn', 'id')),
            Select::make('status')->label(__('অবস্থা'))->options(\App\Support\Lang::t(Contest::STATUS))->required()->default('draft')
                ->helperText(__('"চলছে" হলে লেখা পাঠানোর ফর্মে প্রতিযোগিতাটি দেখা যাবে।')),
            DateTimePicker::make('starts_at')->label(__('শুরু'))->seconds(false),
            DateTimePicker::make('ends_at')->label(__('শেষ তারিখ'))->seconds(false),
            Textarea::make('rules')->label(__('নিয়ম'))->rows(10)->maxLength(4000)->columnSpanFull()
                ->helperText(__('উপরের "এআই-কে নিয়ম লিখতে দিন" চাপলে খসড়া তৈরি হবে; দেখে বদলে নিন।')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->withCount('entries'))
            ->columns([
                TextColumn::make('title')->label(__('প্রতিযোগিতা'))->weight('bold')->searchable()->description(fn (Contest $record) => $record->theme),
                TextColumn::make('entries_count')->label(__('জমা'))->numeric(),
                TextColumn::make('ends_at')->label(__('শেষ তারিখ'))->date('d/m/Y'),
                TextColumn::make('status')->label(__('অবস্থা'))->badge()->formatStateUsing(fn ($state) => __(Contest::STATUS[$state] ?? $state))
                    ->color(fn ($state) => ['open' => 'success', 'judging' => 'warning', 'done' => 'gray'][$state] ?? 'info'),
            ])
            ->recordActions([EditAction::make(), \App\Filament\Support\Deleting::row()])
            ->toolbarActions([\Filament\Actions\BulkActionGroup::make([\App\Filament\Support\Deleting::bulk(\App\Models\Contest::class)])]);
    }

    public static function getPages(): array
    {
        return ['index' => ListContests::route('/'), 'create' => CreateContest::route('/create'), 'edit' => EditContest::route('/{record}/edit')];
    }
}
