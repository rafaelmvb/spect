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
        Schema::table('member_community_events', function (Blueprint $table) {
            $table->string('visibility', 20)->default('all')->after('is_online');
            $table->json('visible_product_ids')->nullable()->after('visibility');
            $table->boolean('is_paid')->default(false)->after('visible_product_ids');
            $table->decimal('price', 10, 2)->nullable()->after('is_paid');
            $table->string('payment_link', 500)->nullable()->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('member_community_events', function (Blueprint $table) {
            $table->dropColumn(['visibility', 'visible_product_ids', 'is_paid', 'price', 'payment_link']);
        });
    }
};
