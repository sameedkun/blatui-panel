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
        Schema::create('blocked_ips', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address', 45);
            // restrictOnDelete(), not cascadeOnDelete(): InnoDB refuses to create a FK with
            // ON DELETE CASCADE/SET NULL on a column that a stored generated column depends on
            // (user_scope, below, depends on user_id) — MySQL error 1215. DeletionService
            // explicitly deletes a user's blocked_ips rows before force-deleting the account, so
            // this restriction is never actually hit in practice; it's just the DB-level backstop.
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('user_scope')->storedAs('COALESCE(user_id, 0)');
            $table->text('reason')->nullable();
            $table->foreignId('blocked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('hits')->default(0);
            $table->timestamp('last_hit_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['ip_address', 'user_scope']);
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blocked_ips');
    }
};
