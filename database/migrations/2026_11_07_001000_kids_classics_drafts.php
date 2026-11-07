<?php

use Illuminate\Database\Migrations\Migration;

/**
 * D101: public-domain classics for ছোটদের হাতপাখা (Sukumar Ray d. 1923, Upendrakishore Ray Chowdhury d. 1915), taken
 * from Bengali Wikisource (database/content/kids-classics.json). Saved as DRAFTS under their real authors with the
 * source link; the owner checks them against the source and publishes. Safe to run again.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }
        \App\Support\KidsClassics::import();
    }

    public function down(): void {}
};
