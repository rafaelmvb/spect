<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_community_posts', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->nullable()->after('member_community_page_id')->index();
            $table->unsignedBigInteger('member_community_page_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('member_community_posts', function (Blueprint $table) {
            $table->dropColumn('product_id');
            $table->unsignedBigInteger('member_community_page_id')->nullable(false)->change();
        });
    }
};
