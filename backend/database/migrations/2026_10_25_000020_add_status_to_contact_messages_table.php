<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inbox workflow for contact messages: status (pending → in progress → waiting on user → resolved, or spam),
 * a private team note and who resolved it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->string('status', 20)->default('pending')->after('message');
            $table->text('admin_note')->nullable()->after('status');
            $table->timestamp('status_changed_at')->nullable()->after('admin_note');
            $table->timestamp('resolved_at')->nullable()->after('status_changed_at');
            $table->foreignId('resolved_by')->nullable()->after('resolved_at')->constrained('users')->nullOnDelete();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->dropIndex(['status', 'created_at']);
            $table->dropConstrainedForeignId('resolved_by');
            $table->dropColumn(['status', 'admin_note', 'status_changed_at', 'resolved_at']);
        });
    }
};
