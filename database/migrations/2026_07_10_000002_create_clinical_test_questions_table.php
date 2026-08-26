<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinical_test_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinical_test_id')->constrained('clinical_tests')->cascadeOnDelete();
            $table->text('text');
            // scale: 1-N | single: uma opção | multi: múltiplas | boolean: sim/não
            $table->string('type', 20)->default('scale');
            $table->unsignedTinyInteger('scale_min')->default(1);
            $table->unsignedTinyInteger('scale_max')->default(5);
            $table->json('scale_labels')->nullable(); // {1: "Nunca", 3: "Às vezes", 5: "Sempre"}
            $table->smallInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinical_test_questions');
    }
};
