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
Route::get('/sitemap-{type}.xml', [SitemapController::class, 'show'])->where('type', 'posts-\d+|sections|authors|issues');

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

// Posts at the root, exactly like WordPress. Reserved words are excluded.
Route::get('/{slug}', PostController::class)
    ->where('slug', '(?!(desk|livewire|filament|up|storage|build|search|submit|account|login|logout|register)$)[^/]+')
    ->name('post');

// Anything else: check the redirects table, then 404.
Route::fallback([LegacyController::class, 'fallback']);
