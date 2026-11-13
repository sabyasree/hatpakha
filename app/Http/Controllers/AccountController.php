<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Auth\AuthController;
use App\Models\Post;
use App\Support\Url;
use Illuminate\Http\Request;

/** "আমার লেখা": the writer's own drafts, submissions and published pieces (Phase 3a/3b). */
class AccountController extends Controller
{
    public const STATUS = [
        'draft' => ['খসড়া', 'এখনও জমা দেওয়া হয়নি'],
        'pending' => ['জমা পড়েছে', 'সম্পাদকমণ্ডলী পড়ছেন'],
        'changes' => ['বদল চাওয়া হয়েছে', 'সম্পাদকের মন্তব্য দেখে ঠিক করে আবার জমা দিন'],
        'confirm' => ['মনোনীত, আপনার সম্মতি চাই', 'সম্পাদক কিছু শব্দ বদলাতে চান: দেখে সম্মতি দিন'],
        'scheduled' => ['মনোনীত', 'প্রকাশের তারিখ ঠিক হয়েছে'],
        'published' => ['প্রকাশিত', ''],
        'rejected' => ['এবার মনোনীত হয়নি', ''],
        'archived' => ['আর্কাইভ', ''],
    ];

    /**
     * The account is four short pages instead of one long one (owner + UX review): home (what needs you now),
     * writings (all pieces, filtered, 20 at a time), reading (saved pieces, followed writers), settings.
     */
    private const PER_PAGE = 20;

    private static function mine(Request $request)
    {
        return Post::query()->whereHas('authors', fn ($a) => $a->where('user_id', $request->user()->id));
    }

    /** What every account page shows at the top. */
    private static function shell(Request $request, string $tab, string $title): array
    {
        $user = $request->user();
        $author = \App\Models\Author::where('user_id', $user->id)->first();
        $counts = self::mine($request)->selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status');

        return [
            'seo' => AuthController::seo($title), 'tab' => $tab, 'user' => $user, 'author' => $author, 'counts' => $counts,
            'twoFactorOn' => filled($user->app_authentication_secret),
            'needsVerification' => self::verificationRequired() && ! $user->hasVerifiedEmail(),
            'certCount' => \App\Models\Certificate::whereNull('revoked_at')->whereIn('author_id', \App\Models\Author::where('user_id', $user->id)->select('id'))->count(),
        ];
    }

    /**
     * D166: "আমার" opens "আমার লেখা" for a writer and "আমার পড়া" for a reader (who never sees empty writing pages).
     * আমার লেখা: what needs the writer first (sent back, drafts), then with the editor, then published pieces with
     * WhatsApp, certificate and read counts, the reader numbers, and the writer page with its link.
     */
    public function index(Request $request)
    {
        $acct = \App\Support\AccountMenu::for($request->user());
        if (! $acct['writer']) {
            return redirect(Url::to('/account/reading'));   // flashed messages ride along to the next page
        }
        $act = self::mine($request)->with('section')->whereIn('status', ['changes', 'confirm'])->latest('updated_at')->get();
        $drafts = self::mine($request)->with('section')->where('status', 'draft');
        $published = self::mine($request)->with('section')->where('status', 'published')->latest('published_at')->limit(5)->get();
        $authors = \App\Models\Author::where('user_id', $request->user()->id)->get();
        $stats = \App\Support\WriterStats::for($authors);
        $reads = collect($stats['rows'])->mapWithKeys(fn ($r) => [$r['post']->id => $r['reads']]);
        $certs = \App\Models\Certificate::whereNull('revoked_at')->whereIn('post_id', $published->pluck('id'))->whereIn('author_id', $authors->pluck('id'))->get()->keyBy('post_id');

        return view('account.index', self::shell($request, 'home', 'আমার লেখা') + [
            'act' => $act,
            'drafts' => (clone $drafts)->latest('updated_at')->limit(3)->get(),
            'draftCount' => $drafts->count(),
            'review' => self::mine($request)->with('section')->whereIn('status', ['pending', 'scheduled'])->latest('updated_at')->limit(5)->get(),
            'published' => $published, 'reads' => $reads, 'certs' => $certs, 'stats' => $stats,
            'writerPage' => ($a = $acct['author']) && ($a->published_count > 0 || $published->isNotEmpty()) ? $a->url() : null,
        ]);
    }

