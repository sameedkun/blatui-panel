<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('deletion_requested_at')->nullable()->index()->after('ban_reason');
            $table->string('deletion_requested_by')->nullable()->after('deletion_requested_at');
            $table->text('deletion_reason')->nullable()->after('deletion_requested_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['deletion_requested_at']);
            $table->dropColumn(['deletion_requested_at', 'deletion_requested_by', 'deletion_reason']);
        });
    }
};
