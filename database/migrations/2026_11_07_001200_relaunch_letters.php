<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** D104: the apology letter to writers of the old unanswered submissions, and their answer per piece. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $t) {
            $t->string('relaunch_status', 10)->nullable()->after('backlog_tier');   // asked | yes | no
            $t->timestamp('relaunch_answered_at')->nullable()->after('relaunch_status');
            $t->boolean('relaunch_elsewhere')->nullable()->after('relaunch_answered_at');   // writer: already published elsewhere
            // Owner (mid-request): read the piece first; only ask about pieces worth publishing.
            $t->string('relaunch_worth', 4)->nullable()->after('relaunch_elsewhere');   // ask | skip (AI suggestion, owner may change)
            $t->unsignedTinyInteger('relaunch_score')->nullable()->after('relaunch_worth');   // 1-5
            $t->string('relaunch_note', 300)->nullable()->after('relaunch_score');   // one line for the owner, never shown to the writer
            $t->boolean('relaunch_worth_by_owner')->default(false)->after('relaunch_note');
        });
        Schema::table('authors', fn (Blueprint $t) => $t->timestamp('relaunch_letter_at')->nullable());
    }

    public function down(): void
    {
        Schema::table('posts', fn (Blueprint $t) => $t->dropColumn(['relaunch_status', 'relaunch_answered_at', 'relaunch_elsewhere', 'relaunch_worth', 'relaunch_score', 'relaunch_note', 'relaunch_worth_by_owner']));
        Schema::table('authors', fn (Blueprint $t) => $t->dropColumn('relaunch_letter_at'));
    }
};
