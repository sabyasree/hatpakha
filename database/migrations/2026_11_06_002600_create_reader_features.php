<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Reader features (D64): likes, a reading list, following writers. Signed-in readers only (no tracking of guests). */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['post_likes' => 'post_id', 'saved_posts' => 'post_id', 'follows' => 'author_id'] as $table => $col) {
            Schema::create($table, function (Blueprint $t) use ($col) {
                $t->foreignId('user_id')->constrained()->cascadeOnDelete();
                $t->foreignId($col)->constrained($col === 'post_id' ? 'posts' : 'authors')->cascadeOnDelete();
                $t->timestamp('created_at')->useCurrent();
                $t->primary(['user_id', $col]);
                $t->index($col);
            });
        }
        Schema::table('posts', function (Blueprint $t) {
            $t->unsignedInteger('likes_count')->default(0)->after('status');
            $t->timestamp('followers_notified_at')->nullable()->after('likes_count');
        });
        Schema::table('authors', fn (Blueprint $t) => $t->unsignedInteger('followers_count')->default(0)->after('published_count'));
    }

    public function down(): void
    {
        Schema::table('authors', fn (Blueprint $t) => $t->dropColumn('followers_count'));
        Schema::table('posts', fn (Blueprint $t) => $t->dropColumn(['likes_count', 'followers_notified_at']));
        Schema::dropIfExists('follows');
        Schema::dropIfExists('saved_posts');
        Schema::dropIfExists('post_likes');
    }
};
