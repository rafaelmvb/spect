<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_mood_checkins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('product_id', 36)->index();
            $table->tinyInteger('mood')->comment('1=muito mal, 2=mal, 3=neutro, 4=bem, 5=muito bem');
            $table->string('note', 500)->nullable();
            $table->date('checkin_date');
            $table->timestamps();

            $table->unique(['user_id', 'product_id', 'checkin_date'], 'uq_user_product_date');
            $table->index(['user_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_mood_checkins');
    }
};
