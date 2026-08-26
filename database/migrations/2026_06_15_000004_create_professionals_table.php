<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('professionals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->string('name', 255);
            $table->string('email', 255)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('avatar')->nullable();
            $table->text('bio')->nullable();
            $table->string('specialty', 255)->nullable();
            $table->string('registration_type', 30)->nullable(); // CRP, CRM, CREFITO, etc.
            $table->string('registration_number', 60)->nullable();
            $table->decimal('price_per_hour', 10, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('professionals');
    }
};
