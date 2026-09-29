<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dashboard reports: recurring schedules, and the metadata + queue state of
     * every generated report file (the file itself lives on the default disk).
     *
     * `report` holds a report-definition key from config('dashboard.reports'),
     * not a foreign key — definitions are code, so an app can add or retire
     * one without a migration.
     */
    public function up(): void
    {
        Schema::create('scheduled_reports', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('report', 60);
            $table->string('format', 10);
            $table->json('filters')->nullable();

            $table->string('frequency', 10);
            $table->unsignedTinyInteger('day_of_week')->nullable();
            $table->unsignedTinyInteger('day_of_month')->nullable();
            $table->unsignedTinyInteger('hour')->default(8);
            $table->json('recipients');

            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'next_run_at']);
        });

        Schema::create('generated_reports', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('report', 60);
            $table->string('title');
            $table->string('format', 10);
            $table->string('status', 20)->default('pending');
            $table->string('source', 20)->default('manual');

            $table->timestamp('range_start');
            $table->timestamp('range_end');
            $table->json('filters')->nullable();

            $table->string('file_path')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->unsignedInteger('row_count')->nullable();
            $table->text('error')->nullable();

            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('scheduled_report_id')->nullable()->constrained('scheduled_reports')->nullOnDelete();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['source', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_reports');
        Schema::dropIfExists('scheduled_reports');
    }
};
