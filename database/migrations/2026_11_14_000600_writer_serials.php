<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** D193: a writer can propose a serial of their own and send its episodes. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('serials', function (Blueprint $t) {
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();   // the writer whose serial it is (null: the desk's own)
            $t->text('plan')->nullable();                                         // the writer's outline of the whole story (desk only)
            $t->unsignedSmallInteger('planned_count')->nullable();                // how many episodes they expect
        });
    }

    public function down(): void
    {
        Schema::table('serials', function (Blueprint $t) {
            $t->dropConstrainedForeignId('user_id');
            $t->dropColumn(['plan', 'planned_count']);
        });
    }
};
