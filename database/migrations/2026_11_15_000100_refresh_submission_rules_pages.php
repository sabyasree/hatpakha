<?php

use App\Console\Commands\ImportWordPress;
use App\Models\Page;
use Illuminate\Database\Migrations\Migration;

/**
 * Owner (2026-11-15, after a writer asked whether a translation may go to another magazine): the rules page says
 * plainly who holds the rights, that a piece or its translation may be published elsewhere, and how to change a
 * sent piece (D205). Refreshes the two pages from database/content/pages, unless they were edited in /desk.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['submissions', 'faqs'] as $slug) {
            $page = Page::where('slug', $slug)->first();
            $revised = ImportWordPress::revisedPage($slug);
            if ($page && $revised && ! $page->edited_in_desk_at) {
                $page->update(['body_html' => $revised['body']]);
            }
        }
    }

    public function down(): void
    {
    }
};
