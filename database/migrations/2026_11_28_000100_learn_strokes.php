<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * D325: stroke order per letter (or sign, or joined letter). A machine draft for every letter of the বর্ণমালা comes with
 * this release (database/content/strokes-draft.json); none is shown to children until it is approved at the desk.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learn_strokes', function (Blueprint $t) {
            $t->id();
            $t->string('glyph', 16)->unique();
            $t->json('strokes');                          // [[[x, y], …], …] in a 0–100 box
            $t->string('status', 10)->default('draft');   // draft | approved
            $t->string('source', 10)->default('machine'); // machine | desk
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
        });
        $file = database_path('content/strokes-draft.json');
        if (is_file($file)) {
            foreach ((array) json_decode((string) file_get_contents($file), true) as $glyph => $strokes) {
                DB::table('learn_strokes')->insert(['glyph' => $glyph, 'strokes' => json_encode($strokes), 'status' => 'draft', 'source' => 'machine', 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('learn_strokes');
    }
};
