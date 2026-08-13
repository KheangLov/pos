<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            // Both required together for a formal Bakong merchant QR
            // (KHQR\Models\MerchantInfo); when either is empty, KhqrService
            // falls back to an individual-account QR, which only needs the
            // Bakong account ID already stored in account_details.
            $table->string('merchant_id')->nullable()->after('account_details');
            $table->string('acquiring_bank')->nullable()->after('merchant_id');

            // Bearer token from KHQR\BakongKHQR::renewToken() - authenticates
            // calls to Bakong's transaction-check API. Long-lived JWT, hence text.
            $table->text('bakong_token')->nullable()->after('acquiring_bank');
        });
    }

    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropColumn(['merchant_id', 'acquiring_bank', 'bakong_token']);
        });
    }
};
