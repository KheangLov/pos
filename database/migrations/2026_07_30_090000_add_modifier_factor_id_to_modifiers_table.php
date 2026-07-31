<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('modifiers', function (Blueprint $table) {
            // Nullable + nullOnDelete: most modifiers have no factor (e.g. "Oat
            // milk" is just a flat add-on), and removing a factor should fall
            // back to the modifier's own price rather than break the modifier.
            $table->foreignId('modifier_factor_id')->nullable()->after('price')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('modifiers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('modifier_factor_id');
        });
    }
};
