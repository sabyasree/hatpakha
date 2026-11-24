<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// D277: issues may be "special" (বিশেষ সংখ্যা) besides monthly and festival; the ফিরে আসা issue was made as a festival one.
return new class extends Migration
{
    public function up(): void
    {
        DB::table('issues')->where('slug', \App\Support\WriterInvites::ISSUE_SLUG)->where('kind', 'festival')->update(['kind' => 'special']);
    }

    public function down(): void
    {
        DB::table('issues')->where('kind', 'special')->update(['kind' => 'festival']);
    }
};
