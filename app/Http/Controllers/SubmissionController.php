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
        abort_unless(in_array($post->status, ['draft', 'changes'], true), 403, 'লেখাটি সম্পাদকের কাছে আছে। বদলাতে চাইলে আগে "ফিরিয়ে এনে বদলান" টিপুন।');

        return $this->save($request, $post);
    }

    /** D205: until a piece is published the writer can take it back, change it and send it again. */
    public const TAKE_BACK = ['pending', 'changes', 'confirm', 'scheduled'];

    public function withdraw(Request $request, Post $post)
    {
        $this->authorizeOwn($request, $post);
        abort_unless(in_array($post->status, self::TAKE_BACK, true) && ! $post->isLive(), 403, 'প্রকাশিত লেখা এখান থেকে বদলানো যায় না।');   // a piece whose day has come is out, whatever its status word says
        $was = $post->status;
        // What the desk had seen is kept in the history; the editor's planned date and waiting word changes no longer apply.
        \App\Models\PostVersion::snap($post, 'withdrawn', null, $request->user()->id);
        $post->forceFill(['status' => 'draft', 'published_at' => null, 'pending_edits' => null])->save();
        \App\Support\Handover::reset($post);
        \App\Models\EditorTask::where('status', 'open')->where('subject_type', $post->getMorphClass())->where('subject_id', $post->id)->get()->each->close('dismissed');
        \App\Models\PieceReview::where('post_id', $post->id)->where('status', 'assigned')->update(['status' => 'withdrawn']);
        AuditLog::record('submission.withdrawn', $post, ['was' => $was]);

        return redirect(Url::to('/submit/'.$post->id))->with('status', 'লেখাটি ফিরিয়ে আনা হয়েছে। এখন এটি শুধু আপনার কাছে, খসড়া হিসেবে। যা বদলানোর বদলে নিচের "জমা দিন" টিপলে তবেই আবার সম্পাদকের কাছে যাবে।');
    }

    /** D205: a draft the writer no longer wants. Kept out of sight, not destroyed (the desk can bring it back). */
    public function destroy(Request $request, Post $post)
    {
        $this->authorizeOwn($request, $post);
        abort_unless($post->status === 'draft', 403);
        AuditLog::record('submission.draft_deleted', $post);
        $post->delete();

        return redirect(Url::to('/account'))->with('status', '"'.$post->title.'" খসড়াটি মুছে ফেলা হয়েছে।');
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

    /**
     * D193 (owner, 2026-11-14: "if anyone wants to write a serial, how will they start?"): the piece is an episode of the
     * writer's own serial. "new" proposes a serial (name, the whole story in a few lines, how many episodes) with this
     * piece as its first episode; the editor decides, and publishing that episode starts the serial. Afterwards the
     * writer picks the serial and each new piece becomes its next episode. Only one's own serials, never the desk's.
     */
    private function serial(Request $request, Post $post, array $data): void
    {
        $choice = (string) ($data['serial'] ?? '');
        $user = $request->user();
        $mine = fn () => \App\Models\Serial::where('user_id', $user->id)->whereIn('status', ['proposed', 'active', 'paused']);
        if (in_array($post->status, ['published', 'scheduled'], true)) {
            return;
        }
        if ($choice === 'new') {
            if ($mine()->where('status', 'proposed')->where('id', '!=', (int) $post->serial_id)->exists()) {
                throw ValidationException::withMessages(['serial' => 'আপনার একটি ধারাবাহিকের প্রস্তাব সম্পাদকের কাছে আছে। উত্তর এলে আর একটি পাঠাতে পারবেন।']);
            }
            $fields = ['title' => strip_tags((string) $data['serial_title']), 'plan' => strip_tags((string) $data['serial_plan']), 'planned_count' => (int) $data['serial_count']];
            $own = $post->serial_id ? $mine()->where('status', 'proposed')->find($post->serial_id) : null;
            if ($own) {
                $own->forceFill($fields)->save();   // still a draft of the same proposal
            } else {
                $own = \App\Models\Serial::create($fields + ['user_id' => $user->id, 'status' => 'proposed', 'day' => 6]);
                $post->forceFill(['serial_id' => $own->id, 'episode_no' => 1])->save();
            }

            return;
        }
        if (ctype_digit($choice) && ($serial = $mine()->find((int) $choice))) {
            if ((int) $post->serial_id !== $serial->id) {
                $post->forceFill(['serial_id' => $serial->id, 'episode_no' => $serial->nextEpisodeNo()])->save();
            }

            return;
        }
        // "No": the piece leaves the writer's own serial (the serial itself stays; an unused proposal is removed).
        if ($post->serial_id && ($old = $mine()->find($post->serial_id))) {
            $post->forceFill(['serial_id' => null, 'episode_no' => null])->save();
            if ($old->status === 'proposed' && ! $old->episodes()->exists()) {
                $old->delete();
            }
        }
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
            'for_issue' => ['nullable', 'boolean'],   // owner, 2026-11-14: the writer says if it is for the ফিরে আসা সংখ্যা
            'writer_note' => ['nullable', 'string', 'max:2000'],   // D171: a note to the editor when sending it back
            // D193: this piece is an episode of the writer's own serial ("new" proposes one)
            'serial' => ['nullable', 'string', 'max:12'],
            'serial_title' => ['required_if:serial,new', 'nullable', 'string', 'min:2', 'max:150'],
            'serial_plan' => ['required_if:serial,new', 'nullable', 'string', 'min:80', 'max:3000'],
            'serial_count' => ['required_if:serial,new', 'nullable', 'integer', 'min:2', 'max:60'],
        ], [
            'serial_title.required_if' => '১ নম্বরে পুরো ধারাবাহিকের নাম লিখুন।',
            'serial_plan.required_if' => '২ নম্বরে পুরো গল্পটা কী নিয়ে, কোন দিকে যাবে, তিন-চার লাইনে লিখুন।',
            'serial_plan.min' => 'গল্পের পরিকল্পনা আর একটু খুলে লিখুন (অন্তত দু-তিন লাইন)।',
            'serial_count.required_if' => 'আনুমানিক কত পর্ব হবে, লিখুন।',
            'serial_count.min' => 'ধারাবাহিকে অন্তত দুটি পর্ব লাগে।',
            'serial_count.max' => 'পর্বের সংখ্যা ৬০-এর মধ্যে রাখুন।',
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
        // D206: the ধারাবাহিক section sent as "a single piece" is the commonest slip; ask instead of guessing.
        if ($submitting && blank($data['serial'] ?? null) && ! $post->serial_id && self::sections()->firstWhere('id', (int) $data['section_id'])?->slug === 'dharabahik') {
            $own = \App\Models\Serial::where('user_id', $request->user()->id)->whereIn('status', ['proposed', 'active', 'paused'])->pluck('status');
            throw ValidationException::withMessages(['serial' => match (true) {
                $own->contains(fn ($st) => $st !== 'proposed') => 'আপনি “ধারাবাহিক” বিভাগ বেছেছেন। “এটি কি ধারাবাহিকের পর্ব?” প্রশ্নে বেছে নিন: আপনার কোন ধারাবাহিকের পরের পর্ব, না নতুন ধারাবাহিকের প্রথম পর্ব।',
                $own->isNotEmpty() => 'আপনার ধারাবাহিকের প্রথম পর্ব এখনও সম্পাদকের কাছে আছে। সেটি প্রকাশ হলে পরের পর্ব পাঠাতে পারবেন। ততদিন “খসড়া রাখুন” টিপে এই লেখাটি রেখে দিন।',
                default => 'আপনি “ধারাবাহিক” বিভাগ বেছেছেন। “এটি কি ধারাবাহিকের পর্ব?” প্রশ্নে “হ্যাঁ, নতুন ধারাবাহিক শুরু করছি” বেছে তিনটি কথা জানান। একটাই লেখা হলে বিভাগ বদলে গল্প, কবিতা বা অন্য কিছু বেছে নিন।',
            }]);
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
        if (\App\Support\WriterInvites::open()) {   // the writer's own word; the desk can still change it
            $issueId = \App\Support\WriterInvites::issue()->id;
            if ($request->boolean('for_issue') && ! $post->issue_id) {
                $post->forceFill(['issue_id' => $issueId])->saveQuietly();
            } elseif (! $request->boolean('for_issue') && (int) $post->issue_id === $issueId) {
                $post->forceFill(['issue_id' => null])->saveQuietly();
            }
        }
        $this->serial($request, $post, $data);
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
        \App\Support\Handover::reset($post);
        // D171: a copy of what was sent, with the writer's note on a second round, so the desk sees what changed
        \App\Models\PostVersion::snap($post, $wasChanges ? 'resubmitted' : 'submitted', $wasChanges ? ($data['writer_note'] ?? null) : null, $request->user()->id);
        if ($request->user()->role === Role::Reader) {
            $request->user()->forceFill(['role' => Role::Writer])->save();
        }
        AuditLog::record($wasChanges ? 'submission.resubmitted' : 'submission.received', $post);
        rescue(fn () => $request->user()->notify(new \App\Notifications\FormReceived('submission', $post->title)));   // D122
        // AI report for the editor (advice only), written after the writer's page has been sent.
        \App\Jobs\ReviewSubmission::dispatch($post->id)->afterResponse();

        $serial = $post->fresh()->serial;
        $what = $serial && $serial->user_id === $request->user()->id
            ? ($serial->status === 'proposed' ? 'নতুন ধারাবাহিক “'.$serial->title.'”-এর প্রথম পর্ব ' : 'ধারাবাহিক “'.$serial->title.'”-এর পর্ব '.\App\Support\Bn::num((string) $post->fresh()->episode_no).' ') : '';

        return redirect(Url::to('/account'))->with('status', $what.'"'.$post->title.'" জমা পড়েছে। সম্পাদকমণ্ডলী পড়ে দেখে সাধারণত '.\App\Support\Bn::num((string) \App\Support\Review::REPLY_DAYS).' দিনের মধ্যে ই-মেলে জানাবেন। কিছু বাদ পড়লে বা বদলাতে চাইলে নতুন করে পাঠাবেন না: "আমার লেখা"-য় এই লেখার পাশে "বদলান" টিপুন। ধন্যবাদ!')->with('submitted', $post->title);
    }

    /** D237: a Word file is read into the writing box; the writer checks it and sends as usual. Nothing is kept. */
    public function word(Request $request)
    {
        $request->validate(['word' => ['required', 'file', 'max:5120']], ['word.*' => 'ফাইলটি ৫ MB-এর মধ্যে হতে হবে।']);
        $read = \App\Support\WordFile::read($request->file('word'));

        return response()->json(['html' => \App\Support\HtmlSanitizer::clean($read['html']), 'paragraphs' => $read['paragraphs']]);
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
