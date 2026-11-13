<?php

use App\Console\Commands\ImportWordPress;
use App\Models\Page;
use Illuminate\Database\Migrations\Migration;

/** Privacy policy: invite links, and top players named in our social posts (D184, owner 2026-11-13). Skips a page edited in /desk. */
return new class extends Migration
{
    public function up(): void
    {
        $page = Page::where('slug', 'privacy-policy')->first();
        $revised = ImportWordPress::revisedPage('privacy-policy');
        if ($page && $revised && ! $page->edited_in_desk_at) {
            $page->update(['body_html' => $revised['body']]);
        }
    }

    public function down(): void
    {
    }
};
