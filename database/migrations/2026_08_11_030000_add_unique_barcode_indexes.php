<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Scoped per company (not globally) - this is a white-label install
        // where unrelated businesses share one database, and two of them
        // legitimately stocking the same manufacturer barcode is normal.
        // Verified 0 duplicate (company_id, barcode) pairs before adding.
        Schema::table('products', function (Blueprint $table) {
            $table->unique(['company_id', 'barcode']);
        });

        // product_variants has no company_id of its own (only reachable via
        // product_id -> products.company_id), so this ends up global rather
        // than per-company - verified 0 duplicates globally before adding.
        // A collision between two different companies' variants is possible
        // in principle; if that ever happens in practice, the real fix is a
        // denormalized company_id column here, not dropping the constraint.
        Schema::table('product_variants', function (Blueprint $table) {
            $table->unique('barcode');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'barcode']);
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropUnique(['barcode']);
        });
    }
};
