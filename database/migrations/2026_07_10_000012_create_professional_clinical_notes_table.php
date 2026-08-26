<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('professional_clinical_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('professional_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('patient_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('product_id', 36)->index();
            $table->text('note');
            $table->timestamps();

            $table->index(['professional_user_id', 'patient_user_id', 'product_id'], 'idx_prof_note_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('professional_clinical_notes');
    }
};
