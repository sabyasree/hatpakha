<?php

use App\Console\Commands\ImportWordPress;
use App\Models\Page;
use Illuminate\Database\Migrations\Migration;

/**
 * Owner (2026-11-24): "change reply time to 2-3 weeks, in the site all places". The rules page and the FAQ now say
 * the answer comes "সাধারণত ২–৩ সপ্তাহের মধ্যে". Refreshes the two pages from database/content/pages, unless they
 * were edited in /desk (then the owner changes the words there).
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
