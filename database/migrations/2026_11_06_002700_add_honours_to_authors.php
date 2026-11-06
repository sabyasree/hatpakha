<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Honouring writers (D64): a short honour shown with their name, the editor's words of praise, and when. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('authors', function (Blueprint $t) {
            $t->string('honour_label', 40)->nullable()->after('followers_count');   // e.g. মাসের লেখক · নভেম্বর ২০২৬
            $t->text('honour_note')->nullable()->after('honour_label');             // the editor's praise (plain text)
            $t->timestamp('honoured_at')->nullable()->after('honour_note');
        });
    }

    public function down(): void
    {
        Schema::table('authors', fn (Blueprint $t) => $t->dropColumn(['honour_label', 'honour_note', 'honoured_at']));
    }
};
