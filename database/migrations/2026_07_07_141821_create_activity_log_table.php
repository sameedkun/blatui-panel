<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_log', function (Blueprint $table) {
            $table->id();
            $table->string('log_name')->nullable()->index();
            $table->text('description');
            $table->nullableMorphs('subject', 'subject');
            $table->string('event')->nullable();
            $table->nullableMorphs('causer', 'causer');
            $table->json('attribute_changes')->nullable();
            $table->json('properties')->nullable();
            $table->timestamps();

            /**
             * The Spatie migration already indexes log_name and the polymorphic subject /
             * causer morphs (subject_type+subject_id, causer_type+causer_id). The Activity
             * Logs viewer additionally filters and sorts on these columns, none of which the
             * defaults cover:
             *
             *  - (event, created_at) composite — the security-signal stats filter
             *    `event = X AND created_at >= Y` together, so lead with event and let the
             *    range narrow within it.
             *  - causer_id (alone) — the composite (causer_type, causer_id) morph index
             *    can't serve `causer_id IS NULL` / distinct-causer / causer_id-only lookups.
             *  - created_at (alone) — the activity-pulse queries filter and sort on
             *    created_at with no event predicate, so the composite above doesn't apply.
             */
            $table->index(['event', 'created_at'], 'activity_log_event_created_at_index');
            $table->index('causer_id', 'activity_log_causer_id_index');
            $table->index('created_at', 'activity_log_created_at_index');
        });
    }
};
