<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** AI editor (D42): pending writer checks, AI-drafted posts, issue proposals. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            // Word-level edits waiting for the writer's OK, with the publish settings the owner chose.
            $table->json('pending_edits')->nullable()->after('review_note');
            // Drafted by the AI editor (e.g. the monthly editorial); a person edits and approves it.
            $table->timestamp('ai_drafted_at')->nullable()->after('pending_edits');
            // AI cover illustration (D43): proposed until the owner approves; then it becomes the featured image.
            $table->string('proposed_image_path')->nullable()->after('featured_image_height');
            $table->unsignedSmallInteger('proposed_image_width')->nullable()->after('proposed_image_path');
            $table->unsignedSmallInteger('proposed_image_height')->nullable()->after('proposed_image_width');
            $table->string('proposed_image_error', 200)->nullable()->after('proposed_image_height');
            $table->boolean('image_is_ai')->default(false)->after('proposed_image_error');   // credit line on the page
        });
        Schema::table('comments', function (Blueprint $table) {
            // AI pre-screen of a waiting comment (ok | spam | check) with a short reason; the editor decides.
            $table->string('ai_verdict', 10)->nullable()->after('status');
            $table->string('ai_reason', 200)->nullable()->after('ai_verdict');
        });
        Schema::table('issues', function (Blueprint $table) {
            // The AI editor's proposal: lineup, theme, reasons. Status "proposed" is never public.
            $table->json('ai_plan')->nullable()->after('editor_note');
            $table->foreignId('editorial_post_id')->nullable()->after('ai_plan')->constrained('posts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->dropConstrainedForeignId('editorial_post_id');
            $table->dropColumn('ai_plan');
        });
        Schema::table('comments', function (Blueprint $table) {
            $table->dropColumn(['ai_verdict', 'ai_reason']);
        });
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn(['pending_edits', 'ai_drafted_at', 'proposed_image_path', 'proposed_image_width', 'proposed_image_height', 'proposed_image_error', 'image_is_ai']);
        });
    }
};
