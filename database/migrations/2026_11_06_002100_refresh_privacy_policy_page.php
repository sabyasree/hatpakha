<?php

use App\Console\Commands\ImportWordPress;
use App\Models\Page;
use Illuminate\Database\Migrations\Migration;

/**
 * The owner asked for the privacy policy to cover the read counter, AI help and the chat (D54, D52).
 * Refreshes the page from database/content/pages/privacy-policy.html, unless it was edited in /desk.
 */
return new class extends Migration
{
    public function up(): void
    {
        $page = Page::where('slug', 'privacy-policy')->first();
        $revised = ImportWordPress::revisedPage('privacy-policy');
        if ($page && $revised && ! $page->edited_in_desk_at) {
            $page->update(['body_html' => $revised['body'], 'seo_description' => $revised['seo_description'] ?? $page->seo_description]);
        }
    }

    public function down(): void
    {
    }
};
