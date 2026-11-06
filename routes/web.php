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
Route::get('/{y}/{m?}/{d?}', [LegacyController::class, 'dateArchive'])->where(['y' => '(19|20)\d\d', 'm' => '\d{1,2}', 'd' => '\d{1,2}']);
Route::get('/{slug}/feed', [LegacyController::class, 'toPost'])->where('slug', '(?!(desk|livewire|filament|storage|build)$)[^/]+');

// Posts at the root, exactly like WordPress. Reserved words are excluded.
Route::get('/{slug}', PostController::class)
    ->where('slug', '(?!(desk|livewire|filament|up|storage|build|search|submit|account|login|logout|register)$)[^/]+')
    ->name('post');

// Old attachment pages /{post}/{attachment}/ → the post (registered after every fixed 2-segment route)
Route::get('/{slug}/{child}', [LegacyController::class, 'toPost'])
    ->where(['slug' => '(?!(desk|livewire|filament|storage|build|fonts|images|up|wp-content|wp-admin|wp-includes|wp-json|category|tag|author|sonkha|certificate)$)[^/]+', 'child' => '[^/]+']);

// Anything else: check the redirects table, then 404.
Route::fallback([LegacyController::class, 'fallback']);
