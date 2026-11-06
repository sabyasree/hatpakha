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
