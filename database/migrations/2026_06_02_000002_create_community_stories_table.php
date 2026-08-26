<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_community_stories', function (Blueprint $table) {
            $table->id();
            $table->char('product_id', 36)->index();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->text('content')->nullable();
            $table->string('image', 500)->nullable();
            $table->string('video_url', 500)->nullable();
            $table->string('bg_color', 20)->default('#1e1e2e');
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->index(['product_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_community_stories');
    }
};