    /** All writings: filter chips with counts, title search, 20 a page ("আরও দেখুন"). */
    public function writings(Request $request)
    {
        $filter = (string) $request->query('status', 'all');
        $groups = ['action' => ['changes', 'confirm'], 'draft' => ['draft'], 'review' => ['pending', 'scheduled'], 'published' => ['published'], 'other' => ['rejected', 'archived']];
        $q = trim((string) $request->query('q', ''));
        $query = self::mine($request)->with('section');
        if (isset($groups[$filter])) {
            $query->whereIn('status', $groups[$filter]);
        }
        if ($q !== '') {
            $query->where('title', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], mb_substr($q, 0, 80)).'%');
        }
        $posts = $query->orderByRaw("case status when 'changes' then 0 when 'confirm' then 1 when 'draft' then 2 when 'pending' then 3 when 'scheduled' then 4 else 5 end")
            ->orderByRaw('coalesce(published_at, updated_at) desc')->paginate(self::PER_PAGE)->withPath(Url::to('/account/writings'))->withQueryString();
        $user = $request->user();

        return view('account.writings', self::shell($request, 'writings', 'আমার লেখা') + [
            'posts' => $posts, 'filter' => $filter, 'q' => $q, 'groups' => $groups,
            'certificates' => $filter === 'certificates'
                ? \App\Models\Certificate::whereNull('revoked_at')->whereIn('author_id', \App\Models\Author::where('user_id', $user->id)->select('id'))->latest('issued_at')->get()
                : \App\Models\Certificate::whereNull('revoked_at')->whereIn('post_id', $posts->pluck('id'))->whereIn('author_id', \App\Models\Author::where('user_id', $user->id)->select('id'))->get(),
            'certRequested' => \App\Models\EditorTask::open('certificate_request')->where('subject_type', (new Post)->getMorphClass())->whereIn('subject_id', $posts->pluck('id'))->pluck('subject_id')->all(),
        ]);
    }

    /** "আমার পাঠক" (owner, 2026-11-12): a writer's own numbers and a card to share each piece. */
    public function stats(Request $request)
    {
        $authors = \App\Models\Author::where('user_id', $request->user()->id)->get();

        return view('account.stats', self::shell($request, 'writings', 'আমার পাঠক') + ['s' => \App\Support\WriterStats::for($authors), 'authors' => $authors]);
    }

    /**
     * D166 "আমার পড়া": where the reader stopped, what is new from those they follow, saved pieces (20 a page),
     * who and what they follow, liked and recently read pieces.
     */
    public function reading(Request $request)
    {
        $user = $request->user();
        $db = \Illuminate\Support\Facades\DB::class;
        $savedIds = $db::table('saved_posts')->where('user_id', $user->id)->select('post_id');
        $likedIds = $db::table('post_likes')->where('user_id', $user->id)->latest('created_at')->limit(20)->pluck('post_id');

        return view('account.reading', self::shell($request, 'reading', 'আমার পড়া') + [
            'unfinished' => $user->reading_history ? \App\Support\ReadingHistory::unfinished($user) : collect(),
            'fresh' => \App\Support\ReadingHistory::newForYou($user),
            'saved' => Post::published()->with('authors', 'section')->whereIn('id', $savedIds)->latest('published_at')->paginate(self::PER_PAGE)->withPath(Url::to('/account/reading')),
            'following' => \App\Models\Author::whereIn('id', $db::table('follows')->where('user_id', $user->id)->select('author_id'))->orderBy('display_name')->get(),
            'serials' => \App\Models\Serial::whereIn('id', $db::table('serial_follows')->where('user_id', $user->id)->select('serial_id'))->get(),
            'liked' => Post::published()->with('authors', 'section')->whereIn('id', $likedIds)->get()->sortBy(fn ($p) => $likedIds->search($p->id))->values(),
            'recent' => $user->reading_history ? \App\Support\ReadingHistory::recent($user) : collect(),
        ]);
    }

    /** The writer's own profile, a main tab of its own (owner): photo, cover, names, links, about. */
    public function profile(Request $request)
    {
        return view('account.profile', self::shell($request, 'profile', 'আমার পাতা'));
    }

    public function settings(Request $request)
    {
        return view('account.settings', self::shell($request, 'settings', 'ই-মেল ও পাসওয়ার্ড') + ['setup' => self::twoFactorSetup($request)]);
    }

    /** While 2FA is being set up: the QR code and the key to type by hand. */
    private static function twoFactorSetup(Request $request): ?array
    {
        $secret = $request->session()->get('2fa_setup');
        if (! $secret) {
            return null;
        }
        $qr = rescue(function () use ($secret) {
            \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('desk'));

            return \Filament\Auth\MultiFactor\App\AppAuthentication::make()->brandName('হাতপাখা')->generateQrCodeDataUri($secret);
        }, null, false);

        return ['secret' => trim(chunk_split($secret, 4, ' ')), 'qr' => is_string($qr) && str_starts_with($qr, 'data:image/') ? $qr : null];
    }

    /** The writer's public profile (D59): Bengali name, bio, city, links, photo and cover. */
    public function updateProfile(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required_without:name_bn', 'nullable', 'string', 'min:2', 'max:80'],
            'name_bn' => ['nullable', 'string', 'min:2', 'max:80'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:60'],
            'website' => ['nullable', 'url:https', 'max:200'],
            'facebook' => ['nullable', 'url:https', 'max:200', 'regex:~^https://(www\.|m\.)?facebook\.com/~i'],
            'instagram' => ['nullable', 'url:https', 'max:200', 'regex:~^https://(www\.)?instagram\.com/~i'],
            'avatar' => ['nullable', 'file', 'max:8192', 'mimetypes:image/jpeg,image/png,image/webp'],
            'cover' => ['nullable', 'file', 'max:8192', 'mimetypes:image/jpeg,image/png,image/webp'],
            'remove_avatar' => ['nullable', 'boolean'],
            'remove_cover' => ['nullable', 'boolean'],
        ], [
            'website.url' => 'ওয়েবসাইটের ঠিকানা https:// দিয়ে শুরু হবে।',
            'facebook.regex' => 'ফেসবুক পাতার পুরো ঠিকানা দিন (https://facebook.com/…)।',
            'instagram.regex' => 'ইনস্টাগ্রামের পুরো ঠিকানা দিন (https://instagram.com/…)।',
            // D166: plain words for older users (no MB, no file types)
            'avatar.max' => 'ছবিটি খুব বড়। ফোনের অন্য একটা ছবি দিন।', 'cover.max' => 'ছবিটি খুব বড়। ফোনের অন্য একটা ছবি দিন।',
            'avatar.mimetypes' => 'এই ফাইলটি ছবি নয়। ফোনের গ্যালারি থেকে একটা ছবি বেছে নিন।', 'cover.mimetypes' => 'এই ফাইলটি ছবি নয়। ফোনের গ্যালারি থেকে একটা ছবি বেছে নিন।',
            'name.required_without' => 'আপনার নাম লিখুন।', 'name.min' => 'নামটি অন্তত দুই অক্ষরের হোক।', 'name_bn.min' => 'নামটি অন্তত দুই অক্ষরের হোক।',
        ]);
        $data['name'] = filled($data['name'] ?? null) ? $data['name'] : $data['name_bn'];
        $user->forceFill(['name' => strip_tags($data['name'])])->save();
        $author = \App\Models\Author::where('user_id', $user->id)->first() ?? \App\Models\Author::create([
            'user_id' => $user->id, 'display_name' => $user->name, 'slug' => 'writer-'.$user->id,
        ]);
        $links = array_filter(['website' => $data['website'] ?? null, 'facebook' => $data['facebook'] ?? null, 'instagram' => $data['instagram'] ?? null]);
        $changes = [
            'display_name' => strip_tags($data['name']),
            'display_name_bn' => strip_tags((string) ($data['name_bn'] ?? '')) ?: null,
            'bio' => trim(strip_tags((string) ($data['bio'] ?? ''))) ?: null,
            'city' => strip_tags((string) ($data['city'] ?? '')) ?: null,
            'links' => $links ?: null,
        ];
        foreach (['avatar' => 'avatar_path', 'cover' => 'cover_path'] as $field => $column) {
            if ($request->hasFile($field)) {
                $new = \App\Support\ProfileImage::store($request->file($field), $field);
                \App\Support\ProfileImage::remove($author->{$column});
                $changes[$column] = $new;
            } elseif ($request->boolean('remove_'.$field)) {
                \App\Support\ProfileImage::remove($author->{$column});
                $changes[$column] = null;
            }
        }
        $author->forceFill($changes)->save();

        return redirect(Url::to('/account/profile'))->with('status', \App\Support\AccountMenu::for($user)['writer'] ? 'রাখা হয়েছে। পাঠকেরা এখন এভাবেই আপনাকে দেখবেন।' : 'রাখা হয়েছে। এখন থেকে আপনার নাম “'.strip_tags($data['name']).'”।');
    }

    /** D67: the writer adds (or removes) their own reading of a piece. Waits for the owner. */
    public function audio(Request $request, Post $post)
    {
        abort_unless($post->authors()->where('user_id', $request->user()->id)->exists() && $post->status !== 'rejected', 403);
        // D94: once a reading is live on a published piece, only the editor changes it (the writer asks).
        if ($post->status === 'published' && $post->audio_status === 'approved') {
            return redirect(Url::to('/account/writings'))->with('status', 'প্রকাশিত লেখার পাঠ আর বদলানো যায় না। বদলাতে চাইলে "বদল চাই" টিপে সম্পাদককে লিখুন।');
        }
        if ($request->boolean('remove')) {
            \App\Support\AudioReading::remove($post);

            return redirect(Url::to('/account/writings'))->with('status', 'পাঠটি সরানো হয়েছে।');
        }
        $request->validate(['audio' => ['required', 'file', 'max:'.(\App\Support\AudioReading::MAX_MB * 1024)]],
            ['audio.required' => 'একটি অডিও ফাইল বেছে নিন।', 'audio.max' => 'ফাইলটি '.\App\Support\Bn::num((string) \App\Support\AudioReading::MAX_MB).' MB-এর মধ্যে হতে হবে।']);
        \App\Support\AudioReading::attach($post, $request->file('audio'));

        return redirect(Url::to('/account/writings'))->with('status', 'পাঠটি জমা পড়েছে। সম্পাদক শুনে অনুমোদন করলেই লেখার পাতায় যোগ হবে।');
    }

    /**
     * "শংসাপত্র চাই" (D73): a writer asks for the certificate of an older published piece. With automatic
     * certificates on, it is made at once; otherwise the owner gets a request on আজকের কাজ.
     */
    public function requestCertificate(Request $request, Post $post)
    {
        abort_unless($post->isLive() && $post->authors()->where('user_id', $request->user()->id)->exists(), 403);
        $mine = \App\Models\Certificate::where('post_id', $post->id)->where('kind', 'publication')->whereNull('revoked_at')
            ->whereIn('author_id', \App\Models\Author::where('user_id', $request->user()->id)->select('id'))->first();
        if ($mine) {
            return redirect($mine->verifyUrl());
        }
        if (\App\Models\Setting::get('certificates.auto') === '1') {
            \App\Models\Certificate::issueFor($post, tell: false);
            \App\Models\AuditLog::record('certificate.requested_issued', $post, ['by' => $request->user()->id]);
            $made = \App\Models\Certificate::where('post_id', $post->id)->where('kind', 'publication')->whereNull('revoked_at')
                ->whereIn('author_id', \App\Models\Author::where('user_id', $request->user()->id)->select('id'))->first();

            // D166: straight to the new certificate (the walk-through found the old jump to the list confusing)
            return $made ? redirect($made->verifyUrl())->with('status', 'আপনার সার্টিফিকেট তৈরি হয়েছে। নিচের বোতাম থেকে ছবি বা PDF নামান, প্রিন্ট করুন বা WhatsApp-এ পাঠান। পরে আবার পাবেন “আমার লেখা”-য়, লেখার পাশের “সার্টিফিকেট” বোতামে।')
                : back(302, [], Url::to('/account'))->with('status', 'এই লেখার সার্টিফিকেট এখান থেকে তৈরি করা গেল না। সম্পাদককে লিখুন, তাঁরা দেখে দেবেন।');
        }
        \App\Models\EditorTask::propose('certificate_request', 'লেখক শংসাপত্র চেয়েছেন: '.$post->title, ['by' => $request->user()->id], $post);

        return back(302, [], Url::to('/account/writings'))->with('status', 'অনুরোধ পাঠানো হয়েছে। সম্পাদক দেখে শংসাপত্র দিলে আপনাকে জানানো হবে।');
    }

    public function updateNewsletter(Request $request)
    {
        $request->user()->forceFill(['mail_opt_out' => ! $request->boolean('newsletter')])->save();

        return redirect(Url::to('/account/settings').'#letters')->with('status', $request->boolean('newsletter') ? 'সাপ্তাহিক ই-মেল চালু হল।' : 'সাপ্তাহিক ই-মেল বন্ধ হল। নিজের লেখার খবর (যেমন সম্পাদকের কথা) আগের মতোই পাবেন।');
    }

    /** D166: the post page reports how far a signed-in reader got (sendBeacon on leaving; nothing if their switch is off). */
    public function readingProgress(Request $request, Post $post)
    {
        \App\Support\ReadingHistory::record($request->user(), $post, (int) $request->input('p', 0));

        return response()->noContent();
    }

    public function readingHistorySwitch(Request $request)
    {
        $on = $request->boolean('on');
        $request->user()->forceFill(['reading_history' => $on])->save();

        return redirect(Url::to('/account/settings').'#reading')->with('status', $on ? 'এখন থেকে কী পড়ছেন মনে রাখা হবে।' : 'আর মনে রাখা হবে না। আগের হিসেব মুছতে চাইলে নিচের “মুছুন” টিপুন।');
    }

    public function readingHistoryClear(Request $request)
    {
        \App\Support\ReadingHistory::clear($request->user());

        return redirect(Url::to('/account/settings').'#reading')->with('status', 'পড়ার খাতা মুছে দেওয়া হয়েছে। “পরে পড়ব”, “ভালো লেগেছে” আর আপনার লেখা যেমন ছিল আছে।');
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::min(10)->letters()->numbers()->uncompromised()],
        ], ['current_password.current_password' => 'এখনকার পাসওয়ার্ড মিলছে না।', 'password.confirmed' => 'দুটি পাসওয়ার্ড মেলেনি।',
            'password.uncompromised' => 'এই পাসওয়ার্ডটি অন্য কোনো সাইটের ফাঁস হওয়া তালিকায় আছে। অন্য একটি দিন।']);
        $request->user()->forceFill(['password' => $request->input('password'), 'legacy_password' => null])->save();
        \Illuminate\Support\Facades\Auth::logoutOtherDevices($request->input('password'));
        \App\Models\AuditLog::record('user.password_changed', $request->user());

        return redirect(Url::to('/account/settings').'#security')->with('status', 'পাসওয়ার্ড বদলেছে। অন্য সব জায়গা থেকে লগ-আউট করা হয়েছে।');
    }

    /** 2FA, step 1 (D59): a new secret, kept in the session until the first code proves the app has it. */
    public function twoFactorStart(Request $request)
    {
        $request->session()->put('2fa_setup', \Filament\Auth\MultiFactor\App\AppAuthentication::make()->generateSecret());

        return redirect(Url::to('/account/settings').'#security');
    }

    public function twoFactorConfirm(Request $request)
    {
        $secret = (string) $request->session()->get('2fa_setup');
        $request->validate(['code' => ['required', 'digits:6']], ['code.digits' => 'অ্যাপের ৬ অঙ্কের কোডটি দিন।']);
        $mfa = \Filament\Auth\MultiFactor\App\AppAuthentication::make();
        if ($secret === '' || ! $mfa->verifyCode((string) $request->input('code'), $secret)) {
            return redirect(Url::to('/account/settings').'#security')->withErrors(['code' => 'কোডটি মিলছে না। অ্যাপে দেখানো এখনকার কোডটি দিন।']);
        }
        $codes = $mfa->generateRecoveryCodes();
        $user = $request->user();
        $user->forceFill(['app_authentication_secret' => $secret, 'app_authentication_recovery_codes' => array_map(fn ($c) => \Illuminate\Support\Facades\Hash::make($c), $codes)])->save();
        $request->session()->forget('2fa_setup');
        \App\Models\AuditLog::record('user.2fa_enabled', $user);

        return redirect(Url::to('/account/settings').'#security')->with(['status' => 'দুই-ধাপের যাচাই চালু হয়েছে।', 'recovery_codes' => $codes]);
    }

    public function twoFactorDisable(Request $request)
    {
        abort_if($request->user()->hasRole(\App\Enums\Role::Editor), 403);   // desk accounts must keep 2FA (docs/SECURITY.md)
        $request->validate(['current_password' => ['required', 'current_password']], ['current_password.current_password' => 'পাসওয়ার্ড মিলছে না।']);
        $request->user()->forceFill(['app_authentication_secret' => null, 'app_authentication_recovery_codes' => null])->save();
        \App\Models\AuditLog::record('user.2fa_disabled', $request->user());

        return redirect(Url::to('/account/settings').'#security')->with('status', 'দুই-ধাপের যাচাই বন্ধ হয়েছে।');
    }

    public function updateName(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'min:2', 'max:80']]);
        $request->user()->forceFill(['name' => strip_tags($data['name'])])->save();

        return redirect(Url::to('/account'))->with('status', 'নাম বদলানো হয়েছে।');
    }

    /** Email verification is enforced only where real email is sent (staging uses the log mailer). */
    public static function verificationRequired(): bool
    {
        return ! in_array(config('mail.default'), ['log', 'array', null, ''], true);
    }

    /**
     * D94 (owner): a submitted or published piece can't be changed by its writer; they ask the editor instead.
     * The request lands in /desk → বার্তা with a link to the piece. At most 3 open requests per writer.
     */
    public function requestChange(Request $request, Post $post)
    {
        $user = $request->user();
        abort_unless($post->authors()->where('user_id', $user->id)->exists() || $post->submitted_by === $user->id, 404);
        $back = Url::to('/account/writings');
        if (! in_array($post->status, ['pending', 'scheduled', 'confirm', 'published'], true)) {
            return redirect($back)->with('status', 'এই লেখাটি আপনি নিজেই বদলাতে পারেন।');
        }
        $data = \Illuminate\Support\Facades\Validator::make($request->all(), ['what' => ['required', 'string', 'min:5', 'max:1500']],
            ['what.required' => 'কী বদলাতে চান, লিখুন।', 'what.min' => 'কী বদলাতে চান, একটু বিশদে লিখুন।', 'what.max' => 'একটু ছোট করে লিখুন (১৫০০ অক্ষরের মধ্যে)।']);
        if ($data->fails()) {
            return redirect($back)->withErrors($data, 'change')->with('change_post', $post->id);
        }
        $open = \App\Models\ContactMessage::where('kind', 'change')->where('user_id', $user->id)->where('status', 'new')->count();
        if ($open >= 3) {
            return redirect($back)->with('status', 'আপনার আগের তিনটি অনুরোধ এখনও দেখা হয়নি। সম্পাদক সেগুলো দেখলে আবার পাঠাতে পারবেন।');
        }
        $msg = \App\Models\ContactMessage::create([
            'kind' => 'change', 'topic' => 'piece', 'name' => $user->name, 'email' => $user->email, 'user_id' => $user->id,
            'link' => $post->status === 'published' ? $post->url() : null,
            'interests' => ['post_id' => $post->id, 'title' => $post->title],
            'body' => trim(strip_tags(str_replace("\r\n", "\n", (string) $request->input('what')))),
        ]);
        \App\Models\AuditLog::record('post.change_requested', $post, ['message' => $msg->id]);
        $msg->notifyAdmins();

        return redirect($back)->with('status', 'অনুরোধ সম্পাদকের কাছে পৌঁছেছে। তিনি দেখে ঠিক করে দেবেন, দরকার হলে ই-মেলে জানাবেন।');
    }
}
