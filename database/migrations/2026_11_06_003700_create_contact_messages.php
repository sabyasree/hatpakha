<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** D89: the contact and advertising forms (instead of an e-mail address), and the two pages' shorter copy. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 20)->index();          // contact | advertise
            $table->string('topic', 40);
            $table->string('name', 80);
            $table->string('email', 190);
            $table->string('organisation', 120)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('link', 500)->nullable();
            $table->json('interests')->nullable();
            $table->text('body');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_hash', 64)->nullable();      // never the raw address (minimal PII)
            $table->string('status', 20)->default('new')->index();   // new | replied | closed | spam
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
        });

        // Shorter page copy; only if the page still has the text we imported (never over a desk edit).
        $contact = DB::table('pages')->where('slug', 'contact-us')->first();
        if ($contact && str_contains((string) $contact->body_html, '<h2>কী নিয়ে লিখবেন</h2>')) {
            DB::table('pages')->where('id', $contact->id)->update(['updated_at' => now(), 'body_html' => '<p>প্রশ্ন, পরামর্শ, প্রকাশিত লেখায় সংশোধন বা ওয়েবসাইটে কোনও সমস্যা: নিচের ফর্মে লিখুন, আমরা ই-মেলে উত্তর দেব।</p>'
                .'<p><strong>লেখা পাঠাতে এই ফর্ম নয়:</strong> লেখা নেওয়া হয় শুধু <a href="/submit/">লেখা পাঠান</a> ফর্মে (<a href="/submissions/">নিয়ম দেখুন</a>)।</p>']);
        }
        $ad = DB::table('pages')->where('slug', 'advertise')->first();
        if ($ad && str_contains((string) $ad->body_html, 'বিস্তারিত জানতে লিখুন')) {
            $body = preg_replace('~<p>বিস্তারিত জানতে লিখুন.*?</p>~su', '<p>নিচের ফর্মে আপনার প্রতিষ্ঠান আর কী ধরনের সহযোগিতা চান জানান, আমরা প্রস্তাব পাঠাব।</p>', (string) $ad->body_html);
            DB::table('pages')->where('id', $ad->id)->update(['updated_at' => now(), 'body_html' => $body]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};
