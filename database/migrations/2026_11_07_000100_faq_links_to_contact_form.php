<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Owner: the FAQ points to the contact form, not the e-mail address (D89). Only the imported sentence changes. */
return new class extends Migration
{
    public function up(): void
    {
        $page = DB::table('pages')->where('slug', 'faqs')->first();
        if (! $page) {
            return;
        }
        $body = preg_replace(
            '~লেখার লিংক দিয়ে <a href="mailto:editor(?:&#64;|@)hatpakha\.com"[^>]*>editor(?:&#64;|@)hatpakha\.com</a>-এ লিখুন~u',
            '<a href="/contact-us/">যোগাযোগ ফর্মে</a> লেখার লিংক দিয়ে লিখুন',
            (string) $page->body_html, -1, $n);
        if ($n) {
            DB::table('pages')->where('id', $page->id)->update(['body_html' => $body, 'updated_at' => now()]);
        }
    }

    public function down(): void {}
};
