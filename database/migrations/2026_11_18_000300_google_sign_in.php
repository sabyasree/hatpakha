<?php

use App\Console\Commands\ImportWordPress;
use App\Models\Page;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** D235: "Google দিয়ে ঢুকুন". The Google account an account is tied to, and the privacy page says what we receive. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->string('google_id', 64)->nullable()->unique());
        $page = Page::where('slug', 'privacy-policy')->first();
        $revised = ImportWordPress::revisedPage('privacy-policy');
        if ($page && $revised && ! $page->edited_in_desk_at) {
            $page->update(['body_html' => $revised['body']]);
        }
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('google_id'));
    }
};
