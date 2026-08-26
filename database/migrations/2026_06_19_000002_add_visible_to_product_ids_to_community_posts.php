<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_community_posts', function (Blueprint $table) {
            // null = visível para todos; array de IDs = visível só para quem comprou aqueles produtos
            $table->json('visible_to_product_ids')->nullable()->after('is_hidden');
        });
    }

    public function down(): void
    {
        Schema::table('member_community_posts', function (Blueprint $table) {
            $table->dropColumn('visible_to_product_ids');
        });
    }
};
