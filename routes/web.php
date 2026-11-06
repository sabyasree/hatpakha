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
Route::middleware('auth')->group(function () {
    Route::post('/logout', [\App\Http\Controllers\Auth\AuthController::class, 'logout'])->name('logout');
    Route::get('/account', [\App\Http\Controllers\AccountController::class, 'index'])->name('account');
    Route::post('/account/name', [\App\Http\Controllers\AccountController::class, 'updateName']);
    Route::get('/email/verify', [\App\Http\Controllers\Auth\VerifyEmailController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [\App\Http\Controllers\Auth\VerifyEmailController::class, 'verify'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/email/verification-notification', [\App\Http\Controllers\Auth\VerifyEmailController::class, 'resend'])->middleware('throttle:6,1')->name('verification.send');
});

// Submissions (Phase 3b): the only way to submit (D29). Writers only see/edit their own pieces.
Route::middleware('auth')->group(function () {
    Route::get('/submit', [\App\Http\Controllers\SubmissionController::class, 'create'])->name('submit');
    Route::post('/submit', [\App\Http\Controllers\SubmissionController::class, 'store']);
    Route::post('/submit/autosave', [\App\Http\Controllers\SubmissionController::class, 'autosave'])->middleware('throttle:60,1');
    Route::post('/submit/preview', [\App\Http\Controllers\SubmissionController::class, 'preview'])->middleware('throttle:60,1');
    Route::get('/submit/{post}', [\App\Http\Controllers\SubmissionController::class, 'edit'])->whereNumber('post');
    Route::post('/submit/{post}', [\App\Http\Controllers\SubmissionController::class, 'update'])->whereNumber('post');
    Route::post('/submit/{post}/autosave', [\App\Http\Controllers\SubmissionController::class, 'autosave'])->whereNumber('post')->middleware('throttle:60,1');
});

// Posts at the root, exactly like WordPress. Reserved words are excluded.
Route::get('/{slug}', PostController::class)
    ->where('slug', '(?!(desk|livewire|filament|up|storage|build|search|submit|account|login|logout|register)$)[^/]+')
    ->name('post');

// Old attachment pages /{post}/{attachment}/ → the post (registered after every fixed 2-segment route)
Route::get('/{slug}/{child}', [LegacyController::class, 'toPost'])
    ->where(['slug' => '(?!(desk|livewire|filament|storage|build|fonts|images|up|wp-content|wp-admin|wp-includes|wp-json|category|tag|author|sonkha|certificate)$)[^/]+', 'child' => '[^/]+']);

// Anything else: check the redirects table, then 404.
Route::fallback([LegacyController::class, 'fallback']);
