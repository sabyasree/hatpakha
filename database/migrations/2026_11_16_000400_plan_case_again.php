<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

/** D220: the day's one try at planning a detective case was used up by a refused plan; let it try again now. */
return new class extends Migration
{
    public function up(): void
    {
        rescue(fn () => Setting::put('handover.case_tried', ''), null, false);
    }

    public function down(): void
    {
    }
};
