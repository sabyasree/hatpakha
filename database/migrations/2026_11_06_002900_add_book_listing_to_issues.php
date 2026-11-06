<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The issue's book listing for Google Play Books (D68): description and keywords, editable by the owner. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issues', function (Blueprint $t) {
            $t->text('book_description')->nullable()->after('editor_note');
            $t->string('book_keywords', 300)->nullable()->after('book_description');
        });
    }

    public function down(): void
    {
        Schema::table('issues', fn (Blueprint $t) => $t->dropColumn(['book_description', 'book_keywords']));
    }
};
