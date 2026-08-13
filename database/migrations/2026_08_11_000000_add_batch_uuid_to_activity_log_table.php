<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The original create_activity_log_table migration was written against
 * spatie/laravel-activitylog 5.x's schema, but 4.12.3 is what's installed
 * (see composer.lock). The two differ in exactly this column: 4.x writes a
 * `batch_uuid` on every activity row, 5.x replaced it with
 * `attribute_changes`. Without it, *any* model save against a LogsActivity
 * model fails with "column batch_uuid does not exist".
 *
 * `attribute_changes` is deliberately left in place — 4.x never writes to it
 * and it's nullable, so it's inert; dropping a column is the riskier move on
 * a database that already holds live order history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->uuid('batch_uuid')->nullable()->after('properties');
        });
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropColumn('batch_uuid');
        });
    }
};
