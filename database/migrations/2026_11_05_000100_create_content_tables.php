<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Core content model. See docs/ARCHITECTURE.md → "Data model".
 * Slugs are stored decoded (UTF-8, NFC-normalised); URLs are encoded on output.
 */
return new class extends Migration
{
    public function up(): void
    {
        // A public byline. Linked to a user account, or a "guest" author with no account
        // (WordPress Co-Authors Plus guests, or the "হাতপাখা ডেস্ক" collective byline).
        Schema::create('authors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('slug', 191)->unique();          // = old WordPress nicename → /author/{slug}/
            $table->string('display_name');
            $table->string('display_name_bn')->nullable();  // author's own Bengali spelling (they set it)
            $table->text('bio')->nullable();
            $table->string('avatar_path')->nullable();
            $table->string('cover_path')->nullable();
            $table->string('city')->nullable();
            $table->json('links')->nullable();
            $table->boolean('is_guest')->default(false);
            $table->boolean('is_desk')->default(false);     // the collective "হাতপাখা ডেস্ক" byline
            $table->timestamp('claimed_at')->nullable();
            $table->unsignedBigInteger('legacy_wp_user_id')->nullable()->index();
            $table->unsignedBigInteger('legacy_coauthor_term_id')->nullable()->index();
            $table->unsignedInteger('published_count')->default(0);
            $table->timestamps();
        });

        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 191)->unique();          // = old category slug → /category/{slug}/
            $table->string('name_bn');
            $table->string('name_en')->nullable();
            $table->string('color', 9)->default('#CB1B32');  // logo palette only (docs/DESIGN.md)
            $table->string('format', 16)->default('prose'); // poem | prose → reading layout
            $table->unsignedSmallInteger('sort')->default(100);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('legacy_term_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 191)->unique();
            $table->string('name');
            $table->boolean('is_controlled')->default(false); // only controlled tags are indexed / shown
            $table->unsignedBigInteger('legacy_term_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('moods', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('name_bn');
            $table->unsignedSmallInteger('sort')->default(100);
            $table->timestamps();
        });

        Schema::create('issues', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 16)->unique();           // yyyy-mm → /sonkha/{slug}/
            $table->string('title');
            $table->string('kind', 16)->default('monthly'); // monthly | festival
            $table->string('theme_key', 32)->nullable();    // cover template key (boi, puja, barsha, ...)
            $table->text('editor_note')->nullable();
            $table->string('cover_path')->nullable();
            $table->string('status', 16)->default('planning'); // planning | published
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 400)->unique();
            $table->string('title', 400);
            $table->text('excerpt')->nullable();
            $table->longText('body_html');                  // sanitised on save (App\Support\HtmlSanitizer)
            $table->string('format', 16)->default('prose'); // poem | prose
            $table->string('language', 5)->default('bn');
            // draft | pending | scheduled | published | rejected | archived
            $table->string('status', 16)->default('draft')->index();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('issue_id')->nullable()->constrained()->nullOnDelete();
            $table->string('featured_image_path')->nullable();
            $table->unsignedSmallInteger('featured_image_width')->nullable();
            $table->unsignedSmallInteger('featured_image_height')->nullable();
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->boolean('noindex')->default(false);
            $table->unsignedSmallInteger('reading_minutes')->default(1);
            $table->unsignedBigInteger('legacy_wp_id')->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('author_post', function (Blueprint $table) {
            $table->foreignId('author_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('position')->default(0);
            $table->primary(['author_id', 'post_id']);
        });

        Schema::create('post_tag', function (Blueprint $table) {
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['post_id', 'tag_id']);
        });

        Schema::create('mood_post', function (Blueprint $table) {
            $table->foreignId('mood_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->primary(['mood_id', 'post_id']);
        });

        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('author_name');
            $table->text('body');                            // plain text only, escaped on output
            $table->string('status', 16)->default('pending'); // pending | approved | spam
            // "পাঠকের চিঠি": shown on the home page only if not opted out (docs/SECURITY.md §8)
            $table->boolean('feature_opt_out')->default(false);
            $table->unsignedBigInteger('legacy_wp_id')->nullable()->unique();
            $table->timestamps();
        });

        // Author certificates (publication / contest). Each has a public verification code + QR,
        // so a printed certificate can be checked at /certificate/{code}/ (prevents forgery).
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->string('code', 16)->unique();            // short random verification code
            $table->foreignId('author_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->nullable()->constrained()->nullOnDelete();
            // publication | contest_winner | contest_finalist | contest_participant
            $table->string('kind', 32)->default('publication');
            $table->string('tier', 16)->default('free');     // free | premium | printed
            $table->string('title_snapshot', 400);           // post title at issue time
            $table->string('author_name_snapshot');
            $table->string('issue_label')->nullable();       // e.g. "শারদীয়া ১৪৩৪"
            $table->timestamp('issued_at')->useCurrent();
            $table->timestamp('revoked_at')->nullable();      // e.g. plagiarism found later
            $table->timestamps();
        });

        // Old URL → new URL (301) or gone (410). Checked before returning a 404.
        Schema::create('redirects', function (Blueprint $table) {
            $table->id();
            $table->string('from_path', 500)->unique();
            $table->string('to_path', 500)->nullable();
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->unsignedInteger('hits')->default(0);
            $table->timestamps();
        });

        // Editorial audit trail (docs/SECURITY.md §7).
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 64);
            $table->nullableMorphs('subject');
            $table->string('ip', 45)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'redirects', 'certificates', 'comments', 'mood_post', 'post_tag', 'author_post', 'posts', 'issues', 'moods', 'tags', 'sections', 'authors'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
