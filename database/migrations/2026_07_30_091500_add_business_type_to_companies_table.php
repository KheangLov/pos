<?php

use App\Models\Company;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('business_type')->nullable()->after('name');
        });

        // Best-effort backfill for existing demo companies so the column isn't
        // left null on data created before this feature existed. New
        // companies pick a type through the required form field going
        // forward (see CompanyForm).
        Company::where('name', 'like', '%Coffee%')->update(['business_type' => 'coffee_shop']);
        Company::where('name', 'like', '%Electronics%')->orWhere('name', 'like', '%Tech%')->update(['business_type' => 'computer_phone_shop']);
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('business_type');
        });
    }
};
