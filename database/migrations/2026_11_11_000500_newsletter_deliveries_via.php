<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** D129: which service carried each letter (Amazon SES or Brevo), so each one's daily limit is counted on its own. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('newsletter_deliveries', fn (Blueprint $t) => $t->string('via', 10)->nullable()->after('sent_at'));
    }

    public function down(): void
    {
        Schema::table('newsletter_deliveries', fn (Blueprint $t) => $t->dropColumn('via'));
    }
};
