<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** D108 (owner): ধারাবাহিক, serial stories with a new episode every week. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('serials', function (Blueprint $t) {
            $t->id();
            $t->string('slug', 120)->nullable()->unique();
            $t->string('title', 200);
            $t->string('logline', 400)->nullable();
            $t->string('genre', 40)->nullable();
            $t->string('writer', 40)->nullable();          // the AI writer (Team agent) that drafts it
            $t->string('status', 12)->default('proposed');  // proposed | active | paused | finished
            $t->unsignedTinyInteger('day')->default(6);      // weekday of new episodes (0 Sunday … 6 Saturday)
            $t->json('bible')->nullable();                   // setting, characters, planned episodes (desk only)
            $t->string('cover_path')->nullable();
            $t->unsignedSmallInteger('cover_width')->nullable();
            $t->unsignedSmallInteger('cover_height')->nullable();
            $t->timestamp('started_at')->nullable();
            $t->timestamps();
        });
        Schema::table('posts', function (Blueprint $t) {
            $t->foreignId('serial_id')->nullable()->after('section_id')->constrained('serials')->nullOnDelete();
            $t->unsignedSmallInteger('episode_no')->nullable()->after('serial_id');
            $t->text('episode_summary')->nullable()->after('episode_no');   // private: keeps the next episode consistent
        });
        Schema::create('serial_follows', function (Blueprint $t) {
            $t->foreignId('serial_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->timestamp('created_at')->nullable();
            $t->primary(['serial_id', 'user_id']);
        });
        if (! DB::table('sections')->where('slug', 'dharabahik')->exists()) {
            DB::table('sections')->insert(['slug' => 'dharabahik', 'name_bn' => 'ধারাবাহিক', 'format' => 'prose', 'sort' => 25, 'nav_group' => 'literature',
                'is_active' => true, 'about' => 'সপ্তাহে একটি করে পর্ব: রহস্য, অলৌকিক আর সম্পর্কের গল্প। আগের পর্ব ভুলে গেলে পাতাতেই সব পর্ব আছে।',
                'created_at' => now(), 'updated_at' => now()]);
        }
        cache()->forget('sections.menu');
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $t) {
            $t->dropConstrainedForeignId('serial_id');
            $t->dropColumn(['episode_no', 'episode_summary']);
        });
        Schema::dropIfExists('serial_follows');
        Schema::dropIfExists('serials');
    }
};
