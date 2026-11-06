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

    public function index(Request $request)
    {
        $user = $request->user();
        $posts = Post::query()->with('section')
            ->whereHas('authors', fn ($a) => $a->where('user_id', $user->id))
            ->orderByRaw("case status when 'changes' then 0 when 'draft' then 1 when 'pending' then 2 when 'scheduled' then 3 else 4 end")
            ->latest('updated_at')->get();

        return view('account.index', [
            'seo' => AuthController::seo('আমার লেখা'),
            'user' => $user,
            'posts' => $posts,
            // The writer's own certificates (D12, D56), each with its public verification page.
            'certificates' => \App\Models\Certificate::whereNull('revoked_at')->whereIn('author_id', \App\Models\Author::where('user_id', $user->id)->select('id'))->latest('issued_at')->get(),
            // D73: pieces whose certificate the writer has asked for (when certificates need the owner's OK).
            'certRequested' => \App\Models\EditorTask::open('certificate_request')->where('subject_type', (new Post)->getMorphClass())->whereIn('subject_id', $posts->pluck('id'))->pluck('subject_id')->all(),
            'needsVerification' => self::verificationRequired() && ! $user->hasVerifiedEmail(),
            'author' => \App\Models\Author::where('user_id', $user->id)->first(),
            // D64: the reading list and the writers this reader follows.
            'saved' => Post::published()->with('authors', 'section')->whereIn('id', \Illuminate\Support\Facades\DB::table('saved_posts')->where('user_id', $user->id)->select('post_id'))->latest('published_at')->limit(60)->get(),
            'following' => \App\Models\Author::whereIn('id', \Illuminate\Support\Facades\DB::table('follows')->where('user_id', $user->id)->select('author_id'))->orderBy('display_name')->get(),
            'twoFactorOn' => filled($user->app_authentication_secret),
            'setup' => self::twoFactorSetup($request),
        ]);
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
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'name_bn' => ['nullable', 'string', 'max:80'],
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
            'avatar.max' => 'ছবিটি ৮ MB-এর মধ্যে হতে হবে।', 'cover.max' => 'ছবিটি ৮ MB-এর মধ্যে হতে হবে।',
            'avatar.mimetypes' => 'শুধু JPG, PNG বা WebP ছবি দেওয়া যায়।', 'cover.mimetypes' => 'শুধু JPG, PNG বা WebP ছবি দেওয়া যায়।',
        ]);
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

        return redirect(Url::to('/account').'#profile')->with('status', 'প্রোফাইল সাজানো হয়েছে।');
    }

    /** D67: the writer adds (or removes) their own reading of a piece. Waits for the owner. */
    public function audio(Request $request, Post $post)
    {
        abort_unless($post->authors()->where('user_id', $request->user()->id)->exists() && $post->status !== 'rejected', 403);
        if ($request->boolean('remove')) {
            \App\Support\AudioReading::remove($post);

            return redirect(Url::to('/account').'#writing')->with('status', 'পাঠটি সরানো হয়েছে।');
        }
        $request->validate(['audio' => ['required', 'file', 'max:'.(\App\Support\AudioReading::MAX_MB * 1024)]],
            ['audio.required' => 'একটি অডিও ফাইল বেছে নিন।', 'audio.max' => 'ফাইলটি '.\App\Support\Bn::num((string) \App\Support\AudioReading::MAX_MB).' MB-এর মধ্যে হতে হবে।']);
        \App\Support\AudioReading::attach($post, $request->file('audio'));

        return redirect(Url::to('/account').'#writing')->with('status', 'পাঠটি জমা পড়েছে। সম্পাদক শুনে অনুমোদন করলেই লেখার পাতায় যোগ হবে।');
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

            return redirect(Url::to('/account').'#writing')->with('status', 'আপনার শংসাপত্র তৈরি হয়েছে। নিচে "আমার শংসাপত্র" থেকে খুলে নামান বা প্রিন্ট করুন।');
        }
        \App\Models\EditorTask::propose('certificate_request', 'লেখক শংসাপত্র চেয়েছেন: '.$post->title, ['by' => $request->user()->id], $post);

        return redirect(Url::to('/account').'#writing')->with('status', 'অনুরোধ পাঠানো হয়েছে। সম্পাদক দেখে শংসাপত্র দিলে আপনাকে জানানো হবে।');
    }

    public function updateNewsletter(Request $request)
    {
        $request->user()->forceFill(['mail_opt_out' => ! $request->boolean('newsletter')])->save();

        return redirect(Url::to('/account').'#letters')->with('status', $request->boolean('newsletter') ? 'চিঠি চালু থাকল।' : 'চিঠি বন্ধ হয়েছে। নিজের লেখার খবর আগের মতোই পাবেন।');
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

        return redirect(Url::to('/account').'#security')->with('status', 'পাসওয়ার্ড বদলেছে। অন্য সব জায়গা থেকে লগ-আউট করা হয়েছে।');
    }

    /** 2FA, step 1 (D59): a new secret, kept in the session until the first code proves the app has it. */
    public function twoFactorStart(Request $request)
    {
        $request->session()->put('2fa_setup', \Filament\Auth\MultiFactor\App\AppAuthentication::make()->generateSecret());

        return redirect(Url::to('/account').'#security');
    }

    public function twoFactorConfirm(Request $request)
    {
        $secret = (string) $request->session()->get('2fa_setup');
        $request->validate(['code' => ['required', 'digits:6']], ['code.digits' => 'অ্যাপের ৬ অঙ্কের কোডটি দিন।']);
        $mfa = \Filament\Auth\MultiFactor\App\AppAuthentication::make();
        if ($secret === '' || ! $mfa->verifyCode((string) $request->input('code'), $secret)) {
            return redirect(Url::to('/account').'#security')->withErrors(['code' => 'কোডটি মিলছে না। অ্যাপে দেখানো এখনকার কোডটি দিন।']);
        }
        $codes = $mfa->generateRecoveryCodes();
        $user = $request->user();
        $user->forceFill(['app_authentication_secret' => $secret, 'app_authentication_recovery_codes' => array_map(fn ($c) => \Illuminate\Support\Facades\Hash::make($c), $codes)])->save();
        $request->session()->forget('2fa_setup');
        \App\Models\AuditLog::record('user.2fa_enabled', $user);

        return redirect(Url::to('/account').'#security')->with(['status' => 'দুই-ধাপের যাচাই চালু হয়েছে।', 'recovery_codes' => $codes]);
    }

    public function twoFactorDisable(Request $request)
    {
        abort_if($request->user()->hasRole(\App\Enums\Role::Editor), 403);   // desk accounts must keep 2FA (docs/SECURITY.md)
        $request->validate(['current_password' => ['required', 'current_password']], ['current_password.current_password' => 'পাসওয়ার্ড মিলছে না।']);
        $request->user()->forceFill(['app_authentication_secret' => null, 'app_authentication_recovery_codes' => null])->save();
        \App\Models\AuditLog::record('user.2fa_disabled', $request->user());

        return redirect(Url::to('/account').'#security')->with('status', 'দুই-ধাপের যাচাই বন্ধ হয়েছে।');
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
}
