<?php

use App\Http\Controllers\ArchiveController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LegacyController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

/*
| URL policy: docs/SEO.md §1. Old WordPress URLs must keep working — never change a
| public pattern without adding a 301 and a test in tests/Feature/SeoUrlTest.php.
*/

Route::get('/', HomeController::class)->name('home');

// Crawlers
Route::get('/robots.txt', [SitemapController::class, 'robots']);
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::get('/sitemap-{type}.xml', [SitemapController::class, 'show'])->where('type', 'posts-\d+|sections|authors|issues|pages');

// Feeds (trailing slash is enforced by middleware; Laravel matches with or without it)
Route::get('/feed', [FeedController::class, 'site']);
Route::get('/category/{slug}/feed', [FeedController::class, 'section']);
Route::get('/author/{slug}/feed', [FeedController::class, 'author']);

// Archives
Route::get('/category/{slug}', [ArchiveController::class, 'section'])->name('section');
Route::get('/author/{slug}', [ArchiveController::class, 'author'])->name('author');
Route::get('/tag/{slug}', [ArchiveController::class, 'tag'])->name('tag');
Route::get('/archive', [ArchiveController::class, 'archiveIndex'])->name('archive');
Route::get('/archive/{year}', [ArchiveController::class, 'archiveYear'])->where('year', '(19|20)\d\d')->name('archive.year');
Route::get('/sonkha/{slug}', [ArchiveController::class, 'issue'])->where('slug', '\d{4}-\d{2}')->name('issue');
Route::get('/sonkha/{slug}/boi', [ArchiveController::class, 'issueBook'])->where('slug', '\d{4}-\d{2}')->name('issue.book');   // D68: book view, noindex
Route::get('/issue-print/{issue}', function (\App\Models\Issue $issue) {   // D68: print-ready book for "Save as PDF" (editors only)
    abort_unless(auth()->user()?->hasRole(\App\Enums\Role::Editor), 403);

    return view('issue-print', ['issue' => $issue, 'pieces' => \App\Support\IssueBook::pieces($issue)]);
})->middleware('auth')->name('issue.print');

// Certificate verification (public, read-only)
Route::get('/certificate/{code}', CertificateController::class)->where('code', '[A-Z0-9]{6,16}')->name('certificate');

// Old WordPress attack surface → 410 Gone (docs/SECURITY.md §5)
Route::any('/{path}', [LegacyController::class, 'gone'])
    ->where('path', 'wp-login\.php|xmlrpc\.php|wp-admin(/.*)?|wp-json(/.*)?|wp-includes(/.*)?|wp-content/plugins(/.*)?|wp-content/themes(/.*)?');

// Old AMP URLs → canonical post
Route::get('/{slug}/amp', [LegacyController::class, 'amp'])->where('slug', '[^/]+');

// Other old WordPress URL shapes (Phase 2 URL audit, docs/SEO.md §1) → 301 to the closest page
Route::get('/comments/feed', fn () => redirect(\App\Support\Url::to('/feed'), 301));
Route::get('/page/{n}', fn () => redirect(url('/').'/', 301))->where('n', '\d+');
Route::get('/{type}/{slug}/page/{n}', [LegacyController::class, 'paged'])->where(['type' => 'category|tag|author', 'n' => '\d+']);
Route::get('/category/{parent}/{child}', [LegacyController::class, 'nestedCategory'])->where(['parent' => '[^/]+', 'child' => '(?!feed$)[^/]+']);
Route::get('/sitemap.html', fn () => redirect(url('/').'/', 301));
Route::get('/{y}/{m?}/{d?}', [LegacyController::class, 'dateArchive'])->where(['y' => '(19|20)\d\d', 'm' => '\d{1,2}', 'd' => '\d{1,2}']);
Route::get('/{slug}/feed', [LegacyController::class, 'toPost'])->where('slug', '(?!(desk|livewire|filament|storage|build)$)[^/]+');

