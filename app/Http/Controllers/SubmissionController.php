<?php

namespace App\Http\Controllers;

use App\Console\Commands\ImportWordPress;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Author;
use App\Models\Post;
use App\Models\Section;
use App\Seo\Seo;
use App\Support\Embeds;
use App\Support\Slug;
use App\Support\Url;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Support\HtmlSanitizer;

/**
 * Phase 3b: the ONLY way to submit writing (owner decision D29). One screen, plain text (pasting
 * from Word/Docs/WhatsApp arrives clean), autosave, preview, originality declaration, 3 per day.
 */
class SubmissionController extends Controller
{
    /** Sections a writer may choose (editorial is written by the desk). */
    public static function sections()
    {
        return Section::where('is_active', true)->where('slug', '!=', 'editorial')->orderBy('sort')->get();
    }

    public function create(Request $request)
    {
        return $this->form($request, new Post(['status' => 'draft']));
    }

    public function edit(Request $request, Post $post)
    {
        $this->authorizeOwn($request, $post);

        return $this->form($request, $post);
    }

    private function form(Request $request, Post $post)
    {
        // D96: "এই বিভাগে লেখা পাঠান" opens a new piece with that section already chosen
        if (! $post->exists && ($pick = self::sections()->firstWhere('slug', (string) $request->query('section')))) {
            $post->section_id = $pick->id;
        }
        // Owner (2026-11-11): "Share → হাতপাখা" from Notes or WhatsApp (Android share target) opens a new piece with the
        // shared text already in it. Plain text only (shown escaped in the box, sanitised on save like any submission).
        $shared = ! $post->exists && $request->query('text') !== null
            ? trim(mb_substr(str_replace("\r\n", "\n", (string) $request->query('text')), 0, 20000)) : '';
        if ($shared !== '' && ! $request->old('title') && filled($request->query('title'))) {
            $post->title = mb_substr(trim((string) $request->query('title')), 0, 200);
        }

        return view('submit', [
            'seo' => new Seo(title: 'লেখা পাঠান', noindex: true),
            'post' => $post,
            'text' => $post->exists ? self::toText((string) $post->body_html) : $shared,
            'shared' => $shared !== '',
            'html' => $post->exists ? self::fromEditor((string) $post->body_html) : '',
            'sections' => self::sections(),
            'editable' => in_array($post->status, ['draft', 'changes'], true),
            'needsVerification' => AccountController::verificationRequired() && ! $request->user()->hasVerifiedEmail(),
        ]);
    }

    /** New piece: save as draft or submit. */
    public function store(Request $request)
    {
        $post = new Post(['status' => 'draft']);

        return $this->save($request, $post);
    }

    public function update(Request $request, Post $post)
    {
        $this->authorizeOwn($request, $post);
        abort_unless(in_array($post->status, ['draft', 'changes'], true), 403, 'জমা দেওয়া লেখা আর বদলানো যায় না।');

        return $this->save($request, $post);
    }

    /** The writer's answer to the editor's word changes (D42): accept → published as planned; object → back to the editor. */
    public function confirm(Request $request, Post $post)
    {
        $this->authorizeOwn($request, $post);
        abort_unless($post->status === 'confirm', 403);
        if ($request->input('answer') === 'accept') {
            \App\Ai\EditorPackage::writerAccepts($post);

            return redirect(Url::to('/account'))->with('status', 'ধন্যবাদ! "'.$post->title.'" প্রকাশের জন্য তৈরি।');
        }
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:1000']], ['reason.required' => 'কোন বদলে আপত্তি, একটু লিখে দিন।']);
        \App\Ai\EditorPackage::writerObjects($post, $data['reason']);

        return redirect(Url::to('/account'))->with('status', 'আপনার কথা সম্পাদককে জানানো হয়েছে।');
    }

