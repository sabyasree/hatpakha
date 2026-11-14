<?php

namespace App\Filament\Pages;

use App\Ai\Illustrator;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Post;
use App\Support\CoverImage;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * D43: AI cover illustrations wait here until the owner approves them, a page at a time.
 * Tick the good ones → approve; redraw or drop the rest. Nothing shows on the site before approval.
 */
class AiImages extends Page
{
    use \App\Filament\Concerns\TranslatedPageLabels;

    protected string $view = 'filament.pages.ai-images';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static string|\UnitEnum|null $navigationGroup = 'প্রশাসন';

    protected static ?string $navigationLabel = 'ছবি অনুমোদন';

    protected static ?string $title = 'এআই-এর আঁকা ছবি';

    protected static ?int $navigationSort = 85;

    /** @var array<int,bool> post id → ticked */
    public array $picked = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(Role::Admin) ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        return ($n = self::waiting()->count()) ? (string) $n : null;
    }

    public static function waiting()
    {
        return Post::whereNotNull('proposed_image_path')->whereIn('status', ['published', 'scheduled', 'pending']);
    }

    /** D195: the look of new pictures (admins). Pictures already made stay as they are; "আবার আঁকুন" redraws one in the new look. */
    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('style')
                ->label(fn () => __('ছবির ধরন').': '.__(\App\Ai\Illustrator::STYLES[\App\Ai\Illustrator::style()][0]))
                ->icon('heroicon-o-paint-brush')->color('gray')
                ->visible(fn () => \App\Filament\Support\Deleting::isAdmin())
                ->modalHeading(__('নতুন ছবি কেমন দেখতে হবে'))
                ->modalDescription(__('এখন থেকে যে ছবি আঁকা হবে তা এই ধরনে হবে। আগের ছবি যেমন আছে থাকবে; কোনো লেখার ছবি নতুন ধরনে চাইলে তার “আবার আঁকুন” টিপুন।'))
                ->schema([
                    \Filament\Forms\Components\Radio::make('style')->label(__('ধরন'))->required()->default(fn () => \App\Ai\Illustrator::style())
                        ->options(collect(\App\Ai\Illustrator::STYLES)->map(fn ($v) => __($v[0]))->all())
                        ->descriptions(collect(\App\Ai\Illustrator::STYLES)->map(fn ($v) => __($v[1]))->all()),
                ])
                ->modalSubmitActionLabel(__('রাখুন'))
                ->action(function (array $data) {
                    \App\Models\Setting::put(\App\Ai\Illustrator::STYLE_SETTING, isset(\App\Ai\Illustrator::STYLES[$data['style']]) ? $data['style'] : 'paint');
                    \App\Models\AuditLog::record('covers.style', null, ['style' => $data['style'], 'by' => auth()->id()]);
                    \Filament\Notifications\Notification::make()->title(__('রাখা হয়েছে'))->success()->send();
                }),
        ];
    }

    public function posts()
    {
        return self::waiting()->with('authors')->orderByDesc('updated_at')->limit(48)->get();
    }

    public function pickAll(): void
    {
        $this->picked = $this->posts()->mapWithKeys(fn ($p) => [$p->id => true])->all();
    }

    public function approvePicked(): void
    {
        $ids = array_keys(array_filter($this->picked));
        $n = 0;
        foreach (self::waiting()->whereIn('id', $ids)->get() as $post) {
            CoverImage::acceptProposed($post);
            $n++;
        }
        AuditLog::record('post.ai_images_approved', null, ['count' => $n]);
        $this->picked = [];
        Notification::make()->title(\App\Support\Bn::num($n).__('টি ছবি সাইটে বসানো হয়েছে'))->success()->send();
    }

    public function drop(int $id): void
    {
        $post = self::waiting()->findOrFail($id);
        CoverImage::discardProposed($post);
        $post->forceFill(['proposed_image_error' => __('বাদ দেওয়া হয়েছে')])->save();   // not redrawn by the batch unless asked
        unset($this->picked[$id]);
    }

    public function redraw(int $id): void
    {
        $post = self::waiting()->findOrFail($id);
        CoverImage::discardProposed($post);
        $why = Illustrator::make($post->fresh());
        $why ? Notification::make()->title(__('আবার আঁকা গেল না'))->body($why)->warning()->send()
             : Notification::make()->title(__('নতুন ছবি তৈরি'))->success()->send();
    }
}
