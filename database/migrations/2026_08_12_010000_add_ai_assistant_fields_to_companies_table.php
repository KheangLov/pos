<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('ai_assistant_enabled')->default(false);
            // Company's own Anthropic API key - usage is billed to them, not
            // us. Encrypted at the model level (see Company::casts()).
            $table->text('anthropic_api_key')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['ai_assistant_enabled', 'anthropic_api_key']);
        });
    }
};
