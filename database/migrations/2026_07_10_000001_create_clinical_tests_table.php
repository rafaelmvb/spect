<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinical_tests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->string('name');
            $table->string('category', 50)->default('geral'); // tdah, tea, ah_sd, humor, geral
            $table->text('description')->nullable();
            $table->text('instructions')->nullable();
            $table->unsignedSmallInteger('estimated_minutes')->default(5);
            $table->boolean('is_active')->default(true);
            $table->smallInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinical_tests');
    }
};
