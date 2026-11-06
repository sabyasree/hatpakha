<?php

namespace App\Ai;

use App\Models\AuditLog;
use App\Models\Author;
use App\Models\EditorTask;
use App\Models\Post;
use Illuminate\Support\Facades\DB;

/**
 * Honouring writers (D66). Monthly, the AI editor proposes a "মাসের লেখক" from the last two months
 * (quality in the editor's reports, readers' likes and reads, kindness to the craft) and drafts the
 * words of praise. The owner edits and approves; only then does anything appear or get sent.
 */
final class Honours
{
    public const LABELS = ['month' => 'মাসের লেখক', 'pick' => 'সম্পাদকের পছন্দের লেখক', 'honour' => 'হাতপাখা সম্মান', 'rising' => 'নতুন প্রতিভা'];

    public static function proposeWriterOfMonth(bool $force = false): ?EditorTask
    {
        if (! $force && EditorTask::where('type', 'honour')->where('created_at', '>=', now()->startOfMonth())->exists()) {
            return null;
        }
        $since = now()->subDays(60);
        $posts = Post::published()->with('authors', 'latestAiReview')->where('published_at', '>=', $since)->get();
        $views = DB::table('post_views')->whereIn('post_id', $posts->pluck('id'))->groupBy('post_id')->selectRaw('post_id, SUM(views) v')->pluck('v', 'post_id');
        $byAuthor = [];
        foreach ($posts as $p) {
            foreach ($p->authors->where('is_desk', false) as $a) {
                $byAuthor[$a->id]['author'] = $a;
                $byAuthor[$a->id]['pieces'][] = '"'.$p->title.'" (AI score '.($p->latestAiReview?->score ?? '?').', likes '.$p->likes_count.', reads '.($views[$p->id] ?? 0).')';
            }
        }
        $recent = Author::whereNotNull('honoured_at')->where('honoured_at', '>=', now()->subMonths(6))->pluck('id')->all();
        $list = collect($byAuthor)->reject(fn ($x, $id) => in_array($id, $recent, true))
            ->map(fn ($x, $id) => 'id '.$id.' | '.$x['author']->publicName().' | '.implode('; ', $x['pieces']))->take(40)->implode("\n");
        if ($list === '') {
            return null;
        }
        [$d] = Claude::json('editor',
            'You are the editor of Hatpakha (হাতপাখা). Choose this month\'s writer to honour ("মাসের লেখক") from the writers of the last two months (DATA): '
            .'quality first (the AI scores), then readers (likes, reads); prefer someone whose work shows craft and growth. Then write the editor\'s words of praise '
            .'in warm, specific Bengali (2-3 sentences) about their writing as shown by the titles given; never invent facts about the person or quote lines you were not given. '
            .'Also give one sentence to the owner on why.',
            "<writers>\n".e($list)."\n</writers>",
            ['type' => 'object', 'additionalProperties' => false, 'required' => ['author_id', 'praise', 'why'], 'properties' => [
                'author_id' => ['type' => 'integer'], 'praise' => ['type' => 'string'], 'why' => ['type' => 'string']]],
            null, 1500);
        $author = $d ? Author::find((int) $d['author_id']) : null;
        if (! $author || ! isset($byAuthor[$author->id])) {
            return null;
        }

        return EditorTask::propose('honour', __('মাসের লেখকের প্রস্তাব: ').$author->publicName(), [
            'label' => 'month', 'praise' => mb_substr(trim(strip_tags((string) $d['praise'])), 0, 800), 'why' => mb_substr(trim(strip_tags((string) $d['why'])), 0, 400),
        ], $author);
    }

    /** The owner's click: honour shows on the writer's pages and the home page; optionally one note to the writer. */
    public static function give(Author $author, string $labelKey, string $praise, bool $tellWriter): void
    {
        $label = self::LABELS[$labelKey] ?? self::LABELS['honour'];
        if ($labelKey === 'month') {
            $label .= ' · '.\App\Support\Bn::num(now('Asia/Kolkata')->locale('bn')->isoFormat('MMMM YYYY'));
        }
        $author->forceFill(['honour_label' => mb_substr($label, 0, 40), 'honour_note' => trim(strip_tags($praise)) ?: null, 'honoured_at' => now()])->save();
        AuditLog::record('author.honoured', $author, ['label' => $label]);
        // D73: a সম্মাননা সনদ goes with every honour (one per honour and writer).
        $cert = $author->is_desk ? null : \App\Models\Certificate::firstOrCreate(['author_id' => $author->id, 'kind' => 'honour', 'title_snapshot' => mb_substr($label, 0, 400)],
            ['author_name_snapshot' => $author->publicName()]);
        if ($tellWriter && $author->user && \App\Support\NewsletterSender::realMail()) {
            $author->user->notify(new \App\Notifications\WriterNote('হাতপাখা: আপনাকে অভিনন্দন!',
                'নমস্কার '.$author->publicName().', হাতপাখা আপনাকে "'.$label.'" হিসেবে সম্মান জানাচ্ছে।'."\n\n".$praise."\n\nআপনার লেখক-পাতায় সম্মানটি দেখা যাবে, আর নিচের লিংকে আপনার সম্মাননা সনদ (ছবি বা PDF হিসেবে নামাতে পারবেন)। লিখতে থাকুন!",
                $cert?->verifyUrl() ?? $author->url(), $cert ? 'সনদ দেখুন' : 'আমার লেখা'));
        }
    }
}