    /** Autosave (JSON). Drafts only; never submits. */
    public function autosave(Request $request, ?Post $post = null)
    {
        if ($post) {
            $this->authorizeOwn($request, $post);
            abort_unless(in_array($post->status, ['draft', 'changes'], true), 403);
        }
        $data = $request->validate(['title' => ['nullable', 'string', 'max:200'], 'body' => ['nullable', 'string', 'max:200000'], 'section_id' => ['nullable', 'integer']]);
        if (blank($data['title'] ?? null) && blank($data['body'] ?? null)) {
            return response()->json(['saved' => false]);
        }
        // Owner (2026-11-12): a title alone made an empty draft. A new draft starts only once some text is written;
        // until then nothing is kept (the title is easy to type again).
        if (! $post && mb_strlen(trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($data['body'] ?? ''))))) < 10) {
            return response()->json(['saved' => false]);
        }
        $post ??= new Post(['status' => 'draft']);
        $this->fill($request, $post, $data['title'] ?? '', $data['body'] ?? '', $data['section_id'] ?? null);

        return response()->json(['saved' => true, 'id' => $post->id, 'url' => Url::to('/submit/'.$post->id), 'at' => \App\Support\Bn::num(now()->format('H:i'))]);
    }

    /** Preview exactly as readers will see it (same sanitiser + formatter as the site). */
    public function preview(Request $request)
    {
        $data = $request->validate(['title' => ['nullable', 'string', 'max:200'], 'body' => ['nullable', 'string', 'max:200000'], 'section_id' => ['nullable', 'integer']]);
        $post = new Post(['title' => $data['title'] ?? '', 'format' => $this->formatFor($data['section_id'] ?? null)]);
        $post->body_html = \App\Support\BodyFormatter::format(\App\Support\HtmlSanitizer::clean(self::bodyHtml($request, $data['body'] ?? '')), $post->format, null);

        return response()->json(['poem' => $post->format === 'poem', 'html' => Embeds::render($post->body_html)]);
    }

    private function save(Request $request, Post $post)
    {
        $submitting = $request->input('action') === 'submit';
        $data = $request->validate([
            'title' => ['required', 'string', 'min:1', 'max:200'],
            'body' => ['required', 'string', 'min:30', 'max:200000'],
            'section_id' => ['required', 'integer', 'in:'.self::sections()->pluck('id')->implode(',')],
            'original' => [$submitting ? 'accepted' : 'nullable'],
            // Checked again for real (sniffed + re-encoded) in CoverImage; this is the quick first gate.
            'cover' => ['nullable', 'file', 'max:8192', 'mimetypes:image/jpeg,image/png,image/webp'],
            'remove_cover' => ['nullable', 'boolean'],
            'contest_id' => ['nullable', 'integer'],
            'writer_note' => ['nullable', 'string', 'max:2000'],   // D171: a note to the editor when sending it back
        ], [
            'writer_note.max' => 'সম্পাদককে লেখা কথা ২০০০ অক্ষরের মধ্যে রাখুন।',
            'cover.max' => 'ছবিটি ৮ MB-এর মধ্যে হতে হবে।',
            'cover.mimetypes' => 'শুধু JPG, PNG বা WebP ছবি দেওয়া যায়।',
            'title.required' => 'শিরোনাম দিন।',
            'body.required' => 'লেখাটি দিন।',
            'body.min' => 'লেখাটি খুব ছোট।',
            'section_id.required' => 'বিভাগ বেছে নিন।',
            'original.accepted' => 'জমা দেওয়ার আগে ঘোষণায় টিক দিন।',
        ]);

        if ($submitting) {
            if (AccountController::verificationRequired() && ! $request->user()->hasVerifiedEmail()) {
                throw ValidationException::withMessages(['title' => 'লেখা জমা দেওয়ার আগে ইমেল যাচাই করুন (আমার লেখা পাতায় লিংক আছে)।']);
            }
            $key = 'submit:'.$request->user()->id;
            if (RateLimiter::tooManyAttempts($key, 3)) {
                throw ValidationException::withMessages(['title' => 'আজ ৩টি লেখা জমা দেওয়া হয়ে গেছে। আগামীকাল আবার পাঠান। লেখাটি খসড়া হিসেবে রাখা আছে।']);
            }
        }

        if (mb_strlen(trim(html_entity_decode(strip_tags(self::bodyHtml($request, $data['body']))))) < 30) {
            throw ValidationException::withMessages(['body' => 'লেখাটি খুব ছোট।']);
        }
        $this->fill($request, $post, $data['title'], $data['body'], (int) $data['section_id']);
        // Contest entry (D53): only an open contest; one entry per writer per contest; never changed after judging starts.
        $contestId = $data['contest_id'] ?? null;
        if ($contestId && $contestId !== $post->contest_id) {
            $open = \App\Models\Contest::openNow()->whereKey($contestId)->exists();
            $already = Post::where('contest_id', $contestId)->where('submitted_by', $request->user()->id)->whereKeyNot($post->id)->exists();
            if (! $open || $already) {
                throw ValidationException::withMessages(['contest_id' => $already ? 'এই প্রতিযোগিতায় আপনার একটি লেখা আগেই জমা আছে।' : 'এই প্রতিযোগিতা এখন চলছে না।']);
            }
        }
        if (! $post->contest_id || \App\Models\Contest::openNow()->whereKey($post->contest_id)->exists()) {
            $post->forceFill(['contest_id' => $contestId ?: null])->save();
        }
        if ($request->hasFile('cover')) {
            \App\Support\CoverImage::attach($post, $request->file('cover'));
        } elseif ($request->boolean('remove_cover')) {
            \App\Support\CoverImage::remove($post);
        }

        if (! $submitting) {
            return redirect(Url::to('/submit/'.$post->id))->with('status', 'খসড়া রাখা হয়েছে।');
        }

        RateLimiter::hit('submit:'.$request->user()->id, 86400);
        $wasChanges = $post->status === 'changes';
        $post->forceFill(['status' => 'pending', 'submitted_at' => now(), 'originality_confirmed_at' => now()])->save();
        // D171: a copy of what was sent, with the writer's note on a second round, so the desk sees what changed
        \App\Models\PostVersion::snap($post, $wasChanges ? 'resubmitted' : 'submitted', $wasChanges ? ($data['writer_note'] ?? null) : null, $request->user()->id);
        if ($request->user()->role === Role::Reader) {
            $request->user()->forceFill(['role' => Role::Writer])->save();
        }
        AuditLog::record($wasChanges ? 'submission.resubmitted' : 'submission.received', $post);
        rescue(fn () => $request->user()->notify(new \App\Notifications\FormReceived('submission', $post->title)));   // D122
        // AI report for the editor (advice only), written after the writer's page has been sent.
        \App\Jobs\ReviewSubmission::dispatch($post->id)->afterResponse();

        return redirect(Url::to('/account'))->with('status', '"'.$post->title.'" জমা পড়েছে। সম্পাদকমণ্ডলী পড়ে দেখে জানাবেন। ধন্যবাদ!')->with('submitted', $post->title);
    }

    private function fill(Request $request, Post $post, string $title, string $body, ?int $sectionId): void
    {
        $user = $request->user();
        // Always look the profile up fresh (a cached "no author yet" would create a duplicate).
        $author = Author::where('user_id', $user->id)->first() ?? Author::create([
            'user_id' => $user->id,
            'display_name' => $user->name,
            'slug' => $this->authorSlug($user->name, $user->id),
        ]);
        $sample = $title.' '.mb_substr(strip_tags($body), 0, 2000);
        $post->forceFill([
            'title' => trim(strip_tags($title)) ?: 'শিরোনামহীন',
            'body_html' => self::bodyHtml($request, $body),
            'section_id' => $sectionId,
            'format' => $this->formatFor($sectionId),
            'language' => preg_match('/[\x{0985}-\x{09B9}]/u', $sample) ? 'bn' : (preg_match('/[\x{0905}-\x{0939}]/u', $sample) ? 'hi' : 'en'),
            'submitted_by' => $user->id,
            // Not public until an editor publishes it; the real reader-facing slug is set then (3c).
            'slug' => $post->slug ?: 'draft-'.Str::lower(Str::random(12)),
            'status' => $post->status ?: 'draft',
        ])->save();
        $post->authors()->syncWithoutDetaching([$author->id => ['position' => 0]]);
    }

    private function formatFor(?int $sectionId): string
    {
        $slug = $sectionId ? Section::whereKey($sectionId)->value('slug') : null;

        return in_array($slug, ['poetry', 'sher-o-shayari'], true) ? 'poem' : 'prose';
    }

    private function authorSlug(string $name, int $userId): string
    {
        $base = Slug::normalize(Str::slug($name) ?: preg_replace('/\s+/u', '-', trim($name))) ?: 'writer';
        $slug = mb_substr($base, 0, 60);

        return Author::where('slug', $slug)->exists() ? "{$slug}-{$userId}" : $slug;
    }

    private function authorizeOwn(Request $request, Post $post): void
    {
        abort_unless($post->authors()->where('user_id', $request->user()->id)->exists() || $post->submitted_by === $request->user()->id, 404);
    }

    /** Plain text from the form → HTML paragraphs (blank line = stanza/paragraph, newline = line break). */
    /** The editor sends HTML (body_format=html); without JavaScript the box is plain text. */
    public static function bodyHtml(Request $request, string $body): string
    {
        return $request->input('body_format') === 'html' ? self::fromEditor($body) : self::toHtml($body);
    }

    /** Formatting a writer may use (D40). Everything else is unwrapped to its text; no attributes survive. */
    public const WRITER_TAGS = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'del', 'sup', 'sub', 'h3', 'blockquote', 'ul', 'ol', 'li', 'hr'];

    public static function fromEditor(string $html): string
    {
        $html = HtmlSanitizer::clean($html);   // also turns editor alignment into a ta-* class
        $html = preg_replace('~<h[24]\b~i', '<h3', $html);
        $html = preg_replace('~</h[24]>~i', '</h3>', $html);
        $html = strip_tags($html, self::WRITER_TAGS);
        // Drop every attribute, except the alignment class on paragraphs and sub-headings.
        $html = preg_replace_callback('~<('.implode('|', self::WRITER_TAGS).')\b([^>]*)>~i', function (array $m) {
            $tag = strtolower($m[1]);
            $class = in_array($tag, ['p', 'h3'], true) && preg_match('~class="(ta-(?:left|center|right))"~', $m[2], $c) ? ' class="'.$c[1].'"' : '';

            return '<'.$tag.$class.'>';
        }, $html);
        $html = preg_replace('~<p>(\s|&nbsp;|<br>)*</p>~i', '', $html);                                 // empty paragraphs

        return trim($html);
    }

    public static function toHtml(string $text): string
    {
        $text = e(str_replace(["\r\n", "\r"], "\n", trim($text)));

        return ImportWordPress::autop($text);
    }

    /** Stored HTML → the plain text shown in the form again. */
    public static function toText(string $html): string
    {
        $t = preg_replace(['~<br\s*/?>~i', '~</p>\s*~i'], ["\n", "\n\n"], $html);

        return trim(html_entity_decode(strip_tags($t), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
