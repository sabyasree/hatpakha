<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Phase 2 content audit: a suggested label per post that an admin confirms or dismisses (PLAN.md §5).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('audit_label', 12)->nullable()->index();   // keep|improve|merge|noindex|remove
            $table->json('audit_reasons')->nullable();
            $table->unsignedBigInteger('audit_merge_into_id')->nullable();
            $table->timestamp('audit_decided_at')->nullable();
            $table->foreignId('audit_decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('audit_decision', 12)->nullable();          // applied|dismissed
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('audit_decided_by');
            $table->dropColumn(['audit_label', 'audit_reasons', 'audit_merge_into_id', 'audit_decided_at', 'audit_decision']);
        });
    }
};
