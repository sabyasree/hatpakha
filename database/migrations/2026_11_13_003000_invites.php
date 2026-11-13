<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** D184: "হাতপাখার বন্ধু": each member's invite link, and who brought whom (for the বন্ধু medal). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('invite_code', 12)->nullable()->unique();
            $t->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
        });
        // The weekly letter's "come back" blocks (today's riddle, the open theme, a running serial), kept apart from the pieces.
        Schema::table('newsletters', fn (Blueprint $t) => $t->json('extras')->nullable());
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropConstrainedForeignId('invited_by');
            $t->dropColumn('invite_code');
        });
        Schema::table('newsletters', fn (Blueprint $t) => $t->dropColumn('extras'));
    }
};
