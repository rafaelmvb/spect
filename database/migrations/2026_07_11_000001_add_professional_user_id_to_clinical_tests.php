<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinical_tests', function (Blueprint $table) {
            $table->foreignId('professional_user_id')
                  ->nullable()
                  ->after('tenant_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            $table->index('professional_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('clinical_tests', function (Blueprint $table) {
            $table->dropForeign(['professional_user_id']);
            $table->dropColumn('professional_user_id');
        });
    }
};
