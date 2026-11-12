<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

/**
 * D163 (owner, 2026-11-12): once, on deploy: writers shown by an old login get the name from their own profile
 * (hatpakha:profile-names). Safe without the old WordPress database: then only account names are used.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! app()->runningUnitTests()) {
            rescue(fn () => Artisan::call('hatpakha:profile-names'), null, false);
        }
    }

    public function down(): void {}
};
