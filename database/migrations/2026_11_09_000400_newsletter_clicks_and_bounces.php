<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D117: which links in a letter were clicked (counted on our own site, no outside tracker), and why an
 * address stopped getting mail (bounced address or spam complaint reported by Amazon SES).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('newsletter_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('delivery_id')->index();
            $table->string('link', 12);            // home | cta | 0, 1, 2 … (the n-th piece in the letter)
            $table->timestamp('created_at')->nullable();
            $table->index(['newsletter_id', 'link']);
        });
        Schema::table('newsletter_deliveries', fn (Blueprint $table) => $table->string('bounce', 20)->nullable()->after('error'));
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_clicks');
        Schema::table('newsletter_deliveries', fn (Blueprint $table) => $table->dropColumn('bounce'));
    }
};
