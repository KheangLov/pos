<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Postgres does not index FK columns automatically — every hot-path FK and
 * lookup column gets a covering index here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->index(['branch_id', 'created_at']);
            $table->index(['branch_id', 'status']);
            $table->index('shift_id');
            $table->index('table_id');
            $table->index('user_id');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->index('invoice_id');
            $table->index('product_id');
            $table->index('product_variant_id');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index('invoice_id');
        });

        Schema::table('stock_transactions', function (Blueprint $table) {
            $table->index(['branch_id', 'product_id']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->index(['company_id', 'category_id']);
            $table->index(['company_id', 'is_active']);
            $table->index('sku');
            $table->index('barcode');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->index('product_id');
            $table->index('sku');
            $table->index('barcode');
        });

        Schema::table('serial_numbers', function (Blueprint $table) {
            $table->index(['branch_id', 'product_id', 'status']);
            $table->index('serial_number');
        });

        Schema::table('shifts', function (Blueprint $table) {
            $table->index(['branch_id', 'status']);
            $table->index('user_id');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->index(['company_id', 'is_active']);
        });

        Schema::table('tables', function (Blueprint $table) {
            $table->index('floor_plan_id');
            $table->index('uuid');
        });

        Schema::table('floor_plans', function (Blueprint $table) {
            $table->index('branch_id');
        });

        Schema::table('cash_notes', function (Blueprint $table) {
            $table->index('shift_id');
        });

        Schema::table('modifiers', function (Blueprint $table) {
            $table->index('modifier_group_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index(['company_id', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['branch_id', 'created_at']);
            $table->dropIndex(['branch_id', 'status']);
            $table->dropIndex(['shift_id']);
            $table->dropIndex(['table_id']);
            $table->dropIndex(['user_id']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex(['invoice_id']);
            $table->dropIndex(['product_id']);
            $table->dropIndex(['product_variant_id']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['invoice_id']);
        });

        Schema::table('stock_transactions', function (Blueprint $table) {
            $table->dropIndex(['branch_id', 'product_id']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'category_id']);
            $table->dropIndex(['company_id', 'is_active']);
            $table->dropIndex(['sku']);
            $table->dropIndex(['barcode']);
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropIndex(['product_id']);
            $table->dropIndex(['sku']);
            $table->dropIndex(['barcode']);
        });

        Schema::table('serial_numbers', function (Blueprint $table) {
            $table->dropIndex(['branch_id', 'product_id', 'status']);
            $table->dropIndex(['serial_number']);
        });

        Schema::table('shifts', function (Blueprint $table) {
            $table->dropIndex(['branch_id', 'status']);
            $table->dropIndex(['user_id']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'is_active']);
        });

        Schema::table('tables', function (Blueprint $table) {
            $table->dropIndex(['floor_plan_id']);
            $table->dropIndex(['uuid']);
        });

        Schema::table('floor_plans', function (Blueprint $table) {
            $table->dropIndex(['branch_id']);
        });

        Schema::table('cash_notes', function (Blueprint $table) {
            $table->dropIndex(['shift_id']);
        });

        Schema::table('modifiers', function (Blueprint $table) {
            $table->dropIndex(['modifier_group_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'branch_id']);
        });
    }
};