// Accounts (Phase 3a). Throttles: AppServiceProvider. All pages noindex.
Route::middleware('guest')->group(function () {
    Route::get('/login', [\App\Http\Controllers\Auth\AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [\App\Http\Controllers\Auth\AuthController::class, 'login'])->middleware('throttle:login');
    Route::get('/register', [\App\Http\Controllers\Auth\AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [\App\Http\Controllers\Auth\AuthController::class, 'register'])->middleware('throttle:register');
    Route::get('/forgot-password', [\App\Http\Controllers\Auth\AuthController::class, 'showForgot'])->name('password.request');
    Route::post('/forgot-password', [\App\Http\Controllers\Auth\AuthController::class, 'sendResetLink'])->middleware('throttle:password-reset')->name('password.email');
    Route::get('/reset-password/{token}', [\App\Http\Controllers\Auth\AuthController::class, 'showReset'])->name('password.reset');
    Route::post('/reset-password', [\App\Http\Controllers\Auth\AuthController::class, 'reset'])->middleware('throttle:password-reset')->name('password.update');
});
// D59: after sign-up, the link in the e-mail verifies the address and signs the writer in.
Route::get('/register/check-email', [\App\Http\Controllers\Auth\AuthController::class, 'checkEmail'])->name('register.check');
Route::get('/login/2fa', [\App\Http\Controllers\Auth\AuthController::class, 'showTwoFactor'])->middleware('guest')->name('login.2fa');
Route::post('/login/2fa', [\App\Http\Controllers\Auth\AuthController::class, 'twoFactor'])->middleware(['guest', 'throttle:6,1']);
Route::get('/email/verify/{id}/{hash}', [\App\Http\Controllers\Auth\VerifyEmailController::class, 'verify'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
Route::middleware('auth')->group(function () {
    Route::post('/logout', [\App\Http\Controllers\Auth\AuthController::class, 'logout'])->name('logout');
    Route::get('/email/verify', [\App\Http\Controllers\Auth\VerifyEmailController::class, 'notice'])->name('verification.notice');
    Route::post('/email/verification-notification', [\App\Http\Controllers\Auth\VerifyEmailController::class, 'resend'])->middleware('throttle:6,1')->name('verification.send');
});
Route::middleware(['auth', \App\Http\Middleware\EnsureEmailVerified::class])->group(function () {
    Route::get('/account', [\App\Http\Controllers\AccountController::class, 'index'])->name('account');
    Route::post('/account/name', [\App\Http\Controllers\AccountController::class, 'updateName']);
    Route::post('/account/profile', [\App\Http\Controllers\AccountController::class, 'updateProfile'])->middleware('throttle:10,1');
    Route::post('/account/audio/{post}', [\App\Http\Controllers\AccountController::class, 'audio'])->whereNumber('post')->middleware('throttle:10,1');
    Route::post('/account/newsletter', [\App\Http\Controllers\AccountController::class, 'updateNewsletter']);
    Route::post('/account/password', [\App\Http\Controllers\AccountController::class, 'updatePassword'])->middleware('throttle:5,1');
    Route::post('/account/2fa/start', [\App\Http\Controllers\AccountController::class, 'twoFactorStart'])->middleware('throttle:10,1');
    Route::post('/account/2fa/confirm', [\App\Http\Controllers\AccountController::class, 'twoFactorConfirm'])->middleware('throttle:6,1');
    Route::post('/account/2fa/disable', [\App\Http\Controllers\AccountController::class, 'twoFactorDisable'])->middleware('throttle:5,1');
});

// A fresh form token for pages served from the shared cache (D65). Never cached.
Route::get('/csrf-token', fn () => response()->json(['token' => csrf_token()])->header('Cache-Control', 'no-store, private'))
    ->middleware('throttle:60,1')->name('csrf');

// Site search (D64): results are noindex.
Route::get('/search', \App\Http\Controllers\SearchController::class)->middleware('throttle:40,1')->name('search');
// Reader features (D64): likes, reading list, following writers.
Route::middleware(['auth', \App\Http\Middleware\EnsureEmailVerified::class, 'throttle:60,1'])->group(function () {
    Route::post('/like/{post}', [\App\Http\Controllers\ReaderController::class, 'like'])->whereNumber('post')->name('like');
    Route::post('/save/{post}', [\App\Http\Controllers\ReaderController::class, 'save'])->whereNumber('post')->name('save');
    Route::post('/follow/{author}', [\App\Http\Controllers\ReaderController::class, 'follow'])->whereNumber('author')->name('follow');
});

// One-click unfollow from the e-mail (D64), no sign-in needed.
Route::get('/newsletter/unsubscribe/f/{author}/{user}', function (\App\Models\Author $author, \App\Models\User $user) {
    \Illuminate\Support\Facades\DB::table('follows')->where(['author_id' => $author->id, 'user_id' => $user->id])->delete();
    $author->forceFill(['followers_count' => \Illuminate\Support\Facades\DB::table('follows')->where('author_id', $author->id)->count()])->saveQuietly();

    return response()->view('newsletter-message', ['seo' => new \App\Seo\Seo(title: 'অনুসরণ বন্ধ হয়েছে', noindex: true),
        'title' => 'অনুসরণ বন্ধ হয়েছে', 'text' => $author->publicName().'-এর নতুন লেখার ই-মেল আর পাঠানো হবে না।']);
})->middleware(['signed', 'throttle:20,1'])->name('unfollow');

// The submission-page helper chat (D52): guests get public answers, writers their own pieces' status.
Route::post('/chat', [\App\Http\Controllers\ChatController::class, 'ask'])->middleware('throttle:30,1')->name('chat');

// Submissions (Phase 3b): the only way to submit (D29). Writers only see/edit their own pieces.
Route::middleware(['auth', \App\Http\Middleware\EnsureEmailVerified::class])->group(function () {
    Route::get('/submit', [\App\Http\Controllers\SubmissionController::class, 'create'])->name('submit');
    Route::post('/submit', [\App\Http\Controllers\SubmissionController::class, 'store']);
    Route::post('/submit/autosave', [\App\Http\Controllers\SubmissionController::class, 'autosave'])->middleware('throttle:60,1');
    Route::post('/submit/preview', [\App\Http\Controllers\SubmissionController::class, 'preview'])->middleware('throttle:60,1');
    Route::get('/submit/{post}', [\App\Http\Controllers\SubmissionController::class, 'edit'])->whereNumber('post');
    Route::post('/submit/{post}', [\App\Http\Controllers\SubmissionController::class, 'update'])->whereNumber('post');
    Route::post('/submit/{post}/confirm', [\App\Http\Controllers\SubmissionController::class, 'confirm'])->whereNumber('post');
    Route::post('/submit/{post}/autosave', [\App\Http\Controllers\SubmissionController::class, 'autosave'])->whereNumber('post')->middleware('throttle:60,1');
});

// /desk language (D45): saved on the user's account, then back to where they were.
Route::get('/desk-language/{locale}', function (\Illuminate\Http\Request $request, string $locale) {
    abort_unless(in_array($locale, ['bn', 'en'], true), 404);
    $request->user()->forceFill(['desk_locale' => $locale])->save();

    return redirect()->back(fallback: url('/desk'));
})->middleware('auth')->name('desk.language');

// Newsletter: one-click unsubscribe from the signed link in every issue (D48). Never emailed again.
// Newsletter sign-up with double opt-in (D58).
Route::post('/newsletter/subscribe', [\App\Http\Controllers\NewsletterController::class, 'subscribe'])->middleware('throttle:6,1')->name('newsletter.subscribe');
Route::get('/newsletter/confirm/{subscriber}', [\App\Http\Controllers\NewsletterController::class, 'confirm'])->middleware(['signed', 'throttle:20,1'])->name('newsletter.confirm');
Route::get('/newsletter/unsubscribe/w/{user}', [\App\Http\Controllers\NewsletterController::class, 'unsubscribeWriter'])->middleware(['signed', 'throttle:20,1'])->name('newsletter.unsubscribe.writer');
Route::get('/newsletter/unsubscribe/{subscriber}', function (\App\Models\NewsletterSubscriber $subscriber) {
    $subscriber->forceFill(['status' => 'unsubscribed', 'suppressed' => true])->save();
    \App\Models\AuditLog::record('newsletter.unsubscribed', $subscriber);

    return response()->view('newsletter-unsubscribed', ['seo' => new \App\Seo\Seo(title: 'চিঠি বন্ধ হয়েছে', noindex: true)]);
})->middleware('signed')->name('newsletter.unsubscribe');

// Reader comments (D39): guests and signed-in readers; plain text, moderated until trusted.
Route::post('/comment/{post}', [\App\Http\Controllers\CommentController::class, 'store'])->whereNumber('post')->middleware('throttle:comment')->name('comment.store');

// Posts at the root, exactly like WordPress. Reserved words are excluded.
Route::get('/{slug}', PostController::class)
    ->where('slug', '(?!(desk|livewire|filament|up|storage|build|search|submit|account|login|logout|register)$)[^/]+')
    ->name('post');

// Old attachment pages /{post}/{attachment}/ → the post (registered after every fixed 2-segment route)
Route::get('/{slug}/{child}', [LegacyController::class, 'toPost'])
    ->where(['slug' => '(?!(desk|livewire|filament|storage|build|fonts|images|up|wp-content|wp-admin|wp-includes|wp-json|category|tag|author|sonkha|certificate)$)[^/]+', 'child' => '[^/]+']);

// Anything else: check the redirects table, then 404.
Route::fallback([LegacyController::class, 'fallback']);
