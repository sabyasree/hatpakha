<?php

namespace App\Filament\Pages;

use App\Models\AuditLog;
use App\Models\Issue;
use App\Models\Post;
use App\Models\SocialPost;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * The marketing desk (D51): the marketing expert's ready-to-post text for Facebook, WhatsApp and
 * Instagram, with the graphic designer's cards to download as pictures. The owner copies, posts by
 * hand, and ticks "posted". Nothing is posted automatically.
 */
class Marketing extends Page
{
    use \App\Filament\Concerns\TranslatedPageLabels;

    protected string $view = 'filament.pages.marketing';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?string $navigationLabel = 'মার্কেটিং';

    protected static ?string $title = 'মার্কেটিং';

    protected static ?int $navigationSort = 3;

    public string $filter = 'ready';

    public static function getNavigationBadge(): ?string
    {
        return ($n = SocialPost::where('status', 'ready')->count()) ? (string) $n : null;
    }

    public function posted(int $id): void
    {
        $s = SocialPost::findOrFail($id);
        $s->forceFill(['status' => 'posted', 'posted_at' => now(), 'posted_by' => auth()->id()])->save();
        AuditLog::record('marketing.posted', null, ['social_post' => $s->id]);
        Notification::make()->title(__('পোস্ট করা হয়েছে বলে রাখা হলো'))->success()->send();
    }

    public function dismiss(int $id): void
    {
        SocialPost::findOrFail($id)->forceFill(['status' => 'dismissed'])->save();
    }

    public function data(): array
    {
        $items = SocialPost::with('subject')->where('status', $this->filter === 'done' ? '!=' : '=', 'ready')->latest()->limit(40)->get();

        return ['items' => $items->map(fn (SocialPost $s) => ['s' => $s, 'card' => self::card($s)])];
    }

    /** What the designer's card shows: only real data (title, writer, exact quote, picture). */
    public static function card(SocialPost $s): ?array
    {
        $subject = $s->subject;
        if ($subject instanceof Post) {
            return [
                'title' => $subject->title,
                'by' => $subject->authors->first()?->publicName() ?? '',
                'section' => $subject->section?->name_bn ?? '',
                'quote' => $s->texts['quote'] ?? '',
                'image' => $subject->featured_image_path ? asset(ltrim($subject->featured_image_path, '/')) : null,
            ];
        }
        if ($subject instanceof Issue) {
            return ['title' => $subject->title, 'by' => 'সংখ্যা', 'section' => '', 'quote' => '', 'image' => $subject->cover_path ? asset(ltrim($subject->cover_path, '/')) : null];
        }

        return null;
    }
}
