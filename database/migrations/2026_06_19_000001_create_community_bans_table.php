<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_bans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('product_id')->nullable(); // null = ban em todos os produtos do tenant
            $table->string('reason', 500)->nullable();
            $table->unsignedBigInteger('banned_by');
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id', 'product_id']);
            $table->index(['tenant_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_bans');
    }
};
