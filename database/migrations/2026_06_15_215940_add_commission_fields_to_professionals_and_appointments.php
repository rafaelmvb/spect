<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('professionals', function (Blueprint $table) {
            $table->decimal('commission_percent', 5, 2)->default(80)->after('price_per_hour');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->decimal('service_price', 10, 2)->nullable()->after('duration_minutes');
            $table->unsignedBigInteger('service_id')->nullable()->after('service_price');
        });
    }

    public function down(): void
    {
        Schema::table('professionals', function (Blueprint $table) {
            $table->dropColumn('commission_percent');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn(['service_price', 'service_id']);
        });
    }
};
