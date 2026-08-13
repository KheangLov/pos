<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Nullable: cash taken before any PaymentMethod existed, and
            // installs that never configure the resource, both stay valid.
            $table->foreignId('payment_method_id')->nullable()->after('invoice_id')
                ->constrained()->nullOnDelete();

            // MD5 of the generated KHQR string (KHQR\BakongKHQR::generateMerchant()/
            // generateIndividual() already computes this) - the key Bakong's
            // check_transaction_by_md5 API needs to confirm a scan actually cleared.
            $table->string('khqr_md5')->nullable()->after('reference');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_method_id');
            $table->dropColumn('khqr_md5');
        });
    }
};
