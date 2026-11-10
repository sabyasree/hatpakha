<?php

namespace App\Ai;

use App\Filament\Pages\AiImages;
use App\Models\AiUsage;
use App\Models\AuditLog;
use App\Models\Comment;
use App\Models\Issue;
use App\Models\Post;

/** What the AI editor did in the last 24 hours and what waits for the owner (D44). */
final class Brief
{
    /** @return array<string,int|string> */
    public static function today(): array
    {
        $since = now()->subDay();
        $usage = AiUsage::where('created_at', '>=', $since);

        return [
            'at' => now()->toIso8601String(),
            'prepared' => (clone $usage)->whereIn('purpose', ['review', 'editor'])->count(),
            'pictures' => (clone $usage)->where('purpose', 'image')->count(),
            'comments_screened' => (clone $usage)->where('purpose', 'comment_screen')->count(),
            'cost' => round((float) (clone $usage)->sum('cost_usd'), 2),
            'waiting_pieces' => Post::where('status', 'pending')->whereNotNull('submitted_by')->count(),
            'waiting_comments' => Comment::where('status', 'pending')->count(),
            'waiting_pictures' => AiImages::waiting()->count(),
            'issue_proposed' => Issue::where('status', 'proposed')->count(),
            'editorial_drafts' => Post::whereNotNull('ai_drafted_at')->where('status', 'draft')->count(),
            'published_yesterday' => Post::published()->where('published_at', '>=', $since)->count(),
            'free_slots_week' => collect(range(0, 6))->map(fn ($i) => now('Asia/Kolkata')->startOfDay()->addDays($i))
                ->filter(fn ($d) => in_array($d->dayOfWeek, \App\Support\Schedule::DAYS, true))
                ->filter(fn ($d) => ! Post::whereIn('status', ['scheduled', 'published'])->whereBetween('published_at', [$d->copy()->utc(), $d->copy()->endOfDay()->utc()])->exists())
                ->count(),
            'owner_actions' => AuditLog::where('created_at', '>=', $since)->whereNotNull('user_id')->count(),
        ];
    }
}
