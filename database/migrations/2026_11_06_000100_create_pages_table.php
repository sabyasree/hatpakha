<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Static pages (about, submission guidelines, privacy, terms…) at /{slug}/ like WordPress pages.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 200)->unique();
            $table->string('title');
            $table->longText('body_html')->nullable();
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->boolean('noindex')->default(false);
            $table->string('status', 20)->default('published');
            $table->boolean('show_in_footer')->default(false);
            $table->unsignedSmallInteger('sort')->default(100);
            $table->unsignedBigInteger('legacy_wp_id')->nullable()->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
