<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Both encrypted at the model level (Filament's MFA provider only
            // calls the model's save/get methods, it doesn't encrypt for you).
            $table->text('app_authentication_secret')->nullable();
            $table->text('app_authentication_recovery_codes')->nullable();
            $table->boolean('has_email_authentication')->default(false);
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('requires_2fa')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['app_authentication_secret', 'app_authentication_recovery_codes', 'has_email_authentication']);
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('requires_2fa');
        });
    }
};
