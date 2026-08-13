<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hand-written because the package ships its migrations as .stub files under
 * vendor/spatie/laravel-activitylog/database/migrations, which `php artisan
 * migrate` does not pick up on its own.
 *
 * NOTE: this was originally written against activitylog 5.x's schema, but
 * 4.12.3 is the installed version. The `attribute_changes` column below is a
 * 5.x concept that 4.x never writes to, and 4.x's required `batch_uuid` was
 * missing entirely - added back in
 * 2026_08_11_000000_add_batch_uuid_to_activity_log_table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_log', function (Blueprint $table) {
            $table->id();
            $table->string('log_name')->nullable();
            $table->text('description');
            $table->nullableMorphs('subject');
            $table->string('event')->nullable();
            $table->nullableMorphs('causer');
            $table->json('properties')->nullable();
            $table->json('attribute_changes')->nullable();
            $table->timestamps();
            $table->index('log_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_log');
    }
};
