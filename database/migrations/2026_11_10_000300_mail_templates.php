<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Owner, 2026-11-10 (D123): the owner's own wording for the site's e-mails. A missing row = the original text in code. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_templates', function (Blueprint $table) {
            $table->string('key', 40)->primary();
            $table->string('subject', 200);
            $table->string('greeting', 200)->nullable();
            $table->text('body');
            $table->string('button', 80)->nullable();
            $table->string('salutation', 120)->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_templates');
    }
};
