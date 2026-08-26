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
        Schema::create('journey_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('journey_id')->index();
            $table->unsignedBigInteger('product_id')->nullable()->index();
            $table->enum('status', ['draft', 'ready', 'published'])->default('draft');
            $table->json('report_data')->nullable();
            $table->text('admin_notes')->nullable();
            $table->text('ai_summary')->nullable();
            $table->text('custom_content')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id', 'journey_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journey_reports');
    }
};
