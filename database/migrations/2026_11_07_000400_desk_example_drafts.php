<?php

use Illuminate\Database\Migrations\Migration;

/** D95: the two example pieces for the new sections, as DRAFTS under "হাতপাখা ডেস্ক" (never published by this). */
return new class extends Migration
{
    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }
        $desk = \App\Models\Author::where('slug', 'hatpakha-desk')->first();
        foreach (require database_path('content/desk-examples.php') as $x) {
            $sec = \App\Models\Section::where('slug', $x['section'])->first();
            if ($desk && $sec && ! \App\Models\Post::where('title', $x['title'])->exists()) {
                \App\Ai\DeskWriter::save($sec, $desk, $x['title'], $x['excerpt'], $x['paragraphs'], $x['facts'], $x['photos']);
            }
        }
    }

    public function down(): void {}
};
