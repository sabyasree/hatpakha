<?php

namespace App\Filament\Resources\Contests\Pages;

use App\Ai\ContestManager;
use App\Enums\Role;
use App\Filament\Resources\Contests\ContestResource;
use App\Filament\Resources\Posts\PostResource;
use App\Models\Contest;
use App\Models\Post;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditContest extends EditRecord
{
    protected static string $resource = ContestResource::class;

    protected function getHeaderActions(): array
    {
        $isAdmin = fn () => auth()->user()?->hasRole(Role::Admin) ?? false;

        return [
            Action::make('draftRules')->label(__('এআই-কে নিয়ম লিখতে দিন'))->icon('heroicon-o-sparkles')->color('gray')
                ->action(function (Contest $record) {
                    $err = ContestManager::draftRules($record);
                    $this->refreshFormData(['rules']);
                    Notification::make()->title($err ? __('এখন হলো না: ').$err : __('নিয়মের খসড়া তৈরি'))->{$err ? 'warning' : 'success'}()->send();
                }),
            Action::make('shortlist')->label(__('এআই বাছাই তালিকা'))->icon('heroicon-o-list-bullet')->color('gray')
                ->visible(fn (Contest $record) => $record->entries()->exists())
                ->action(function (Contest $record) {
                    $err = ContestManager::shortlist($record);
                    Notification::make()->title($err ? __('এখন হলো না: ').$err : __('বাছাই তালিকা তৈরি, নিচে দেখুন'))->{$err ? 'warning' : 'success'}()->send();
                }),
            // The owner, not the AI, decides the winners (D53).
            Action::make('winners')->label(__('বিজয়ী ঠিক করুন'))->icon('heroicon-o-trophy')->color('success')
                ->visible(fn (Contest $record) => $isAdmin() && $record->entries()->exists() && $record->status !== 'done')
                ->schema(fn (Contest $record) => [
                    Repeater::make('winners')->label(__('বিজয়ী'))->minItems(1)->maxItems(6)->defaultItems(1)->reorderable(false)
                        ->schema([
                            Select::make('post_id')->label(__('লেখা'))->required()
                                ->options(fn () => $record->entries()->whereNotIn('status', ['draft'])->pluck('title', 'id')),
                            Select::make('place')->label(__('স্থান'))->required()->options([1 => __('প্রথম'), 2 => __('দ্বিতীয়'), 3 => __('তৃতীয়'), 4 => __('বিশেষ উল্লেখ')]),
                        ])->columns(2),
                ])
                ->modalDescription(__('ফলাফলের লেখা খসড়া হিসেবে তৈরি হবে (আপনি দেখে প্রকাশ করবেন), আর বিজয়ীদের শংসাপত্র তৈরি হবে।'))
                ->action(function (Contest $record, array $data) {
                    $post = ContestManager::announce($record, auth()->user(), array_map(fn ($w) => ['post_id' => (int) $w['post_id'], 'place' => (int) $w['place']], $data['winners']));
                    Notification::make()->title(__('ফলাফলের খসড়া তৈরি'))->success()
                        ->actions([Action::make('open')->label(__('খুলুন'))->url(PostResource::getUrl('edit', ['record' => $post]))])->send();
                }),
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [];
    }

    public function getFooter(): ?\Illuminate\Contracts\View\View
    {
        $c = $this->record;
        $short = collect((array) $c->shortlist)->map(fn ($s) => ['post' => Post::find($s['post_id']), 'why' => $s['why']])->filter(fn ($s) => $s['post']);

        return view('filament.contest-entries', ['contest' => $c, 'shortlist' => $short, 'entries' => $c->entries()->with('authors')->whereNotIn('status', ['draft'])->latest('submitted_at')->get()]);
    }
}
