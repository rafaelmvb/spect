<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('professional_patient_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('professional_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('patient_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('product_id', 36)->index();
            $table->string('status')->default('active'); // active, inactive
            $table->timestamps();

            $table->unique(['professional_user_id', 'patient_user_id', 'product_id'], 'uq_prof_patient_product');
            $table->index(['professional_user_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('professional_patient_links');
    }
};
